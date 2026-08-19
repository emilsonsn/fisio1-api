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
                                'transcript' => 'Paciente relata dor lombar.',
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

        $this->assertSame('Paciente relata dor lombar.', $result['transcript']);
        $this->assertSame('Dor lombar', $result['fields']['daily_complaint']);
        $this->assertSame(6, $result['fields']['pain_level']);

        Http::assertSent(fn ($request) => $request['model'] === 'gemini-3.5-flash-lite'
            && str_contains($request['input'][0]['text'], 'Paciente relata dor lombar com intensidade seis.'));
    }
}
