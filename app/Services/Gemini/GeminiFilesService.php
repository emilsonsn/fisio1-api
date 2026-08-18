<?php

namespace App\Services\Gemini;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GeminiFilesService
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta';

    private const UPLOAD_URL = 'https://generativelanguage.googleapis.com/upload/v1beta/files';

    public function upload(UploadedFile $audio): array
    {
        $mimeType = $audio->getMimeType() ?: 'audio/webm';
        $key = $this->apiKey();

        try {
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

            $this->throwIfFailed($start, 'file_upload_start', $audio);

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

            $this->throwIfFailed($finish, 'file_upload_finalize', $audio);

            $file = $finish->json('file');

            if (! is_array($file) || ! is_string(data_get($file, 'name'))) {
                throw new RuntimeException('O Gemini não confirmou o envio do arquivo de áudio.');
            }

            try {
                return $this->waitUntilActive($file);
            } catch (Throwable $exception) {
                $this->delete($file);

                throw $exception;
            }
        } catch (ConnectionException $exception) {
            Log::warning('Gemini Files API request could not be completed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'audio_mime_type' => $mimeType,
                'audio_size_bytes' => $audio->getSize(),
            ]);

            throw new RuntimeException('Não foi possível conectar ao Gemini. Tente novamente em alguns instantes.', previous: $exception);
        }
    }

    public function delete(array $file): void
    {
        $name = data_get($file, 'name');

        if (! is_string($name) || $name === '') {
            return;
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['x-goog-api-key' => $this->apiKey()])
                ->timeout(30)
                ->delete(self::API_URL.'/'.$name);

            if ($response->failed()) {
                Log::warning('Gemini file could not be deleted after processing.', $this->errorContext($response, 'file_delete') + [
                    'remote_file' => $name,
                ]);
            }
        } catch (ConnectionException $exception) {
            Log::warning('Gemini file deletion request could not be completed.', [
                'remote_file' => $name,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function waitUntilActive(array $file): array
    {
        $name = $file['name'];

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $response = Http::acceptJson()
                ->withHeaders(['x-goog-api-key' => $this->apiKey()])
                ->timeout(30)
                ->get(self::API_URL.'/'.$name);

            $this->throwIfFailed($response, 'file_status');
            $file = $response->json();

            if (data_get($file, 'state') === 'ACTIVE') {
                return $file;
            }

            if (data_get($file, 'state') === 'FAILED') {
                Log::warning('Gemini could not process the uploaded audio file.', [
                    'remote_file' => $name,
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

    private function apiKey(): string
    {
        $key = config('services.gemini.api_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('A integração Gemini não está configurada. Informe GEMINI_API_KEY no ambiente.');
        }

        return $key;
    }

    private function throwIfFailed(Response $response, string $operation, ?UploadedFile $audio = null): void
    {
        if (! $response->failed()) {
            return;
        }

        Log::warning('Gemini Files API rejected the clinical audio request.', $this->errorContext($response, $operation) + [
            'audio_mime_type' => $audio?->getMimeType(),
            'audio_size_bytes' => $audio?->getSize(),
        ]);

        throw new RuntimeException('O Gemini recusou o processamento do áudio (HTTP '.$response->status().'). Consulte o log do servidor para o código retornado.');
    }

    private function errorContext(Response $response, string $operation): array
    {
        $error = $response->json('error', []);

        return [
            'operation' => $operation,
            'status' => $response->status(),
            'provider_code' => data_get($error, 'code'),
            'provider_status' => data_get($error, 'status'),
            'provider_message' => str(data_get($error, 'message', 'Unknown Gemini error'))->limit(500)->toString(),
            'request_id' => $response->header('x-request-id'),
        ];
    }
}
