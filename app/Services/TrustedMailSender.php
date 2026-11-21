<?php

namespace App\Services;

use Closure;

class TrustedMailSender
{
    public function __construct(private readonly DkimMailVerifier $dkim) {}

    public function fromAddress(object $headers, string $rawHeaders): ?string
    {
        if (strlen($rawHeaders) > 256 * 1024) {
            return null;
        }
        // Reply-To and Sender do not establish the sending identity.
        if (count($headers->from ?? []) !== 1 || preg_match_all('/^From:/mi', $rawHeaders) !== 1) {
            return null;
        }
        $from = $headers->from[0];
        $email = mb_strtolower(($from->mailbox ?? '').'@'.($from->host ?? ''));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function address(object $headers, string $rawHeaders, ?Closure $readBody = null): ?string
    {
        $email = $this->fromAddress($headers, $rawHeaders);
        if ($email === null) {
            return null;
        }
        $mode = config('mail_import.auth_mode', 'authserv');
        if ($mode === 'dkim') {
            return $readBody && $this->dkim->accepts($email, $headers, $rawHeaders, $readBody) ? $email : null;
        }
        if ($mode !== 'authserv' || ! config('mail_import.authentication_results_trusted')) {
            return null;
        }
        $domain = substr(strrchr($email, '@'), 1);
        $unfolded = preg_replace('/\r?\n[ \t]+/', ' ', $rawHeaders);
        preg_match_all('/^Authentication-Results:[ \t]*(.*)$/mi', $unfolded, $matches);
        $trusted = [];
        foreach ($matches[1] as $value) {
            $parts = $this->segments(trim($value));
            if ($parts === []) {
                return null;
            }
            $server = mb_strtolower(trim(array_shift($parts)));
            if (in_array($server, config('mail_import.trusted_authserv_ids', []), true)) {
                $trusted[] = $parts;
            }
        }
        // Accept a conservative subset. Unknown or ambiguous provider formats fail closed.
        if (count($trusted) !== 1) {
            return null;
        }
        $dmarc = array_values(array_filter($trusted[0], fn (string $part) => preg_match('/^\s*dmarc\s*=/i', $part)));
        if (count($dmarc) !== 1 || ! preg_match('/^\s*dmarc=pass\s+header\.from=([a-z0-9.-]+)\s*$/i', $dmarc[0], $result)) {
            return null;
        }

        return mb_strtolower($result[1]) === $domain ? $email : null;
    }

    /** Do not interpret fake method clauses inside quoted reasons or RFC comments. */
    private function segments(string $value): array
    {
        $segments = [];
        $buffer = '';
        $quoted = false;
        $commentDepth = 0;
        $escaped = false;
        foreach (str_split($value) as $character) {
            if ($escaped) {
                $buffer .= $character;
                $escaped = false;

                continue;
            }
            if (ord($character) === 92 && ($quoted || $commentDepth > 0)) {
                $escaped = true;
            } elseif ($character === '"' && $commentDepth === 0) {
                $quoted = ! $quoted;
            } elseif (! $quoted && $character === '(') {
                $commentDepth++;
            } elseif (! $quoted && $character === ')') {
                if (--$commentDepth < 0) {
                    return [];
                }
            } elseif ($character === ';' && ! $quoted && $commentDepth === 0) {
                $segments[] = $buffer;
                $buffer = '';

                continue;
            }
            $buffer .= $character;
        }
        if ($quoted || $commentDepth !== 0 || $escaped) {
            return [];
        }
        $segments[] = $buffer;

        return $segments;
    }
}
