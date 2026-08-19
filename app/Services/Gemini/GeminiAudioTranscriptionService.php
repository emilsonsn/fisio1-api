<?php

namespace App\Services\Gemini;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiAudioTranscriptionService
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta';

    private const TRANSIENT_STATUSES = [429, 500, 502, 503, 504];

    public function transcribe(UploadedFile $audio): string
    {
        $size = (int) $audio->getSize();
        $maxBytes = (int) config('services.gemini.inline_max_bytes', 14 * 1024 * 1024);

        if ($size <= 0 || $size > $maxBytes) {
            throw new RuntimeException("O bloco de áudio possui {$size} bytes e excede o limite inline seguro de {$maxBytes} bytes.");
        }

        $contents = $audio->get();

        if (! is_string($contents) || $contents === '') {
            throw new RuntimeException('Não foi possível ler o bloco de áudio para transcrição.');
        }

        $mimeType = $audio->getMimeType() ?: 'audio/ogg';
        $models = $this->models();

        foreach ($models as $index => $model) {
            $startedAt = microtime(true);

            try {
                $response = Http::acceptJson()
                    ->withHeaders(['x-goog-api-key' => $this->apiKey()])
                    ->connectTimeout(10)
                    ->timeout((int) config('services.gemini.request_timeout', 60))
                    ->post(self::API_URL.'/models/'.rawurlencode($model).':generateContent', [
                        'contents' => [[
                            'role' => 'user',
                            'parts' => [
                                ['inlineData' => ['mimeType' => $mimeType, 'data' => base64_encode($contents)]],
                                ['text' => $this->prompt()],
                            ],
                        ]],
                        'generationConfig' => [
                            'maxOutputTokens' => (int) config('services.gemini.transcription_max_output_tokens', 4096),
                            'thinkingConfig' => ['thinkingLevel' => 'minimal'],
                        ],
                    ]);
            } catch (ConnectionException $exception) {
                Log::warning('Gemini inline transcription request could not be completed.', [
                    'model' => $model,
                    'audio_mime_type' => $mimeType,
                    'audio_size_bytes' => $size,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'exception' => $exception::class,
                    'message' => str($exception->getMessage())->limit(500)->toString(),
                ]);

                if ($index < count($models) - 1) {
                    continue;
                }

                throw new RuntimeException('Não foi possível conectar ao Gemini para transcrever o áudio.', previous: $exception);
            }

            if ($response->successful()) {
                return $this->transcript($response, $model, $mimeType, $size, $startedAt);
            }

            $this->logFailure($response, $model, $mimeType, $size, $startedAt);

            if ($this->isTransient($response) && $index < count($models) - 1) {
                continue;
            }

            throw new RuntimeException('O Gemini recusou a transcrição do áudio (HTTP '.$response->status().'). Consulte o log do servidor.');
        }

        throw new RuntimeException('Não foi possível transcrever o bloco de áudio com Gemini.');
    }

    private function transcript(Response $response, string $model, string $mimeType, int $size, float $startedAt): string
    {
        $finishReason = $response->json('candidates.0.finishReason');
        $transcript = collect($response->json('candidates.0.content.parts', []))
            ->pluck('text')
            ->filter(fn ($text) => is_string($text))
            ->implode('');
        $transcript = trim($transcript);

        Log::info('Gemini inline audio chunk transcribed.', [
            'model' => $model,
            'audio_mime_type' => $mimeType,
            'audio_size_bytes' => $size,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'finish_reason' => $finishReason,
            'transcript_characters' => mb_strlen($transcript),
            'input_tokens' => $response->json('usageMetadata.promptTokenCount'),
            'output_tokens' => $response->json('usageMetadata.candidatesTokenCount'),
            'thought_tokens' => $response->json('usageMetadata.thoughtsTokenCount'),
            'total_tokens' => $response->json('usageMetadata.totalTokenCount'),
            'request_id' => $response->header('x-request-id'),
        ]);

        if ($transcript === '') {
            throw new RuntimeException('O Gemini retornou uma transcrição vazia para o bloco de áudio.');
        }

        if ($finishReason !== 'STOP') {
            throw new RuntimeException('A transcrição do bloco não foi concluída integralmente pelo Gemini ('.$finishReason.').');
        }

        return $transcript;
    }

    private function logFailure(Response $response, string $model, string $mimeType, int $size, float $startedAt): void
    {
        $error = $response->json('error', []);

        Log::warning('Gemini rejected the inline audio chunk transcription.', [
            'status' => $response->status(),
            'provider_code' => data_get($error, 'code'),
            'provider_status' => data_get($error, 'status'),
            'provider_message' => str(data_get($error, 'message', 'Unknown Gemini error'))->limit(500)->toString(),
            'model' => $model,
            'audio_mime_type' => $mimeType,
            'audio_size_bytes' => $size,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'request_id' => $response->header('x-request-id'),
        ]);
    }

    private function models(): array
    {
        return array_values(array_unique(array_filter([
            config('services.gemini.transcription_model'),
            config('services.gemini.transcription_fallback_model'),
        ], fn ($model) => is_string($model) && $model !== '')));
    }

    private function isTransient(Response $response): bool
    {
        return in_array($response->status(), self::TRANSIENT_STATUSES, true);
    }

    private function apiKey(): string
    {
        $key = config('services.gemini.api_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('A integração Gemini não está configurada. Informe GEMINI_API_KEY no ambiente.');
        }

        return $key;
    }

    private function prompt(): string
    {
        return 'Transcreva integralmente este trecho de áudio em português do Brasil. Retorne somente a transcrição, sem resumo, comentários, marcação Markdown ou análise clínica. Não invente palavras, não repita trechos e, quando algo estiver inaudível, escreva [inaudível].';
    }
}
