<?php

namespace App\Services;

use Illuminate\Support\Str;
use RuntimeException;

class MailContentReader
{
    public function __construct(private readonly ImapMailbox $mailbox) {}

    public function read(int $messageNumber): array
    {
        $structure = $this->mailbox->structure($messageNumber);
        $result = ['body' => '', 'html_body' => '', 'attachments' => [], 'parts' => 0, 'bytes' => 0];

        if ($structure) {
            $this->collectPart($messageNumber, $structure, '', $result);
        }

        return [
            'body' => trim($result['body']),
            'html_body' => trim($result['html_body']),
            'attachments' => $result['attachments'],
        ];
    }

    private function collectPart(int $messageNumber, object $part, string $partNumber, array &$result): void
    {
        if (++$result['parts'] > 100 || substr_count($partNumber, '.') > 10 || (int) ($part->bytes ?? 0) > 20 * 1024 * 1024) {
            throw new RuntimeException('MIME-Grenze überschritten.');
        }
        if (($part->type ?? null) === 1 && isset($part->parts)) {
            foreach ($part->parts as $index => $subPart) {
                $this->collectPart($messageNumber, $subPart, $partNumber === '' ? (string) ($index + 1) : $partNumber.'.'.($index + 1), $result);
            }

            return;
        }

        $rawBody = $this->mailbox->body($messageNumber, $partNumber);
        $result['bytes'] += strlen($rawBody);
        if ($result['bytes'] > 25 * 1024 * 1024) {
            throw new RuntimeException('Mail ist zu gross.');
        }
        $body = $this->decodeBody($rawBody, (int) ($part->encoding ?? 0));
        $mimeType = $this->mimeType($part);
        $filename = $this->partFilename($part);
        $disposition = Str::lower($part->disposition ?? '');
        $isAttachment = $filename || $disposition === 'attachment' || Str::lower($mimeType) === 'application/pdf';

        if ($isAttachment) {
            $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($body);
            if (! in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
                $claimed = $this->mimeType($part);
                $extension = Str::lower(pathinfo((string) $filename, PATHINFO_EXTENSION));
                if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true) && ! in_array($claimed, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
                    return;
                }
                throw new RuntimeException('Nicht unterstützter Anhang.');
            }
            if (count($result['attachments']) >= 5 || strlen($body) > 10 * 1024 * 1024) {
                throw new RuntimeException('Anhang-Limit überschritten.');
            }
            // Stored extensions are derived from actual content, never from the mail header.
            $filename = pathinfo(basename((string) $filename), PATHINFO_FILENAME) ?: 'mail-anhang';
            $filename = Str::limit($filename, 150, '').'.'.pathinfo($this->fallbackAttachmentFilename($mimeType), PATHINFO_EXTENSION);
            $result['attachments'][] = [
                'filename' => $filename ?: $this->fallbackAttachmentFilename($mimeType),
                'mime_type' => $mimeType,
                'contents' => $body,
            ];

            return;
        }

        if (($part->type ?? null) === 0) {
            foreach ($part->parameters ?? [] as $parameter) {
                if (strtolower($parameter->attribute ?? '') === 'charset') {
                    $encoding = (string) ($parameter->value ?? 'UTF-8');
                    if (in_array(strtoupper($encoding), array_map('strtoupper', mb_list_encodings()), true)) {
                        $body = mb_convert_encoding($body, 'UTF-8', $encoding);
                    }
                }
            }
            if (Str::lower($part->subtype ?? '') === 'html') {
                $result['html_body'] .= "\n".$body;
            }

            $text = Str::lower($part->subtype ?? '') === 'html' ? trim(strip_tags($body)) : trim($body);
            $result['body'] .= "\n".$text;
        }
    }

    private function partFilename(object $part): ?string
    {
        foreach (['dparameters', 'parameters'] as $property) {
            foreach (($part->{$property} ?? []) as $parameter) {
                if (in_array(Str::lower($parameter->attribute ?? ''), ['filename', 'name'], true)) {
                    return mb_decode_mimeheader((string) ($parameter->value ?? ''));
                }
            }
        }

        return null;
    }

    private function fallbackAttachmentFilename(string $mimeType): string
    {
        $extension = match (Str::lower($mimeType)) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };

        return 'mail-anhang.'.$extension;
    }

    private function mimeType(object $part): string
    {
        $types = ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'other'];
        $type = $types[(int) ($part->type ?? 7)] ?? 'application';
        $subtype = Str::lower($part->subtype ?? 'octet-stream');

        return "{$type}/{$subtype}";
    }

    private function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            3 => base64_decode($body, true) ?: '',
            4 => quoted_printable_decode($body),
            default => $body,
        };
    }
}
