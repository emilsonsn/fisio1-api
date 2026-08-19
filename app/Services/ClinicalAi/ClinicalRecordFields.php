<?php

namespace App\Services\ClinicalAi;

use App\Models\Patient;
use App\Models\PatientAssessment;
use App\Models\PatientEvolution;
use Illuminate\Database\Eloquent\Model;

class ClinicalRecordFields
{
    private const ASSESSMENT = ['indication', 'birthplace', 'marital_status', 'gender', 'profession', 'address', 'chief_complaint', 'condition_history', 'life_habits', 'personal_family_history', 'previous_treatments', 'physical_examination', 'complementary_exams', 'physical_therapy_diagnosis', 'cbdf', 'planned_sessions', 'resources_methods_techniques', 'therapeutic_objectives', 'physical_therapy_prognosis'];

    private const EVOLUTION = ['daily_complaint', 'pain_level', 'home_guidance_adherence', 'therapeutic_conduct', 'session_final_impression', 'observations'];

    public function type(Model $record): string
    {
        return $record instanceof PatientEvolution ? 'evolution' : 'initial_assessment';
    }

    public function onlyFor(Model $record, array $fields): array
    {
        return collect($record instanceof PatientAssessment ? self::ASSESSMENT : self::EVOLUTION)
            ->mapWithKeys(function (string $field) use ($fields, $record): array {
                if ($record instanceof PatientAssessment && in_array($field, Patient::DEMOGRAPHIC_FIELDS, true) && $record->getAttribute($field) !== null) {
                    return [$field => $record->getAttribute($field)];
                }

                return [$field => $fields[$field] ?? null];
            })
            ->all();
    }
}
