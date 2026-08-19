<?php

namespace Tests\Feature;

use App\Services\Gemini\GeminiAudioTranscriptionService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GeminiAudioTranscriptionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.gemini.api_key', 'test-key');
        config()->set('services.gemini.transcription_model', 'gemini-3.1-flash-lite');
        config()->set('services.gemini.transcription_fallback_model', 'gemini-3.5-flash-lite');
        config()->set('services.gemini.transcription_max_output_tokens', 4096);
        config()->set('services.gemini.inline_max_bytes', 14 * 1024 * 1024);
    }

    public function test_it_transcribes_an_inline_audio_chunk_with_generate_content(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent' => $this->successfulResponse('Paciente relata dor lombar.'),
        ]);
        $audio = UploadedFile::fake()->createWithContent('chunk.ogg', 'valid-ogg-bytes');

        $transcript = app(GeminiAudioTranscriptionService::class)->transcribe($audio);

        $this->assertSame('Paciente relata dor lombar.', $transcript);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent'
                && $request['generationConfig']['maxOutputTokens'] === 4096
                && $request['generationConfig']['thinkingConfig']['thinkingLevel'] === 'minimal'
                && base64_decode($request['contents'][0]['parts'][0]['inlineData']['data'], true) === 'valid-ogg-bytes';
        });
    }

    public function test_it_uses_the_transcription_fallback_after_a_transient_failure(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent' => Http::response([
                'error' => ['code' => 503, 'status' => 'UNAVAILABLE', 'message' => 'High demand'],
            ], 503),
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent' => $this->successfulResponse('Transcrição pelo fallback.'),
        ]);

        $transcript = app(GeminiAudioTranscriptionService::class)->transcribe(
            UploadedFile::fake()->createWithContent('chunk.ogg', 'valid-ogg-bytes')
        );

        $this->assertSame('Transcrição pelo fallback.', $transcript);
        Http::assertSentCount(2);
    }

    public function test_it_rejects_a_chunk_larger_than_the_safe_inline_limit(): void
    {
        config()->set('services.gemini.inline_max_bytes', 10);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('excede o limite inline seguro');

        app(GeminiAudioTranscriptionService::class)->transcribe(
            UploadedFile::fake()->createWithContent('chunk.ogg', 'more-than-ten-bytes')
        );

        Http::assertNothingSent();
    }

    public function test_it_rejects_a_truncated_transcription(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'finishReason' => 'MAX_TOKENS',
                    'content' => ['parts' => [['text' => 'Transcrição incompleta']]],
                ]],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('não foi concluída integralmente');

        app(GeminiAudioTranscriptionService::class)->transcribe(
            UploadedFile::fake()->createWithContent('chunk.ogg', 'valid-ogg-bytes')
        );
    }

    private function successfulResponse(string $transcript)
    {
        return Http::response([
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['parts' => [['text' => $transcript]]],
            ]],
            'usageMetadata' => [
                'promptTokenCount' => 100,
                'candidatesTokenCount' => 20,
                'totalTokenCount' => 120,
            ],
        ]);
    }
}
