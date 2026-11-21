<?php

namespace App\Services;

use Closure;
use Dachs\MailAuth\DkimAlignmentChecker;
use Dachs\MailAuth\DkimCanonicalizer;
use Dachs\MailAuth\DkimDnsResolver;
use Dachs\MailAuth\DkimMessageParser;
use Dachs\MailAuth\DkimPublicKeyParser;
use Dachs\MailAuth\DkimSignatureHeaderParser;
use Dachs\MailAuth\NativeDnsDkimResolver;

final class DkimMailVerifier
{
    public function __construct(private readonly DkimDnsResolver $dns = new NativeDnsDkimResolver) {}

    /** Verify the outer message as received, before MIME decoding or extraction. */
    public function accepts(string $from, object $headers, string $rawHeaders, Closure $readBody): bool
    {
        $rawHeaders = rtrim($rawHeaders, "\r\n");
        if ($rawHeaders === '' || substr_count($rawHeaders, "\n") > 4096
            || preg_match('/\A[ \t]|\x00|\r(?!\n)|^(?![ \t]|[!-9;-~]+:)/m', $rawHeaders)) {
            return false;
        }
        $fields = (new DkimMessageParser)->parse($rawHeaders)['fields'];
        $byName = [];
        foreach ($fields as $index => $field) {
            $byName[strtolower($field['name'])][] = $index;
        }
        $signatures = $byName['dkim-signature'] ?? [];
        if ($signatures === [] || count($signatures) > 8 || count($byName['from'] ?? []) !== 1 || count($byName['subject'] ?? []) > 1) {
            return false;
        }

        // A signed recipient ties the forward to this mailbox, including on replay.
        $recipients = [];
        $destination = strtolower((string) config('mail_import.recipient'));
        foreach (['to', 'cc'] as $name) {
            if (count($byName[$name] ?? []) !== 1) {
                continue;
            }
            foreach ($headers->$name ?? [] as $recipient) {
                if (strtolower(($recipient->mailbox ?? '').'@'.($recipient->host ?? '')) === $destination) {
                    $recipients[] = $name;
                    break;
                }
            }
        }
        if ($recipients === []) {
            return false;
        }

        $domain = substr(strrchr($from, '@'), 1);
        $parser = new DkimSignatureHeaderParser;
        $canonicalizer = new DkimCanonicalizer;
        $body = null;
        $hashes = [];
        foreach ($signatures as $index) {
            $signature = $fields[$index];
            $tags = $parser->parse($signature['value']);
            $signed = $parser->signedHeaderNames($tags['h'] ?? '');
            if (! $this->uniqueTags($signature['value'], $tags) || ! $this->eligible($tags, $domain) || ! in_array('from', $signed, true)
                || ! array_intersect($recipients, $signed) || (isset($byName['subject']) && ! in_array('subject', $signed, true))) {
                continue;
            }
            [$headerMode, $bodyMode] = $canonicalizer->modes(strtolower($tags['c'] ?? 'simple/simple'));
            if ($headerMode === null || $bodyMode === null) {
                continue;
            }
            $data = $this->signedHeaders($fields, $byName, $index, $signed, $headerMode, $canonicalizer);
            if (! $this->verifySignature($tags, $data, $domain)) {
                continue;
            }
            $body ??= $readBody();
            if (strlen($body) > MailContentReader::MAX_MAIL_BYTES) {
                return false;
            }
            $hashes[$bodyMode] ??= $this->bodyHash($body, $bodyMode);
            if (hash_equals($hashes[$bodyMode], base64_decode($tags['bh'], true) ?: '')) {
                return true;
            }
        }

        return false;
    }

    /** The general DKIM parser returns one value per tag. Reject lists that would lose a tag. */
    private function uniqueTags(string $value, array $tags): bool
    {
        $value = trim($value);
        if (str_ends_with($value, ';')) {
            $value = substr($value, 0, -1);
        }

        return count($tags) === substr_count($value, ';') + 1;
    }

    private function eligible(array $tags, string $domain): bool
    {
        if (($tags['v'] ?? '') !== '1' || strtolower($tags['d'] ?? '') !== $domain
            || ! in_array($tags['a'] ?? '', ['rsa-sha256', 'ed25519-sha256'], true)
            || isset($tags['l']) || ! isset($tags['bh'], $tags['b'])) {
            return false;
        }
        $selector = $tags['s'] ?? '';
        if (filter_var($selector, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false || str_ends_with($selector, '.')
            || filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
            || strlen($selector.'._domainkey.'.$domain) > 253 || ! in_array('dns/txt', explode(':', $tags['q'] ?? 'dns/txt'), true)) {
            return false;
        }
        if (isset($tags['i']) && ! (new DkimAlignmentChecker)->identityDomainMatchesSigningDomain($tags['i'], $domain)) {
            return false;
        }
        $signedAt = $tags['t'] ?? '0';
        $expires = $tags['x'] ?? null;

        return ctype_digit($signedAt)
            && ($expires === null || (ctype_digit($expires) && (int) $expires >= time() && (int) $expires > (int) $signedAt));
    }

    private function signedHeaders(array $fields, array $byName, int $index, array $signed, string $mode, DkimCanonicalizer $canonicalizer): string
    {
        // RFC 6376 selects repeated header fields from the bottom, excluding this signature.
        $byName['dkim-signature'] = array_values(array_diff($byName['dkim-signature'], [$index]));
        $data = '';
        foreach ($signed as $name) {
            if (! empty($byName[$name])) {
                $field = $fields[array_pop($byName[$name])];
                $data .= $canonicalizer->canonicalizeHeader($field['name'], $field['value'], $mode)."\r\n";
            }
        }
        $signature = $fields[$index];
        $emptySignature = preg_replace('/(^|;)([ \t\r\n]*b[ \t\r\n]*=)[^;]*/i', '$1$2', $signature['value']);

        return $data.$canonicalizer->canonicalizeHeader($signature['name'], $emptySignature, $mode);
    }

    private function verifySignature(array $tags, string $data, string $domain): bool
    {
        try {
            $records = $this->dns->txt($tags['s'].'._domainkey.'.$domain);
        } catch (\ErrorException) {
            // A resolver warning is a failed lookup. A later import can retry it.
            return false;
        }
        $keyParser = new DkimPublicKeyParser;
        $key = count($records) === 1 ? $keyParser->parse($records[0]) : null;
        $signature = base64_decode($tags['b'], true);
        if ($key === null || ! $signature || ! $this->uniqueTags($records[0], $key['tags'])
            || $keyParser->policyError($key['tags'], $tags['a'], $tags['i'] ?? '@'.$domain) !== null) {
            return false;
        }
        $flags = explode(':', $key['tags']['t'] ?? '');
        if (in_array('y', $flags, true) || (in_array('s', $flags, true) && strtolower(strrchr($tags['i'] ?? '@'.$domain, '@')) !== '@'.$domain)) {
            return false;
        }
        if ($tags['a'] === 'ed25519-sha256') {
            // RFC 8463 signs the SHA-256 digest of the canonical headers.
            return strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES
                && sodium_crypto_sign_verify_detached($signature, hash('sha256', $data, true), $key['rawPublicKey']);
        }
        $publicKey = @openssl_pkey_get_public($key['pem']);
        $details = $publicKey ? openssl_pkey_get_details($publicKey) : false;

        return $details && $details['type'] === OPENSSL_KEYTYPE_RSA && $details['bits'] >= 1024
            && openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /** Hash one wire-format line at a time so large attachments fit the web PHP memory limit. */
    private function bodyHash(string $body, string $mode): string
    {
        $hash = hash_init('sha256');
        $blankLines = 0;
        $hasContent = false;
        $size = strlen($body);
        for ($offset = 0; $offset < $size; $offset = $end + 1) {
            $end = strpos($body, "\n", $offset);
            $end = $end === false ? $size : $end;
            $length = $end - $offset;
            if ($length && $body[$end - 1] === "\r") {
                $length--;
            }
            $line = substr($body, $offset, $length);
            if ($mode === 'relaxed') {
                $line = preg_replace('/[ \t]+/', ' ', rtrim($line, " \t"));
            }
            if ($line === '') {
                $blankLines++;

                continue;
            }
            if ($blankLines > 0) {
                hash_update($hash, str_repeat("\r\n", $blankLines));
                $blankLines = 0;
            }
            hash_update($hash, $line);
            hash_update($hash, "\r\n");
            $hasContent = true;
        }
        if (! $hasContent && $mode === 'simple') {
            hash_update($hash, "\r\n");
        }

        return hash_final($hash, true);
    }
}
