<?php

namespace App\Http\Controllers\Api;

use App\Enums\ClinicalRecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClinicalRecord\CancelClinicalRecordRequest;
use App\Http\Requests\ClinicalRecord\ListClinicalRecordsRequest;
use App\Http\Requests\PatientAssessment\StorePatientAssessmentRequest;
use App\Http\Requests\PatientAssessment\UpdatePatientAssessmentRequest;
use App\Http\Resources\PatientAssessmentResource;
use App\Models\Patient;
use App\Models\PatientAssessment;
use App\Services\ClinicalRecords\CancelClinicalRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PatientAssessmentController extends Controller
{
    public function index(ListClinicalRecordsRequest $request)
    {
        $filters = $request->validated();
        $query = PatientAssessment::query()->with(['patient', 'professional', 'attachments', 'aiProcess'])->latest('assessed_at');
        $query->when($filters['patient_id'] ?? null, fn ($q, int $patientId) => $q->where('patient_id', $patientId))
            ->when($filters['search'] ?? null, fn ($q, string $search) => $q->whereHas('patient', fn ($patient) => $patient
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('document', 'like', '%'.$search.'%')
                ->orWhere('phone', 'like', '%'.$search.'%')
            ))
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($q, string $date) => $q->whereDate('assessed_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($q, string $date) => $q->whereDate('assessed_at', '<=', $date));

        return PatientAssessmentResource::collection($query->paginate($filters['per_page'] ?? 15)->withQueryString());
    }

    public function store(StorePatientAssessmentRequest $request): PatientAssessmentResource
    {
        $payload = $request->safe()->except('attachments');
        $patient = Patient::findOrFail($payload['patient_id']);
        $assessment = PatientAssessment::create([
            ...$patient->only(Patient::DEMOGRAPHIC_FIELDS),
            ...$payload,
            'professional_id' => $request->user()->id,
        ]);
        $this->storeAttachments($request, $assessment);

        return new PatientAssessmentResource($assessment->load(['patient', 'professional', 'attachments', 'aiProcess']));
    }

    public function show(PatientAssessment $assessment): PatientAssessmentResource
    {
        return new PatientAssessmentResource($assessment->load(['patient', 'professional', 'attachments', 'aiProcess']));
    }

    public function update(UpdatePatientAssessmentRequest $request, PatientAssessment $assessment): PatientAssessmentResource
    {
        $this->authorizeRecord($request, $assessment->professional_id);
        abort_unless(
            in_array($assessment->status, [ClinicalRecordStatus::InReview, ClinicalRecordStatus::Completed], true),
            422,
            'Somente avaliações em revisão ou concluídas podem ser editadas.',
        );
        $assessment->update($request->safe()->except('attachments'));
        $this->storeAttachments($request, $assessment);

        return new PatientAssessmentResource($assessment->load(['patient', 'professional', 'attachments', 'aiProcess']));
    }

    public function cancel(
        CancelClinicalRecordRequest $request,
        PatientAssessment $assessment,
        CancelClinicalRecord $canceller,
    ): PatientAssessmentResource {
        $this->authorizeRecord($request, $assessment->professional_id);
        $assessment = $canceller->handle($assessment, $request->user(), $request->validated('reason'));

        return new PatientAssessmentResource($assessment->load(['patient', 'professional', 'attachments', 'aiProcess']));
    }

    public function confirm(UpdatePatientAssessmentRequest $request, PatientAssessment $assessment): PatientAssessmentResource
    {
        $this->authorizeRecord($request, $assessment->professional_id);
        abort_unless($assessment->status === ClinicalRecordStatus::InReview, 422, 'A avaliação ainda não está disponível para revisão.');
        $assessment->update($request->safe()->except('attachments') + [
            'status' => ClinicalRecordStatus::Completed,
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);
        $this->storeAttachments($request, $assessment);

        return new PatientAssessmentResource($assessment->load(['patient', 'professional', 'attachments', 'aiProcess']));
    }

    public function destroy(Request $request, PatientAssessment $assessment): Response
    {
        $this->authorizeRecord($request, $assessment->professional_id);
        $assessment->attachments->each(function ($attachment): void {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->delete();
        });
        $assessment->delete();

        return response()->noContent();
    }

    private function authorizeRecord(Request $request, int $professionalId): void
    {
        abort_unless($request->user()->hasPermission('clinical_records.manage_all') || $professionalId === $request->user()->id, 403);
    }

    private function storeAttachments(Request $request, PatientAssessment $assessment): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('assessments/'.$assessment->id, 'local');
            $assessment->attachments()->create(['disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize()]);
        }
    }
}
