<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Patient\StorePatientRequest;
use App\Http\Requests\Patient\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        $query = Patient::query()->withCount(['assessments', 'evolutions'])->latest();
        $query->when($request->filled('search'), fn ($q) => $q->where(fn ($search) => $search->where('name', 'like', '%'.$request->search.'%')->orWhere('document', 'like', '%'.$request->search.'%')->orWhere('phone', 'like', '%'.$request->search.'%')));

        return PatientResource::collection($query->paginate($request->integer('per_page', 15))->withQueryString());
    }

    public function store(StorePatientRequest $request): PatientResource
    {
        $patient = Patient::create($this->payloadWithPhoto($request));

        return new PatientResource($patient);
    }

    public function show(Patient $patient): PatientResource
    {
        return new PatientResource($patient->loadCount(['assessments', 'evolutions']));
    }

    public function update(UpdatePatientRequest $request, Patient $patient): PatientResource
    {
        $patient->update($this->payloadWithPhoto($request, $patient));

        return new PatientResource($patient);
    }

    public function destroy(Patient $patient): Response
    {
        if ($patient->photo_path) {
            Storage::disk('local')->delete($patient->photo_path);
            $patient->forceFill(['photo_path' => null])->saveQuietly();
        }
        $patient->delete();

        return response()->noContent();
    }

    public function photo(Patient $patient)
    {
        abort_unless($patient->photo_path && Storage::disk('local')->exists($patient->photo_path), 404);

        return Storage::disk('local')->response($patient->photo_path);
    }

    private function payloadWithPhoto(StorePatientRequest|UpdatePatientRequest $request, ?Patient $patient = null): array
    {
        $payload = $request->validated();
        unset($payload['photo']);

        if (! $request->hasFile('photo')) {
            return $payload;
        }
        if ($patient?->photo_path) {
            Storage::disk('local')->delete($patient->photo_path);
        }
        $payload['photo_path'] = $request->file('photo')->store('patients/photos', 'local');

        return $payload;
    }
}
