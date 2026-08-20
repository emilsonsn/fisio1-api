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

    public function __construct(private readonly ClinicalExtractionProtocol $protocol) {}

    public function generateFromTranscript(string $transcript, string $type): array
    {
        if (trim($transcript) === '') {
            throw new RuntimeException('Não há transcrição para organizar no prontuário.');
        }

        try {
            foreach ($this->models() as $index => $model) {
                $response = $this->requestPayload([
                    'model' => $model,
                    'input' => [[
                        'type' => 'text',
                        'text' => $this->protocol->prompt($type)."\n\nTRANSCRIÇÃO INTEGRAL DO ATENDIMENTO\n".$transcript,
                    ]],
                    'response_format' => $this->responseFormat($type),
                    'generation_config' => [
                        'max_output_tokens' => (int) config('services.gemini.extraction_max_output_tokens', 8192),
                        'temperature' => (float) config('services.gemini.extraction_temperature', 0.2),
                        'thinking_level' => 'low',
                    ],
                    'store' => false,
                ], $model);

                if ($response->successful()) {
                    return $this->parse($response, $model, $type);
                }

                if ($index === 0 && $this->isTransient($response)) {
                    Log::warning('Primary Gemini extraction model failed transiently; trying fallback.', [
                        'primary_model' => $model,
                        'fallback_model' => config('services.gemini.extraction_fallback_model'),
                        'status' => $response->status(),
                    ]);

                    continue;
                }

                $this->throwIfFailed($response, $model);
            }
        } catch (ConnectionException $exception) {
            Log::warning('Gemini clinical extraction request could not be completed.', [
                'exception' => $exception::class,
                'message' => str($exception->getMessage())->limit(500)->toString(),
            ]);

            throw new RuntimeException('Não foi possível conectar ao Gemini para organizar o prontuário.', previous: $exception);
        }

        throw new RuntimeException('Não foi possível organizar a transcrição com Gemini.');
    }

    private function requestPayload(array $payload, string $model): Response
    {
        $startedAt = microtime(true);
        $response = Http::acceptJson()
            ->withHeaders(['x-goog-api-key' => $this->apiKey()])
            ->connectTimeout(10)
            ->timeout((int) config('services.gemini.request_timeout', 60))
            ->post(self::API_URL.'/interactions', $payload);

        Log::info('Gemini clinical extraction request completed.', [
            'model' => $model,
            'status' => $response->status(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'request_id' => $response->header('x-request-id'),
        ]);

        return $response;
    }

    private function parse(Response $response, string $model, string $type): array
    {
        $result = json_decode($this->outputText($response), true);

        if (! is_array($result) || ! is_array($result['fields'] ?? null)) {
            Log::warning('Gemini returned an invalid clinical structured response.', [
                'model' => $model,
                'response_id' => $response->json('id'),
            ]);

            throw new RuntimeException('O Gemini retornou uma resposta inválida para o prontuário.');
        }

        return ['fields' => $this->normalizeFields($result['fields'], $type)];
    }

    private function outputText(Response $response): string
    {
        $outputText = $response->json('output_text');

        if (is_string($outputText) && $outputText !== '') {
            return $outputText;
        }

        return collect($response->json('steps', []))
            ->where('type', 'model_output')
            ->flatMap(fn (array $step) => data_get($step, 'content', []))
            ->filter(fn (array $content) => data_get($content, 'type') === 'text')
            ->pluck('text')
            ->filter(fn ($text) => is_string($text))
            ->implode('');
    }

    private function models(): array
    {
        return array_values(array_unique(array_filter([
            config('services.gemini.extraction_model'),
            config('services.gemini.extraction_fallback_model'),
        ], fn ($model) => is_string($model) && $model !== '')));
    }

    private function isTransient(Response $response): bool
    {
        return in_array($response->status(), self::TRANSIENT_STATUSES, true);
    }

    private function throwIfFailed(Response $response, string $model): void
    {
        $error = $response->json('error', []);

        Log::warning('Gemini rejected the clinical extraction request.', [
            'status' => $response->status(),
            'provider_code' => data_get($error, 'code'),
            'provider_status' => data_get($error, 'status'),
            'provider_message' => str(data_get($error, 'message', 'Unknown Gemini error'))->limit(500)->toString(),
            'model' => $model,
            'request_id' => $response->header('x-request-id'),
        ]);

        throw new RuntimeException('O Gemini recusou a organização do prontuário (HTTP '.$response->status().'). Consulte o log do servidor.');
    }

    private function apiKey(): string
    {
        $key = config('services.gemini.api_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('A integração Gemini não está configurada. Informe GEMINI_API_KEY no ambiente.');
        }

        return $key;
    }

    private function responseFormat(string $type): array
    {
        $fieldSchema = $this->fieldSchema($type);

        return [
            'type' => 'object',
            'properties' => [
                'fields' => [
                    'type' => 'object',
                    'properties' => $fieldSchema,
                    'required' => array_keys($fieldSchema),
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['fields'],
            'additionalProperties' => false,
        ];
    }

    private function fieldSchema(string $type): array
    {
        return collect($this->protocol->fieldDefinitions($type))
            ->mapWithKeys(function (string $description, string $field): array {
                if ($field === 'pain_level') {
                    return [$field => ['type' => 'integer', 'nullable' => true, 'minimum' => 0, 'maximum' => 10, 'description' => $description]];
                }

                if ($field === 'planned_sessions') {
                    return [$field => ['type' => 'integer', 'nullable' => true, 'minimum' => 1, 'description' => $description]];
                }

                return [$field => ['type' => 'string', 'description' => $description]];
            })
            ->all();
    }

    private function normalizeFields(array $fields, string $type): array
    {
        return collect(array_keys($this->protocol->fieldDefinitions($type)))
            ->mapWithKeys(function (string $field) use ($fields, $type): array {
                $value = $fields[$field] ?? null;

                if (in_array($field, ['pain_level', 'planned_sessions'], true)) {
                    return [$field => is_numeric($value) ? (int) $value : null];
                }

                $value = is_string($value) ? trim($value) : '';

                return [$field => $value !== '' ? $value : $this->protocol->missingValue($type, $field)];
            })
            ->all();
    }
}
