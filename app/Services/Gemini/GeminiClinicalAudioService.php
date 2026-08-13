<?php

namespace App\Services\Gemini;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiClinicalAudioService
{
    public function process(UploadedFile $audio, string $type): array
    {
        $key = config('services.gemini.api_key');
        if (! $key) {
            throw new RuntimeException('A integração Gemini não está configurada. Informe GEMINI_API_KEY no ambiente.');
        }
        $fields = $type === 'evolution' ? 'daily_complaint, pain_level, home_guidance_adherence, therapeutic_conduct, session_final_impression, observations' : 'indication, birthplace, marital_status, gender, profession, address, chief_complaint, condition_history, life_habits, personal_family_history, previous_treatments, physical_examination, complementary_exams, physical_therapy_diagnosis, cbdf, planned_sessions, resources_methods_techniques, therapeutic_objectives, physical_therapy_prognosis';
        $prompt = "Transcreva integralmente este áudio em português do Brasil e extraia exclusivamente dados clínicos explicitamente informados. Não invente informações. Responda APENAS JSON válido no formato {\"transcript\":\"...\",\"fields\":{...}}. Os únicos campos permitidos são: {$fields}. Para ausências use string vazia ou null. pain_level deve ser inteiro de 0 a 10; planned_sessions deve ser inteiro ou null.";
        $payload = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => ['mime_type' => $audio->getMimeType() ?: 'audio/webm', 'data' => base64_encode($audio->get())]],
                ],
            ]],
            'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1],
        ];
        $response = Http::acceptJson()->withHeaders(['x-goog-api-key' => $key])->timeout(180)->post('https://generativelanguage.googleapis.com/v1beta/models/'.config('services.gemini.model').':generateContent', $payload);
        if ($response->failed()) {
            throw new RuntimeException('Não foi possível processar o áudio com Gemini.');
        }
        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');
        $result = is_string($text) ? json_decode($text, true) : null;
        if (! is_array($result) || ! is_array($result['fields'] ?? null)) {
            throw new RuntimeException('O Gemini retornou uma resposta inválida para o prontuário.');
        }

        return ['transcript' => (string) ($result['transcript'] ?? ''), 'fields' => $result['fields']];
    }
}
