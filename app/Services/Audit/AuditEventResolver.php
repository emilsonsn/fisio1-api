<?php

namespace App\Services\Audit;

use App\Enums\AuditEventCategory;
use App\Enums\ClinicalRecordStatus;
use App\Models\AccessGroup;
use App\Models\ClinicalAttachment;
use App\Models\ClinicalRecord;
use App\Models\Patient;
use App\Models\PatientAssessment;
use App\Models\PatientEvolution;
use App\Models\RecordAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditEventResolver
{
    public function created(Model $model): ?AuditEventCategory
    {
        return match ($model::class) {
            Patient::class => AuditEventCategory::PatientCreated,
            User::class => AuditEventCategory::UserCreated,
            AccessGroup::class => AuditEventCategory::AccessGroupCreated,
            PatientAssessment::class => AuditEventCategory::AssessmentCreated,
            PatientEvolution::class => AuditEventCategory::EvolutionCreated,
            ClinicalRecord::class => AuditEventCategory::ClinicalRecordCreated,
            RecordAttachment::class, ClinicalAttachment::class => AuditEventCategory::AttachmentCreated,
            default => null,
        };
    }

    public function updated(Model $model): ?AuditEventCategory
    {
        return match ($model::class) {
            Patient::class => AuditEventCategory::PatientUpdated,
            User::class => AuditEventCategory::UserUpdated,
            AccessGroup::class => AuditEventCategory::AccessGroupUpdated,
            PatientAssessment::class => match (true) {
                $this->statusChangedTo($model, ClinicalRecordStatus::Completed) => AuditEventCategory::AssessmentConfirmed,
                $this->statusChangedTo($model, ClinicalRecordStatus::Cancelled) => AuditEventCategory::AssessmentCancelled,
                default => AuditEventCategory::AssessmentUpdated,
            },
            PatientEvolution::class => match (true) {
                $this->statusChangedTo($model, ClinicalRecordStatus::Completed) => AuditEventCategory::EvolutionConfirmed,
                $this->statusChangedTo($model, ClinicalRecordStatus::Cancelled) => AuditEventCategory::EvolutionCancelled,
                default => AuditEventCategory::EvolutionUpdated,
            },
            ClinicalRecord::class => AuditEventCategory::ClinicalRecordUpdated,
            default => null,
        };
    }

    public function deleted(Model $model): ?AuditEventCategory
    {
        return match ($model::class) {
            Patient::class => AuditEventCategory::PatientDeleted,
            User::class => AuditEventCategory::UserDeleted,
            AccessGroup::class => AuditEventCategory::AccessGroupDeleted,
            PatientAssessment::class => AuditEventCategory::AssessmentDeleted,
            PatientEvolution::class => AuditEventCategory::EvolutionDeleted,
            ClinicalRecord::class => AuditEventCategory::ClinicalRecordDeleted,
            RecordAttachment::class, ClinicalAttachment::class => AuditEventCategory::AttachmentDeleted,
            default => null,
        };
    }

    private function statusChangedTo(Model $model, ClinicalRecordStatus $status): bool
    {
        return $model->wasChanged('status')
            && $model->getAttribute('status') === $status;
    }
}
