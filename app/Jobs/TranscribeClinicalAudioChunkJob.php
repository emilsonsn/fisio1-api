<?php

namespace App\Jobs;

use App\Enums\ClinicalAiChunkStatus;
use App\Enums\ClinicalAiProcessStatus;
use App\Enums\ClinicalRecordStatus;
use App\Models\ClinicalAiChunk;
use App\Services\ClinicalAi\ClinicalAiProcessCoordinator;
use App\Services\Gemini\GeminiAudioTranscriptionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TranscribeClinicalAudioChunkJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 600;

    public function __construct(public readonly int $chunkId) {}

    public function backoff(): array
    {
        return [60, 180, 600];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('clinical-ai-chunk-'.$this->chunkId))->expireAfter(900)];
    }

    public function handle(GeminiAudioTranscriptionService $gemini, ClinicalAiProcessCoordinator $coordinator): void
    {
        $chunk = ClinicalAiChunk::query()->with('process')->findOrFail($this->chunkId);

        if ($chunk->status === ClinicalAiChunkStatus::Completed || $chunk->process->status !== ClinicalAiProcessStatus::Transcribing) {
            return;
        }

        $chunk->update(['status' => ClinicalAiChunkStatus::Processing, 'attempts' => $chunk->attempts + 1, 'error_message' => null]);
        $disk = Storage::disk($chunk->disk);
        $audio = new UploadedFile($disk->path($chunk->path), basename($chunk->path), $chunk->mime_type, null, true);
        $transcript = $gemini->transcribe($audio);
        $chunk->update(['status' => ClinicalAiChunkStatus::Completed, 'transcript' => $transcript]);
        $coordinator->chunkCompleted($chunk);
    }

    public function failed(?Throwable $exception): void
    {
        $chunk = ClinicalAiChunk::query()->with('process.processable')->find($this->chunkId);
        $message = str($exception?->getMessage())->limit(1000)->toString();
        $chunk?->update(['status' => ClinicalAiChunkStatus::Failed, 'error_message' => $message]);
        $chunk?->process?->update(['status' => ClinicalAiProcessStatus::Failed, 'error_message' => $message, 'failed_at' => now()]);
        $chunk?->process?->processable?->update(['status' => ClinicalRecordStatus::Failed]);
    }
}
