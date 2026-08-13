<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClinicalRecordResource;
use App\Models\ClinicalRecord;
use App\Models\Patient;
use App\Models\PatientAssessment;
use App\Models\PatientEvolution;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): array
    {
        return ['data' => ['active_patients' => Patient::count(), 'initial_assessments' => PatientAssessment::count(), 'records_this_month' => PatientEvolution::whereBetween('evolved_at', [now()->startOfMonth(), now()->endOfMonth()])->count(), 'recent_records' => ClinicalRecordResource::collection(ClinicalRecord::with(['patient', 'professional'])->latest('performed_at')->take(4)->get())->resolve($request)]];
    }
}
