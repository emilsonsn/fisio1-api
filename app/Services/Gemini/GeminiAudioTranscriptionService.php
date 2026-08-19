<?php

namespace App\Services\Gemini;

use Illuminate\Http\UploadedFile;

class GeminiAudioTranscriptionService
{
    public function __construct(
        private readonly GeminiFilesService $files,
        private readonly GeminiClinicalInteractionService $interactions,
    ) {}

    public function transcribe(UploadedFile $audio): string
    {
        $remoteFile = $this->files->upload($audio);

        try {
            return $this->interactions->transcribe($remoteFile, $audio->getMimeType() ?: 'audio/ogg');
        } finally {
            $this->files->delete($remoteFile);
        }
    }
}
