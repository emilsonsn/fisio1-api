<?php

namespace App\Services\ClinicalAi;

use App\Enums\ClinicalAiChunkStatus;
use App\Enums\ClinicalAiProcessStatus;
use App\Jobs\FinalizeClinicalAiProcessJob;
use App\Models\ClinicalAiChunk;
use App\Models\ClinicalAiProcess;
use Illuminate\Support\Facades\DB;

class ClinicalAiProcessCoordinator
{
    public function chunkCompleted(ClinicalAiChunk $chunk): void
    {
        DB::transaction(function () use ($chunk): void {
            $process = ClinicalAiProcess::query()->lockForUpdate()->findOrFail($chunk->clinical_ai_process_id);
            $completed = $process->chunks()->where('status', ClinicalAiChunkStatus::Completed->value)->count();
            $process->processed_chunks = $completed;

            if ($completed === $process->chunks_count && $process->status === ClinicalAiProcessStatus::Transcribing) {
                $process->status = ClinicalAiProcessStatus::Consolidating;
                $process->save();
                FinalizeClinicalAiProcessJob::dispatch($process->id)->onQueue('clinical-ai')->afterCommit();

                return;
            }

            $process->save();
        });
    }
}
