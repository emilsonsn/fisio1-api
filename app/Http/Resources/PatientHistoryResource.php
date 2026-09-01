<?php

namespace App\Http\Resources;

use App\Models\PatientAssessment;
use App\Models\PatientEvolution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class PatientHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $timeline = $this->timeline();
        $painLevels = $this->evolutions
            ->whereNotNull('pain_level')
            ->sortBy([['evolved_at', 'asc'], ['id', 'asc']])
            ->pluck('pain_level')
            ->values();

        return [
            'patient' => new PatientResource($this->resource),
            'summary' => [
                'total_records' => $timeline->count(),
                'total_assessments' => $this->assessments->count(),
                'total_evolutions' => $this->evolutions->count(),
                'first_record_at' => $timeline->first()['recorded_at'] ?? null,
                'last_record_at' => $timeline->last()['recorded_at'] ?? null,
                'initial_pain_level' => $painLevels->first(),
                'current_pain_level' => $painLevels->last(),
                'pain_change' => $painLevels->isEmpty()
                    ? null
                    : (int) $painLevels->first() - (int) $painLevels->last(),
            ],
            'timeline' => $timeline->values(),
        ];
    }

    private function timeline(): Collection
    {
        return $this->assessments
            ->map(fn (PatientAssessment $assessment): array => [
                'id' => $assessment->id,
                'type' => 'initial_assessment',
                'recorded_at' => $assessment->assessed_at?->toDateString(),
                'status' => $assessment->status->value,
                'professional' => new UserResource($assessment->professional),
                'attachment_count' => $assessment->attachments->count(),
                'pain_level' => null,
                'fields' => [
                    'chief_complaint' => $assessment->chief_complaint,
                    'condition_history' => $assessment->condition_history,
                    'physical_examination' => $assessment->physical_examination,
                    'physical_therapy_diagnosis' => $assessment->physical_therapy_diagnosis,
                    'therapeutic_objectives' => $assessment->therapeutic_objectives,
                    'physical_therapy_prognosis' => $assessment->physical_therapy_prognosis,
                    'resources_methods_techniques' => $assessment->resources_methods_techniques,
                ],
            ])
            ->concat($this->evolutions->map(fn (PatientEvolution $evolution): array => [
                'id' => $evolution->id,
                'type' => 'evolution',
                'recorded_at' => $evolution->evolved_at?->toDateString(),
                'status' => $evolution->status->value,
                'professional' => new UserResource($evolution->professional),
                'attachment_count' => $evolution->attachments->count(),
                'pain_level' => $evolution->pain_level,
                'fields' => [
                    'daily_complaint' => $evolution->daily_complaint,
                    'home_guidance_adherence' => $evolution->home_guidance_adherence,
                    'therapeutic_conduct' => $evolution->therapeutic_conduct,
                    'session_final_impression' => $evolution->session_final_impression,
                    'observations' => $evolution->observations,
                ],
            ]))
            ->sortBy(fn (array $entry): string => ($entry['recorded_at'] ?? '').'-'.$entry['type'].'-'.$entry['id']);
    }
}
