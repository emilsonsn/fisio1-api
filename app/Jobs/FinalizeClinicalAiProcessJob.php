<?php

namespace App\Jobs;

use App\Enums\ClinicalAiProcessStatus;
use App\Enums\ClinicalRecordStatus;
use App\Models\ClinicalAiProcess;
use App\Services\ClinicalAi\ClinicalRecordFields;
use App\Services\Gemini\GeminiClinicalInteractionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class FinalizeClinicalAiProcessJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 150;

    public function __construct(public readonly int $processId) {}

    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('clinical-ai-finalize-'.$this->processId))->expireAfter(180)];
    }

    public function handle(GeminiClinicalInteractionService $gemini, ClinicalRecordFields $recordFields): void
    {
        $process = ClinicalAiProcess::query()->with(['processable', 'chunks'])->findOrFail($this->processId);

        if ($process->status !== ClinicalAiProcessStatus::Consolidating) {
            return;
        }

        $transcript = $process->chunks->pluck('transcript')->filter()->implode("\n\n");
        $result = $gemini->generateFromTranscript($transcript, $recordFields->type($process->processable));

        DB::transaction(function () use ($process, $recordFields, $result, $transcript): void {
            $process->processable->update($recordFields->onlyFor($process->processable, $result['fields']) + [
                'ai_transcript' => $transcript,
                'ai_processed_at' => now(),
                'status' => ClinicalRecordStatus::InReview,
            ]);
            $process->update(['status' => ClinicalAiProcessStatus::Completed, 'transcript' => $transcript, 'completed_at' => now(), 'error_message' => null]);
        });

        $disk = Storage::disk($process->audio_disk);
        $disk->delete($process->audio_path);
        $process->chunks->each(fn ($chunk) => Storage::disk($chunk->disk)->delete($chunk->path));
    }

    public function failed(?Throwable $exception): void
    {
        $process = ClinicalAiProcess::query()->with('processable')->find($this->processId);
        $message = str($exception?->getMessage())->limit(1000)->toString();
        $process?->update(['status' => ClinicalAiProcessStatus::Failed, 'error_message' => $message, 'failed_at' => now()]);
        $process?->processable?->update(['status' => ClinicalRecordStatus::Failed]);
    }
}
