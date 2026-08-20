<?php

namespace App\Services\ClinicalRecords;

use App\Enums\ClinicalAiChunkStatus;
use App\Enums\ClinicalAiProcessStatus;
use App\Enums\ClinicalRecordStatus;
use App\Models\ClinicalAiProcess;
use App\Models\PatientAssessment;
use App\Models\PatientEvolution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CancelClinicalRecord
{
    public function handle(
        PatientAssessment|PatientEvolution $record,
        User $user,
        ?string $reason = null,
    ): PatientAssessment|PatientEvolution {
        $processToClean = null;

        $record = DB::transaction(function () use ($record, $user, $reason, &$processToClean) {
            $record = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());
            abort_if($record->status === ClinicalRecordStatus::Cancelled, 422, 'Este registro já está cancelado.');

            $process = $record->aiProcess()->lockForUpdate()->first();
            if ($process && $process->status !== ClinicalAiProcessStatus::Completed) {
                $process->update([
                    'status' => ClinicalAiProcessStatus::Cancelled,
                    'error_message' => null,
                    'failed_at' => null,
                ]);
                $process->chunks()
                    ->where('status', '!=', ClinicalAiChunkStatus::Completed->value)
                    ->update(['status' => ClinicalAiChunkStatus::Cancelled->value, 'error_message' => null]);
                $processToClean = $process->load('chunks');
            }

            $record->update([
                'status' => ClinicalRecordStatus::Cancelled,
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancellation_reason' => filled($reason) ? trim($reason) : null,
            ]);

            return $record;
        });

        if ($processToClean instanceof ClinicalAiProcess) {
            Storage::disk($processToClean->audio_disk)->delete($processToClean->audio_path);
            $processToClean->chunks->each(
                fn ($chunk) => Storage::disk($chunk->disk)->delete($chunk->path),
            );
        }

        return $record;
    }
}
