<?php

namespace Tests\Feature;

use App\Services\Gemini\GeminiClinicalInteractionService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiClinicalInteractionServiceTest extends TestCase
{
    public function test_it_parses_text_from_the_rest_interaction_steps(): void
    {
        config()->set('services.gemini.api_key', 'test-key');
        config()->set('services.gemini.extraction_model', 'gemini-3.5-flash-lite');
        config()->set('services.gemini.extraction_fallback_model', 'gemini-3.6-flash');
        config()->set('services.gemini.extraction_max_output_tokens', 8192);
        config()->set('services.gemini.extraction_temperature', 0.2);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/interactions' => Http::response([
                'id' => 'interaction-test',
                'status' => 'completed',
                'steps' => [
                    ['type' => 'thought', 'signature' => 'ignored'],
                    [
                        'type' => 'model_output',
                        'content' => [[
                            'type' => 'text',
                            'text' => json_encode([
                                'fields' => ['daily_complaint' => 'Dor lombar', 'pain_level' => 6],
                            ]),
                        ]],
                    ],
                ],
            ]),
        ]);

        $result = app(GeminiClinicalInteractionService::class)->generateFromTranscript(
            'Paciente relata dor lombar com intensidade seis.',
            'evolution',
        );

        $this->assertSame('Dor lombar', $result['fields']['daily_complaint']);
        $this->assertSame(6, $result['fields']['pain_level']);
        $this->assertSame('Não relatado.', $result['fields']['therapeutic_conduct']);
        $this->assertSame('Não avaliado.', $result['fields']['observations']);

        Http::assertSent(fn ($request) => $request['model'] === 'gemini-3.5-flash-lite'
            && str_contains($request['input'][0]['text'], 'Fisioterapeuta Especialista')
            && str_contains($request['input'][0]['text'], 'Paciente relata dor lombar com intensidade seis.')
            && $request['generation_config']['max_output_tokens'] === 8192
            && $request['generation_config']['temperature'] === 0.2
            && $request['generation_config']['thinking_level'] === 'low'
            && $request['store'] === false
            && ! array_key_exists('transcript', $request['response_format']['properties'])
            && $request['response_format']['properties']['fields']['required'] === [
                'daily_complaint',
                'pain_level',
                'home_guidance_adherence',
                'therapeutic_conduct',
                'session_final_impression',
                'observations',
            ]);
    }
}
