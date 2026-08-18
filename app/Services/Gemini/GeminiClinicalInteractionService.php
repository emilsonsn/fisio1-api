<?php

namespace App\Services\Gemini;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiClinicalInteractionService
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta';

    private const TRANSIENT_STATUSES = [429, 500, 502, 503, 504];

    public function generate(array $file, string $mimeType, string $type): array
    {
        $uri = data_get($file, 'uri');

        if (! is_string($uri) || $uri === '') {
            throw new RuntimeException('O Gemini não retornou uma URI válida para o áudio enviado.');
        }

        try {
            foreach ($this->models() as $index => $model) {
                $response = $this->request($uri, $mimeType, $type, $model);

                if (! $response->failed()) {
                    return $this->parse($response, $model, data_get($file, 'name'));
                }

                if ($index === 0 && $this->isTransient($response)) {
                    Log::warning('Primary Gemini model failed transiently; trying configured fallback.', [
                        'primary_model' => $model,
                        'fallback_model' => config('services.gemini.fallback_model'),
                        'status' => $response->status(),
                    ]);

                    continue;
                }

                $this->throwIfFailed($response, $model);
            }
        } catch (ConnectionException $exception) {
            Log::warning('Gemini interaction request could not be completed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException('Não foi possível conectar ao Gemini. Tente novamente em alguns instantes.', previous: $exception);
        }

        throw new RuntimeException('Não foi possível processar o áudio com Gemini.');
    }

    private function request(string $uri, string $mimeType, string $type, string $model): Response
    {
        $payload = [
            'model' => $model,
            'input' => [
                ['type' => 'text', 'text' => $this->prompt()],
                ['type' => str_starts_with($mimeType, 'video/') ? 'video' : 'audio', 'uri' => $uri, 'mime_type' => $mimeType],
            ],
            'response_format' => $this->responseFormat($type),
        ];

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $response = Http::acceptJson()
                ->withHeaders(['x-goog-api-key' => $this->apiKey()])
                ->timeout(180)
                ->post(self::API_URL.'/interactions', $payload);

            if (! $this->isTransient($response) || $attempt === 3) {
                return $response;
            }

            Log::info('Retrying transient Gemini interaction failure.', [
                'attempt' => $attempt,
                'status' => $response->status(),
                'model' => $model,
            ]);

            sleep($attempt * 2);
        }
    }

    private function parse(Response $response, string $model, mixed $remoteFile): array
    {
        $result = json_decode((string) $response->json('output_text'), true);

        if (! is_array($result) || ! is_array($result['fields'] ?? null)) {
            Log::warning('Gemini returned an invalid clinical structured response.', [
                'model' => $model,
                'remote_file' => $remoteFile,
                'response_id' => $response->json('id'),
            ]);

            throw new RuntimeException('O Gemini retornou uma resposta inválida para o prontuário.');
        }

        return [
            'transcript' => (string) ($result['transcript'] ?? ''),
            'fields' => $result['fields'],
        ];
    }

    private function models(): array
    {
        return array_values(array_unique(array_filter([
            config('services.gemini.model'),
            config('services.gemini.fallback_model'),
        ], fn ($model) => is_string($model) && $model !== '')));
    }

    private function isTransient(Response $response): bool
    {
        return in_array($response->status(), self::TRANSIENT_STATUSES, true);
    }

    private function throwIfFailed(Response $response, string $model): void
    {
        $error = $response->json('error', []);

        Log::warning('Gemini interaction rejected the clinical audio request.', [
            'status' => $response->status(),
            'provider_code' => data_get($error, 'code'),
            'provider_status' => data_get($error, 'status'),
            'provider_message' => str(data_get($error, 'message', 'Unknown Gemini error'))->limit(500)->toString(),
            'model' => $model,
            'request_id' => $response->header('x-request-id'),
        ]);

        throw new RuntimeException('O Gemini recusou o processamento do áudio (HTTP '.$response->status().'). Consulte o log do servidor para o código retornado.');
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
        return 'Transcreva integralmente este áudio em português do Brasil e extraia exclusivamente dados clínicos explicitamente informados. Não invente informações. Para campos não citados, use string vazia; para campos numéricos ausentes, use null.';
    }

    private function responseFormat(string $type): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'transcript' => ['type' => 'string'],
                'fields' => ['type' => 'object', 'properties' => $this->fieldSchema($type)],
            ],
            'required' => ['transcript', 'fields'],
        ];
    }

    private function fieldSchema(string $type): array
    {
        $stringFields = $type === 'evolution'
            ? ['daily_complaint', 'home_guidance_adherence', 'therapeutic_conduct', 'session_final_impression', 'observations']
            : ['indication', 'birthplace', 'marital_status', 'gender', 'profession', 'address', 'chief_complaint', 'condition_history', 'life_habits', 'personal_family_history', 'previous_treatments', 'physical_examination', 'complementary_exams', 'physical_therapy_diagnosis', 'cbdf', 'resources_methods_techniques', 'therapeutic_objectives', 'physical_therapy_prognosis'];

        $schema = array_fill_keys($stringFields, ['type' => 'string']);

        $schema[$type === 'evolution' ? 'pain_level' : 'planned_sessions'] = [
            'type' => 'integer',
            'nullable' => true,
            'minimum' => 0,
        ];

        if ($type === 'evolution') {
            $schema['pain_level']['maximum'] = 10;
        }

        return $schema;
    }
}
