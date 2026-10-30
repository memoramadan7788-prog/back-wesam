<?php

namespace App\Services\Ats;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OllamaClient implements LlmClient
{
    public function __construct(
        private ?string $baseUrl = null,
        private ?int $timeout = null,
        private ?array $options = null,
    ) {
        $this->baseUrl ??= (string) config('ats.ollama.base_url');
        $this->timeout ??= (int) config('ats.ollama.timeout', 120);
        $this->options ??= (array) config('ats.ollama.options', []);
    }

    public function generate(string $system, string $prompt, string $model): string
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->connectTimeout(5)
                ->timeout($this->timeout)
                ->acceptJson()
                ->post('/api/generate', [
                    'model' => $model,
                    'system' => $system,
                    'prompt' => $prompt,
                    'format' => 'json',
                    'stream' => false,
                    'options' => $this->options,
                ]);
        } catch (ConnectionException $e) {
            throw new OllamaUnavailableException('خدمة Ollama غير متاحة أو انتهت مهلة الاتصال.', 0, $e);
        }

        if ($response->status() === 404) {
            throw new OllamaUnavailableException("الموديل '{$model}' غير منزّل على Ollama.");
        }
        if (! $response->successful()) {
            throw new OllamaUnavailableException('Ollama رجّع خطأ: HTTP ' . $response->status());
        }

        $text = $response->json('response');
        if (! is_string($text) || trim($text) === '') {
            throw new InvalidModelResponseException('Ollama رجّع رد فاضي.');
        }

        return $text;
    }
}
