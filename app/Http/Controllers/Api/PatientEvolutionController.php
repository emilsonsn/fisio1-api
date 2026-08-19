<?php

namespace App\Http\Controllers\Api;

use App\Enums\ClinicalRecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientEvolution\StorePatientEvolutionRequest;
use App\Http\Requests\PatientEvolution\UpdatePatientEvolutionRequest;
use App\Http\Resources\PatientEvolutionResource;
use App\Models\PatientEvolution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PatientEvolutionController extends Controller
{
    public function index(Request $request)
    {
        $query = PatientEvolution::query()->with(['patient', 'professional', 'attachments', 'aiProcess'])->latest('evolved_at');
        $query->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('patient', fn ($patient) => $patient->where('name', 'like', '%'.$request->string('search').'%')));

        return PatientEvolutionResource::collection($query->paginate($request->integer('per_page', 15))->withQueryString());
    }

    public function store(StorePatientEvolutionRequest $request): PatientEvolutionResource
    {
        $evolution = PatientEvolution::create([...$request->safe()->except('attachments'), 'professional_id' => $request->user()->id]);
        $this->storeAttachments($request, $evolution);

        return new PatientEvolutionResource($evolution->load(['patient', 'professional', 'attachments', 'aiProcess']));
    }

    public function show(PatientEvolution $evolution): PatientEvolutionResource
    {
        return new PatientEvolutionResource($evolution->load(['patient', 'professional', 'attachments', 'aiProcess']));
    }

    public function update(UpdatePatientEvolutionRequest $request, PatientEvolution $evolution): PatientEvolutionResource
    {
        $this->authorizeRecord($request, $evolution->professional_id);
        $evolution->update($request->safe()->except('attachments'));
        $this->storeAttachments($request, $evolution);

        return new PatientEvolutionResource($evolution->load(['patient', 'professional', 'attachments', 'aiProcess']));
    }

    public function confirm(UpdatePatientEvolutionRequest $request, PatientEvolution $evolution): PatientEvolutionResource
    {
        $this->authorizeRecord($request, $evolution->professional_id);
        abort_unless($evolution->status === ClinicalRecordStatus::InReview, 422, 'A evolução ainda não está disponível para revisão.');
        $evolution->update($request->safe()->except('attachments') + [
            'status' => ClinicalRecordStatus::Completed,
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);
        $this->storeAttachments($request, $evolution);

        return new PatientEvolutionResource($evolution->load(['patient', 'professional', 'attachments', 'aiProcess']));
    }

    public function destroy(Request $request, PatientEvolution $evolution): Response
    {
        $this->authorizeRecord($request, $evolution->professional_id);
        $evolution->attachments->each(fn ($attachment) => Storage::disk($attachment->disk)->delete($attachment->path));
        $evolution->attachments()->delete();
        $evolution->delete();

        return response()->noContent();
    }

    private function authorizeRecord(Request $request, int $professionalId): void
    {
        abort_unless($request->user()->hasPermission('clinical_records.manage_all') || $professionalId === $request->user()->id, 403);
    }

    private function storeAttachments(Request $request, PatientEvolution $evolution): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('evolutions/'.$evolution->id, 'local');
            $evolution->attachments()->create(['disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize()]);
        }
    }
}
