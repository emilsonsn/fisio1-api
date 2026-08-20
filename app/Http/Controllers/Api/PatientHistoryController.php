<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatientHistoryResource;
use App\Models\Patient;

class PatientHistoryController extends Controller
{
    public function __invoke(Patient $patient): PatientHistoryResource
    {
        $patient->load([
            'assessments' => fn ($query) => $query
                ->with(['professional', 'attachments'])
                ->oldest('assessed_at')
                ->oldest('id'),
            'evolutions' => fn ($query) => $query
                ->with(['professional', 'attachments'])
                ->oldest('evolved_at')
                ->oldest('id'),
        ]);

        return new PatientHistoryResource($patient);
    }
}
