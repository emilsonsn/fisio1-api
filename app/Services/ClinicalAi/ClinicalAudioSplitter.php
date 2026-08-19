<?php

namespace App\Services\ClinicalAi;

use App\Models\ClinicalAiProcess;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ClinicalAudioSplitter
{
    public function split(ClinicalAiProcess $process): array
    {
        $disk = Storage::disk($process->audio_disk);
        $directory = 'clinical-ai/processes/'.$process->id.'/chunks';
        $disk->deleteDirectory($directory);
        $disk->makeDirectory($directory);
        $outputPattern = $disk->path($directory.'/chunk-%03d.ogg');
        $inputPath = $disk->path($process->audio_path);
        $startedAt = microtime(true);

        $copyResult = $this->run($inputPath, $outputPattern, true);
        $files = $this->files($disk, $directory);

        if ($copyResult->successful() && $this->areValidInlineChunks($disk, $files)) {
            $this->logResult($process, 'copy', $files, $disk, $startedAt);

            return $files;
        }

        Log::info('Clinical audio requires FFmpeg transcoding before inline transcription.', [
            'process_id' => $process->id,
            'copy_exit_code' => $copyResult->exitCode(),
            'copy_error' => str($copyResult->errorOutput())->limit(500)->toString(),
            'copy_chunks_count' => count($files),
        ]);

        $disk->deleteDirectory($directory);
        $disk->makeDirectory($directory);
        $result = $this->run($inputPath, $outputPattern, false);

        if ($result->failed()) {
            throw new RuntimeException('O FFmpeg não conseguiu dividir o áudio: '.str($result->errorOutput())->limit(500));
        }

        $files = $this->files($disk, $directory);

        if ($files === []) {
            throw new RuntimeException('O FFmpeg não gerou blocos de áudio para processamento.');
        }

        if (! $this->areValidInlineChunks($disk, $files)) {
            throw new RuntimeException('O FFmpeg gerou um bloco maior que o limite inline seguro do Gemini.');
        }

        $this->logResult($process, 'transcode-opus', $files, $disk, $startedAt);

        return $files;
    }

    private function run(string $inputPath, string $outputPattern, bool $copyCodec)
    {
        $audioArguments = $copyCodec
            ? ['-c:a', 'copy']
            : ['-ac', '1', '-ar', '16000', '-c:a', 'libopus', '-b:a', '16k'];

        return Process::timeout((int) config('services.clinical_ai.ffmpeg_timeout', 900))
            ->run([
                (string) config('services.clinical_ai.ffmpeg_binary', 'ffmpeg'),
                '-hide_banner', '-loglevel', 'error', '-y',
                '-i', $inputPath,
                '-vn', ...$audioArguments,
                '-f', 'segment', '-segment_time', (string) config('services.clinical_ai.chunk_seconds', 300),
                '-reset_timestamps', '1', $outputPattern,
            ]);
    }

    private function files($disk, string $directory): array
    {
        return collect($disk->files($directory))
            ->filter(fn (string $path) => str_ends_with($path, '.ogg'))
            ->sort()
            ->values()
            ->all();
    }

    private function areValidInlineChunks($disk, array $files): bool
    {
        $maxBytes = (int) config('services.gemini.inline_max_bytes', 14 * 1024 * 1024);

        return $files !== [] && collect($files)->every(
            fn (string $path) => $disk->size($path) > 0 && $disk->size($path) <= $maxBytes
        );
    }

    private function logResult(ClinicalAiProcess $process, string $strategy, array $files, $disk, float $startedAt): void
    {
        Log::info('Clinical audio split completed.', [
            'process_id' => $process->id,
            'strategy' => $strategy,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'chunks_count' => count($files),
            'total_bytes' => collect($files)->sum(fn (string $path) => $disk->size($path)),
            'largest_chunk_bytes' => collect($files)->max(fn (string $path) => $disk->size($path)),
        ]);
    }
}
