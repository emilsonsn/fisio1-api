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
                        'text' => $this->prompt()."\n\nTranscrição integral do atendimento:\n".$transcript,
                    ]],
                    'response_format' => $this->responseFormat($type),
                ], $model);

                if ($response->successful()) {
                    return $this->parse($response, $model);
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

    private function parse(Response $response, string $model): array
    {
        $result = json_decode($this->outputText($response), true);

        if (! is_array($result) || ! is_array($result['fields'] ?? null)) {
            Log::warning('Gemini returned an invalid clinical structured response.', [
                'model' => $model,
                'response_id' => $response->json('id'),
            ]);

            throw new RuntimeException('O Gemini retornou uma resposta inválida para o prontuário.');
        }

        return [
            'transcript' => (string) ($result['transcript'] ?? ''),
            'fields' => $result['fields'],
        ];
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

    private function prompt(): string
    {
        return 'Extraia exclusivamente os dados clínicos explicitamente informados na transcrição. Não invente informações. Para campos não citados, use string vazia; para campos numéricos ausentes, use null.';
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
