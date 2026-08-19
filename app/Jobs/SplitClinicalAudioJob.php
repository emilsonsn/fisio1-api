<?php

namespace App\Jobs;

use App\Enums\ClinicalAiChunkStatus;
use App\Enums\ClinicalAiProcessStatus;
use App\Enums\ClinicalRecordStatus;
use App\Models\ClinicalAiProcess;
use App\Services\ClinicalAi\ClinicalAudioSplitter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Throwable;

class SplitClinicalAudioJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 150;

    public function __construct(public readonly int $processId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('clinical-ai-split-'.$this->processId))->expireAfter(180)];
    }

    public function handle(ClinicalAudioSplitter $splitter): void
    {
        $process = ClinicalAiProcess::query()->findOrFail($this->processId);

        if (! in_array($process->status, [ClinicalAiProcessStatus::Pending, ClinicalAiProcessStatus::Splitting, ClinicalAiProcessStatus::Failed], true)) {
            return;
        }

        $process->update(['status' => ClinicalAiProcessStatus::Splitting, 'started_at' => $process->started_at ?? now(), 'error_message' => null, 'failed_at' => null]);
        $files = $splitter->split($process);

        DB::transaction(function () use ($process, $files): void {
            $process->chunks()->delete();

            foreach ($files as $sequence => $path) {
                $process->chunks()->create([
                    'sequence' => $sequence,
                    'status' => ClinicalAiChunkStatus::Pending,
                    'disk' => $process->audio_disk,
                    'path' => $path,
                    'mime_type' => 'audio/ogg',
                ]);
            }

            $process->update(['status' => ClinicalAiProcessStatus::Transcribing, 'chunks_count' => count($files), 'processed_chunks' => 0]);
        });

        $process->chunks()->each(fn ($chunk) => TranscribeClinicalAudioChunkJob::dispatch($chunk->id)->onQueue('clinical-ai'));
    }

    public function failed(?Throwable $exception): void
    {
        $process = ClinicalAiProcess::query()->find($this->processId);
        $process?->update(['status' => ClinicalAiProcessStatus::Failed, 'error_message' => str($exception?->getMessage())->limit(1000), 'failed_at' => now()]);
        $process?->processable?->update(['status' => ClinicalRecordStatus::Failed]);
    }
}
