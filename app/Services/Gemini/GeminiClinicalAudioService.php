<?php

namespace App\Services\Gemini;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiClinicalAudioService
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta';

    private const UPLOAD_URL = 'https://generativelanguage.googleapis.com/upload/v1beta/files';

    public function process(UploadedFile $audio, string $type): array
    {
        $key = config('services.gemini.api_key');

        if (! $key) {
            throw new RuntimeException('A integração Gemini não está configurada. Informe GEMINI_API_KEY no ambiente.');
        }

        $mimeType = $audio->getMimeType() ?: 'audio/webm';
        $remoteFileName = null;

        try {
            $uploadedFile = $this->uploadFile($audio, $mimeType, $key);
            $remoteFileName = data_get($uploadedFile, 'name');

            if (! is_string($remoteFileName) || $remoteFileName === '') {
                throw new RuntimeException('O Gemini não retornou a identificação do arquivo enviado.');
            }

            $uploadedFile = $this->waitUntilFileIsReady($remoteFileName, $key);
            $fileUri = data_get($uploadedFile, 'uri');

            if (! is_string($fileUri) || $fileUri === '') {
                throw new RuntimeException('O Gemini não retornou uma URI válida para o áudio enviado.');
            }

            $response = $this->createInteraction($fileUri, $mimeType, $type, $key);
            $text = $response->json('output_text');
            $result = is_string($text) ? json_decode($text, true) : null;

            if (! is_array($result) || ! is_array($result['fields'] ?? null)) {
                Log::warning('Gemini returned an invalid clinical structured response.', [
                    'model' => config('services.gemini.model'),
                    'remote_file' => $remoteFileName,
                    'response_id' => $response->json('id'),
                ]);

                throw new RuntimeException('O Gemini retornou uma resposta inválida para o prontuário.');
            }

            return [
                'transcript' => (string) ($result['transcript'] ?? ''),
                'fields' => $result['fields'],
            ];
        } catch (ConnectionException $exception) {
            Log::warning('Gemini Files API request could not be completed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'model' => config('services.gemini.model'),
                'audio_mime_type' => $mimeType,
                'audio_size_bytes' => $audio->getSize(),
            ]);

            throw new RuntimeException('Não foi possível conectar ao Gemini. Tente novamente em alguns instantes.', previous: $exception);
        } finally {
            if (is_string($remoteFileName) && $remoteFileName !== '') {
                $this->deleteFile($remoteFileName, $key);
            }
        }
    }

    private function uploadFile(UploadedFile $audio, string $mimeType, string $key): array
    {
        $start = Http::acceptJson()
            ->withHeaders([
                'x-goog-api-key' => $key,
                'X-Goog-Upload-Protocol' => 'resumable',
                'X-Goog-Upload-Command' => 'start',
                'X-Goog-Upload-Header-Content-Length' => (string) $audio->getSize(),
                'X-Goog-Upload-Header-Content-Type' => $mimeType,
            ])
            ->timeout(30)
            ->post(self::UPLOAD_URL, [
                'file' => ['display_name' => $audio->getClientOriginalName() ?: 'clinical-audio'],
            ]);

        $this->ensureSuccessful($start, 'file_upload_start', $audio);

        $uploadUrl = $start->header('x-goog-upload-url');

        if (! is_string($uploadUrl) || $uploadUrl === '') {
            throw new RuntimeException('O Gemini não retornou a URL de envio do áudio.');
        }

        $finish = Http::acceptJson()
            ->withHeaders([
                'Content-Length' => (string) $audio->getSize(),
                'X-Goog-Upload-Offset' => '0',
                'X-Goog-Upload-Command' => 'upload, finalize',
            ])
            ->withBody($audio->get(), $mimeType)
            ->timeout(180)
            ->send('POST', $uploadUrl);

        $this->ensureSuccessful($finish, 'file_upload_finalize', $audio);

        $file = $finish->json('file');

        if (! is_array($file)) {
            throw new RuntimeException('O Gemini não confirmou o envio do arquivo de áudio.');
        }

        return $file;
    }

    private function waitUntilFileIsReady(string $fileName, string $key): array
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $response = Http::acceptJson()
                ->withHeaders(['x-goog-api-key' => $key])
                ->timeout(30)
                ->get(self::API_URL.'/'.$fileName);

            $this->ensureSuccessful($response, 'file_status');

            $file = $response->json();
            $state = data_get($file, 'state');

            if ($state === 'ACTIVE') {
                return $file;
            }

            if ($state === 'FAILED') {
                Log::warning('Gemini could not process the uploaded audio file.', [
                    'remote_file' => $fileName,
                    'error' => data_get($file, 'error.message'),
                ]);

                throw new RuntimeException('O Gemini não conseguiu preparar o áudio enviado.');
            }

            if ($attempt < 10) {
                sleep(2);
            }
        }

        throw new RuntimeException('O Gemini demorou mais que o esperado para preparar o áudio. Tente novamente.');
    }

    private function createInteraction(string $fileUri, string $mimeType, string $type, string $key): Response
    {
        $fields = $this->fieldSchema($type);
        $prompt = 'Transcreva integralmente este áudio em português do Brasil e extraia exclusivamente dados clínicos explicitamente informados. Não invente informações. Para campos não citados, use string vazia; para campos numéricos ausentes, use null.';
        $payload = [
            'model' => config('services.gemini.model'),
            'input' => [
                ['type' => 'text', 'text' => $prompt],
                ['type' => str_starts_with($mimeType, 'video/') ? 'video' : 'audio', 'uri' => $fileUri, 'mime_type' => $mimeType],
            ],
            'response_format' => [
                'type' => 'object',
                'properties' => [
                    'transcript' => ['type' => 'string'],
                    'fields' => ['type' => 'object', 'properties' => $fields],
                ],
                'required' => ['transcript', 'fields'],
            ],
        ];

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $response = Http::acceptJson()
                ->withHeaders(['x-goog-api-key' => $key])
                ->timeout(180)
                ->post(self::API_URL.'/interactions', $payload);

            if (! in_array($response->status(), [429, 500, 502, 503, 504], true) || $attempt === 3) {
                break;
            }

            Log::info('Retrying transient Gemini interaction failure.', [
                'attempt' => $attempt,
                'status' => $response->status(),
                'model' => config('services.gemini.model'),
            ]);

            sleep($attempt * 2);
        }

        $this->ensureSuccessful($response, 'interaction');

        return $response;
    }

    private function deleteFile(string $fileName, string $key): void
    {
        try {
            $response = Http::acceptJson()
                ->withHeaders(['x-goog-api-key' => $key])
                ->timeout(30)
                ->delete(self::API_URL.'/'.$fileName);

            if ($response->failed()) {
                Log::warning('Gemini file could not be deleted after processing.', [
                    'remote_file' => $fileName,
                    'status' => $response->status(),
                    'provider_message' => str(data_get($response->json('error', []), 'message', 'Unknown Gemini error'))->limit(500)->toString(),
                ]);
            }
        } catch (ConnectionException $exception) {
            Log::warning('Gemini file deletion request could not be completed.', [
                'remote_file' => $fileName,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function ensureSuccessful(Response $response, string $operation, ?UploadedFile $audio = null): void
    {
        if (! $response->failed()) {
            return;
        }

        $error = $response->json('error', []);

        Log::warning('Gemini Files API rejected the clinical audio request.', [
            'operation' => $operation,
            'status' => $response->status(),
            'provider_code' => data_get($error, 'code'),
            'provider_status' => data_get($error, 'status'),
            'provider_message' => str(data_get($error, 'message', 'Unknown Gemini error'))->limit(500)->toString(),
            'model' => config('services.gemini.model'),
            'audio_mime_type' => $audio?->getMimeType(),
            'audio_size_bytes' => $audio?->getSize(),
            'request_id' => $response->header('x-request-id'),
        ]);

        throw new RuntimeException('O Gemini recusou o processamento do áudio (HTTP '.$response->status().'). Consulte o log do servidor para o código retornado.');
    }

    private function fieldSchema(string $type): array
    {
        $stringFields = $type === 'evolution'
            ? ['daily_complaint', 'home_guidance_adherence', 'therapeutic_conduct', 'session_final_impression', 'observations']
            : ['indication', 'birthplace', 'marital_status', 'gender', 'profession', 'address', 'chief_complaint', 'condition_history', 'life_habits', 'personal_family_history', 'previous_treatments', 'physical_examination', 'complementary_exams', 'physical_therapy_diagnosis', 'cbdf', 'resources_methods_techniques', 'therapeutic_objectives', 'physical_therapy_prognosis'];

        $schema = [];

        foreach ($stringFields as $field) {
            $schema[$field] = ['type' => 'string'];
        }

        if ($type === 'evolution') {
            $schema['pain_level'] = ['type' => 'integer', 'nullable' => true, 'minimum' => 0, 'maximum' => 10];
        } else {
            $schema['planned_sessions'] = ['type' => 'integer', 'nullable' => true, 'minimum' => 0];
        }

        return $schema;
    }
}
