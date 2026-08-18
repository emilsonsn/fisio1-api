<?php

namespace Tests\Feature;

use App\Services\Gemini\GeminiClinicalAudioService;
use App\Services\Gemini\GeminiClinicalInteractionService;
use App\Services\Gemini\GeminiFilesService;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;

class GeminiClinicalAudioServiceTest extends TestCase
{
    public function test_it_deletes_the_remote_file_after_a_successful_clinical_interaction(): void
    {
        $files = new class extends GeminiFilesService
        {
            public array $deleted = [];

            public function upload(UploadedFile $audio): array
            {
                return ['name' => 'files/clinical-audio', 'uri' => 'https://example.test/audio'];
            }

            public function delete(array $file): void
            {
                $this->deleted[] = $file;
            }
        };
        $interactions = new class extends GeminiClinicalInteractionService
        {
            public function generate(array $file, string $mimeType, string $type): array
            {
                return ['transcript' => 'Texto transcrito', 'fields' => ['chief_complaint' => 'Dor lombar']];
            }
        };

        $service = new GeminiClinicalAudioService($files, $interactions);
        $result = $service->process(UploadedFile::fake()->create('audio.ogg', 1, 'audio/ogg'), 'initial_assessment');

        $this->assertSame('Texto transcrito', $result['transcript']);
        $this->assertCount(1, $files->deleted);
        $this->assertSame('files/clinical-audio', $files->deleted[0]['name']);
    }

    public function test_it_deletes_the_remote_file_when_the_clinical_interaction_fails(): void
    {
        $files = new class extends GeminiFilesService
        {
            public bool $deleted = false;

            public function upload(UploadedFile $audio): array
            {
                return ['name' => 'files/clinical-audio', 'uri' => 'https://example.test/audio'];
            }

            public function delete(array $file): void
            {
                $this->deleted = true;
            }
        };
        $interactions = new class extends GeminiClinicalInteractionService
        {
            public function generate(array $file, string $mimeType, string $type): array
            {
                throw new RuntimeException('Provider unavailable');
            }
        };

        $service = new GeminiClinicalAudioService($files, $interactions);

        try {
            $service->process(UploadedFile::fake()->create('audio.ogg', 1, 'audio/ogg'), 'initial_assessment');
            $this->fail('The provider exception should be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Provider unavailable', $exception->getMessage());
        }

        $this->assertTrue($files->deleted);
    }
}
