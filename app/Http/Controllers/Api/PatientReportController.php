<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditEventCategory;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientAssessment;
use App\Models\PatientEvolution;
use App\Services\Audit\AuditLogger;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PatientReportController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Request $request, Patient $patient): Response
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

        $timeline = $this->timeline($patient);
        $painLevels = $patient->evolutions
            ->whereNotNull('pain_level')
            ->sortBy([['evolved_at', 'asc'], ['id', 'asc']])
            ->pluck('pain_level')
            ->map(fn ($level): int => (int) $level)
            ->values();

        $summary = [
            'total_records' => $timeline->count(),
            'total_assessments' => $patient->assessments->count(),
            'total_evolutions' => $patient->evolutions->count(),
            'first_record_at' => $timeline->first()['date'] ?? null,
            'last_record_at' => $timeline->last()['date'] ?? null,
            'initial_pain_level' => $painLevels->first(),
            'current_pain_level' => $painLevels->last(),
            'pain_change' => $painLevels->isEmpty()
                ? null
                : $painLevels->first() - $painLevels->last(),
        ];

        $logoDataUri = $this->logoDataUri();
        $generatedAt = now();
        $generatedBy = $request->user();

        $this->audit->record(AuditEventCategory::PatientHistoryExported, $patient);

        $filename = 'relatorio-clinico-'.Str::slug($patient->name).'.pdf';

        return Pdf::loadView('pdf.patient-history', compact(
            'patient',
            'timeline',
            'summary',
            'logoDataUri',
            'generatedAt',
            'generatedBy',
        ))
            ->setPaper('a4')
            ->download($filename);
    }

    private function timeline(Patient $patient): Collection
    {
        $assessments = $patient->assessments->map(fn (PatientAssessment $assessment): array => [
            'type' => 'assessment',
            'date' => $assessment->assessed_at,
            'record' => $assessment,
        ]);

        $evolutions = $patient->evolutions->map(fn (PatientEvolution $evolution): array => [
            'type' => 'evolution',
            'date' => $evolution->evolved_at,
            'record' => $evolution,
        ]);

        return $assessments
            ->concat($evolutions)
            ->sortBy(fn (array $entry): string => ($entry['date']?->format('Y-m-d') ?? '').'-'.$entry['type'].'-'.$entry['record']->id)
            ->values();
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('images/fisio1-logo.png');

        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }
}
