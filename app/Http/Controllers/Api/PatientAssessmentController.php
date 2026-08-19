<?php

namespace App\Http\Controllers\Api;

use App\Enums\ClinicalRecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientAssessment\StorePatientAssessmentRequest;
use App\Http\Requests\PatientAssessment\UpdatePatientAssessmentRequest;
use App\Http\Resources\PatientAssessmentResource;
use App\Models\PatientAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PatientAssessmentController extends Controller
{
    public function index(Request $request)
    {
        $query = PatientAssessment::query()->with(['patient', 'professional', 'attachments', 'aiProcess'])->latest('assessed_at');
        $query->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('patient', fn ($patient) => $patient->where('name', 'like', '%'.$request->string('search').'%')));

        return PatientAssessmentResource::collection($query->paginate($request->integer('per_page', 15))->withQueryString());
    }

    public function store(StorePatientAssessmentRequest $request): PatientAssessmentResource
    {
        $assessment = PatientAssessment::create([...$request->safe()->except('attachments'), 'professional_id' => $request->user()->id]);
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
        $assessment->update($request->safe()->except('attachments'));
        $this->storeAttachments($request, $assessment);

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
        $assessment->attachments->each(fn ($attachment) => Storage::disk($attachment->disk)->delete($attachment->path));
        $assessment->attachments()->delete();
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
