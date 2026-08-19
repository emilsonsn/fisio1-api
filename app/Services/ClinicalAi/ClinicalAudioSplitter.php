<?php

namespace App\Services\ClinicalAi;

use App\Models\ClinicalAiProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ClinicalAudioSplitter
{
    public function split(ClinicalAiProcess $process): array
    {
        $disk = Storage::disk($process->audio_disk);
        $directory = 'clinical-ai/processes/'.$process->id.'/chunks';
        $disk->makeDirectory($directory);
        $outputPattern = $disk->path($directory.'/chunk-%03d.ogg');

        $result = Process::timeout((int) config('services.clinical_ai.ffmpeg_timeout', 900))
            ->run([
                (string) config('services.clinical_ai.ffmpeg_binary', 'ffmpeg'),
                '-hide_banner', '-loglevel', 'error', '-y',
                '-i', $disk->path($process->audio_path),
                '-vn', '-ac', '1', '-ar', '16000',
                '-c:a', 'libopus', '-b:a', '24k',
                '-f', 'segment', '-segment_time', (string) config('services.clinical_ai.chunk_seconds', 300),
                '-reset_timestamps', '1', $outputPattern,
            ]);

        if ($result->failed()) {
            throw new RuntimeException('O FFmpeg não conseguiu dividir o áudio: '.str($result->errorOutput())->limit(500));
        }

        $files = collect($disk->files($directory))
            ->filter(fn (string $path) => str_ends_with($path, '.ogg'))
            ->sort()
            ->values()
            ->all();

        if ($files === []) {
            throw new RuntimeException('O FFmpeg não gerou blocos de áudio para processamento.');
        }

        return $files;
    }
}
