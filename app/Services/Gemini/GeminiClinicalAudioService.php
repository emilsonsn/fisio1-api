<?php

namespace App\Services\Gemini;

use Illuminate\Http\UploadedFile;

class GeminiClinicalAudioService
{
    public function __construct(
        private readonly GeminiFilesService $files,
        private readonly GeminiClinicalInteractionService $interactions,
    ) {}

    public function process(UploadedFile $audio, string $type): array
    {
        $file = $this->files->upload($audio);

        try {
            return $this->interactions->generate($file, $audio->getMimeType() ?: 'audio/webm', $type);
        } finally {
            $this->files->delete($file);
        }
    }
}
