<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

trait UsesOpenAi
{
    private function client(): PendingRequest
    {
        return Http::withToken(config('ai.openai.api_key'))
            ->connectTimeout(10)
            ->timeout(max(5, min(90, (int) config('ai.openai.timeout'))))
            ->acceptJson();
    }

    private function outputText(array $response): string
    {
        if (isset($response['output_text'])) {
            return $response['output_text'];
        }

        foreach ($response['output'] ?? [] as $output) {
            foreach ($output['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && isset($content['text'])) {
                    return $content['text'];
                }
            }
        }

        throw new RuntimeException('OpenAI-Antwort enthaelt keinen Text.');
    }

    private function deleteUploadedFile(string $id): void
    {
        try {
            $response = $this->client()->retry(2, 200, throw: false)->delete('https://api.openai.com/v1/files/'.rawurlencode($id));
            if ($response->failed() && $response->status() !== 404) {
                throw new RuntimeException('Remote cleanup failed');
            }
        } catch (Throwable) {
            // Every uploaded file also has an explicit one-hour expiry.
            Log::warning('AI-Datei konnte nicht sofort gelöscht werden; Ablauf nach einer Stunde ist gesetzt.');
        }
    }
}
