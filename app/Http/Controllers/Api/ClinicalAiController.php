<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditEventCategory;
use App\Enums\ClinicalAiChunkStatus;
use App\Enums\ClinicalAiProcessStatus;
use App\Enums\ClinicalRecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClinicalAi\ProcessClinicalAudioRequest;
use App\Http\Resources\PatientAssessmentResource;
use App\Http\Resources\PatientEvolutionResource;
use App\Jobs\FinalizeClinicalAiProcessJob;
use App\Jobs\SplitClinicalAudioJob;
use App\Jobs\TranscribeClinicalAudioChunkJob;
use App\Models\ClinicalAiProcess;
use App\Models\Patient;
use App\Models\PatientAssessment;
use App\Models\PatientEvolution;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ClinicalAiController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function processAudio(ProcessClinicalAudioRequest $request): JsonResponse
    {
        $audio = $request->file('audio');
        $path = $audio->store('clinical-ai/audio', 'local');

        try {
            $record = DB::transaction(function () use ($request, $audio, $path) {
                $attributes = [
                    'patient_id' => $request->integer('patient_id'),
                    'professional_id' => $request->user()->id,
                    'status' => ClinicalRecordStatus::Pending,
                ];

                $record = $request->string('type')->toString() === 'evolution'
                    ? PatientEvolution::create($attributes + ['evolved_at' => $request->date('performed_at')])
                    : PatientAssessment::create([
                        ...$attributes,
                        ...Patient::findOrFail($attributes['patient_id'])->only(Patient::DEMOGRAPHIC_FIELDS),
                        'assessed_at' => $request->date('performed_at'),
                    ]);

                $process = $record->aiProcess()->create([
                    'status' => ClinicalAiProcessStatus::Pending,
                    'audio_disk' => 'local',
                    'audio_path' => $path,
                    'original_name' => $audio->getClientOriginalName() ?: basename($path),
                    'mime_type' => $audio->getMimeType() ?: 'application/octet-stream',
                    'size' => $audio->getSize(),
                ]);

                SplitClinicalAudioJob::dispatch($process->id)->onQueue('clinical-ai')->afterCommit();

                return $record;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        $record->load(['patient', 'professional', 'attachments', 'aiProcess']);
        $this->audit->record(AuditEventCategory::AiProcessingStarted, $record, metadata: [
            'process_id' => $record->aiProcess?->id,
            'record_type' => $request->string('type')->toString(),
            'audio_mime_type' => $audio->getMimeType(),
            'audio_size' => $audio->getSize(),
        ]);
        $resource = $record instanceof PatientEvolution
            ? new PatientEvolutionResource($record)
            : new PatientAssessmentResource($record);

        return $resource->response()->setStatusCode(202);
    }

    public function retry(Request $request, ClinicalAiProcess $process): JsonResponse
    {
        $process->load(['processable', 'chunks']);
        abort_unless($process->processable && ($request->user()->hasPermission('clinical_records.manage_all') || $process->processable->professional_id === $request->user()->id), 403);
        abort_unless($process->status === ClinicalAiProcessStatus::Failed, 422, 'Somente processamentos com falha podem ser retomados.');

        DB::transaction(function () use ($process): void {
            $process->processable->update(['status' => ClinicalRecordStatus::Pending]);
            $process->update(['error_message' => null, 'failed_at' => null]);
            $unfinishedChunks = $process->chunks->where('status', '!=', ClinicalAiChunkStatus::Completed);

            if ($process->chunks->isEmpty()) {
                $process->update(['status' => ClinicalAiProcessStatus::Pending]);
                SplitClinicalAudioJob::dispatch($process->id)->onQueue('clinical-ai')->afterCommit();

                return;
            }

            if ($unfinishedChunks->isNotEmpty()) {
                $process->update(['status' => ClinicalAiProcessStatus::Transcribing]);
                $unfinishedChunks->each(function ($chunk): void {
                    $chunk->update(['status' => ClinicalAiChunkStatus::Pending, 'error_message' => null]);
                    TranscribeClinicalAudioChunkJob::dispatch($chunk->id)->onQueue('clinical-ai')->afterCommit();
                });

                return;
            }

            $process->update(['status' => ClinicalAiProcessStatus::Consolidating]);
            FinalizeClinicalAiProcessJob::dispatch($process->id)->onQueue('clinical-ai')->afterCommit();
        });

        $this->audit->record(AuditEventCategory::AiProcessingRetried, $process->processable, metadata: [
            'process_id' => $process->id,
        ]);

        return response()->json(['message' => 'Processamento reenfileirado com sucesso.'], 202);
    }
}
