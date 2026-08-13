<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClinicalRecord\StoreClinicalRecordRequest;
use App\Http\Requests\ClinicalRecord\UpdateClinicalRecordRequest;
use App\Http\Resources\ClinicalRecordResource;
use App\Models\ClinicalAttachment;
use App\Models\ClinicalRecord;
use App\Models\Patient;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ClinicalRecordController extends Controller
{
    public function index(Request $request)
    {
        $query = ClinicalRecord::query()->with(['patient', 'professional', 'attachments'])->latest('performed_at');
        $query->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('patient', fn ($patient) => $patient->where('name', 'like', '%'.$request->search.'%')));

        return ClinicalRecordResource::collection($query->paginate($request->integer('per_page', 15))->withQueryString());
    }

    public function store(StoreClinicalRecordRequest $request): ClinicalRecordResource
    {
        $record = ClinicalRecord::create([...$request->safe()->except('attachments'), 'professional_id' => $request->user()->id, 'reviewed_at' => now()]);
        $this->storeAttachments($request, $record);

        return new ClinicalRecordResource($record->load(['patient', 'professional', 'attachments']));
    }

    public function show(ClinicalRecord $clinicalRecord): ClinicalRecordResource
    {
        return new ClinicalRecordResource($clinicalRecord->load(['patient', 'professional', 'attachments']));
    }

    public function update(UpdateClinicalRecordRequest $request, ClinicalRecord $clinicalRecord): ClinicalRecordResource
    {
        abort_unless($request->user()->hasPermission('clinical_records.manage_all') || $clinicalRecord->professional_id === $request->user()->id, 403);
        $clinicalRecord->update($request->safe()->except('attachments'));
        $this->storeAttachments($request, $clinicalRecord);

        return new ClinicalRecordResource($clinicalRecord->load(['patient', 'professional', 'attachments']));
    }

    public function destroy(Request $request, ClinicalRecord $clinicalRecord): Response
    {
        abort_unless($request->user()->hasPermission('clinical_records.manage_all') || $clinicalRecord->professional_id === $request->user()->id, 403);
        $clinicalRecord->attachments->each(fn (ClinicalAttachment $attachment) => Storage::disk($attachment->disk)->delete($attachment->path));
        $clinicalRecord->delete();

        return response()->noContent();
    }

    public function downloadAttachment(ClinicalAttachment $attachment)
    {
        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function exportPatientHistory(Patient $patient)
    {
        $patient->load(['clinicalRecords' => fn ($query) => $query->with('professional')->orderBy('performed_at')]);

        return Pdf::loadView('pdf.patient-history', compact('patient'))->download('historico-'.$patient->id.'.pdf');
    }

    private function storeAttachments(Request $request, ClinicalRecord $record): void
    {
        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('clinical-records/'.$record->id, 'local');
            $record->attachments()->create(['disk' => 'local', 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize()]);
        }
    }
}
