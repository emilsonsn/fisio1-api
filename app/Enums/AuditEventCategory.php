<?php

namespace App\Enums;

enum AuditEventCategory: string
{
    case Login = 'auth.login';
    case LoginFailed = 'auth.login_failed';
    case Logout = 'auth.logout';
    case PasswordResetRequested = 'auth.password_reset_requested';
    case PasswordResetCodeVerified = 'auth.password_reset_code_verified';
    case PasswordResetVerificationFailed = 'auth.password_reset_verification_failed';
    case PasswordReset = 'auth.password_reset';

    case PatientCreated = 'patient.created';
    case PatientUpdated = 'patient.updated';
    case PatientDeleted = 'patient.deleted';
    case PatientHistoryExported = 'patient.history_exported';

    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserDeleted = 'user.deleted';
    case UserGroupsUpdated = 'user.groups_updated';

    case AccessGroupCreated = 'access_group.created';
    case AccessGroupUpdated = 'access_group.updated';
    case AccessGroupDeleted = 'access_group.deleted';
    case AccessGroupPermissionsUpdated = 'access_group.permissions_updated';

    case AssessmentCreated = 'assessment.created';
    case AssessmentUpdated = 'assessment.updated';
    case AssessmentConfirmed = 'assessment.confirmed';
    case AssessmentCancelled = 'assessment.cancelled';
    case AssessmentDeleted = 'assessment.deleted';

    case EvolutionCreated = 'evolution.created';
    case EvolutionUpdated = 'evolution.updated';
    case EvolutionConfirmed = 'evolution.confirmed';
    case EvolutionCancelled = 'evolution.cancelled';
    case EvolutionDeleted = 'evolution.deleted';

    case ClinicalRecordCreated = 'clinical_record.created';
    case ClinicalRecordUpdated = 'clinical_record.updated';
    case ClinicalRecordDeleted = 'clinical_record.deleted';

    case AttachmentCreated = 'attachment.created';
    case AttachmentDeleted = 'attachment.deleted';
    case AttachmentDownloaded = 'attachment.downloaded';

    case AiProcessingStarted = 'clinical_ai.started';
    case AiProcessingRetried = 'clinical_ai.retried';
    case AiProcessingCompleted = 'clinical_ai.completed';
    case AiProcessingFailed = 'clinical_ai.failed';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Login realizado',
            self::LoginFailed => 'Tentativa de login sem sucesso',
            self::Logout => 'Logout realizado',
            self::PasswordResetRequested => 'Recuperação de senha solicitada',
            self::PasswordResetCodeVerified => 'Código de recuperação validado',
            self::PasswordResetVerificationFailed => 'Validação do código de recuperação falhou',
            self::PasswordReset => 'Senha redefinida',
            self::PatientCreated => 'Paciente cadastrado',
            self::PatientUpdated => 'Paciente editado',
            self::PatientDeleted => 'Paciente excluído',
            self::PatientHistoryExported => 'Histórico do paciente exportado',
            self::UserCreated => 'Usuário cadastrado',
            self::UserUpdated => 'Usuário editado',
            self::UserDeleted => 'Usuário excluído',
            self::UserGroupsUpdated => 'Grupos do usuário alterados',
            self::AccessGroupCreated => 'Grupo cadastrado',
            self::AccessGroupUpdated => 'Grupo editado',
            self::AccessGroupDeleted => 'Grupo excluído',
            self::AccessGroupPermissionsUpdated => 'Permissões do grupo alteradas',
            self::AssessmentCreated => 'Avaliação cadastrada',
            self::AssessmentUpdated => 'Avaliação editada',
            self::AssessmentConfirmed => 'Avaliação concluída',
            self::AssessmentCancelled => 'Avaliação cancelada',
            self::AssessmentDeleted => 'Avaliação excluída',
            self::EvolutionCreated => 'Evolução cadastrada',
            self::EvolutionUpdated => 'Evolução editada',
            self::EvolutionConfirmed => 'Evolução concluída',
            self::EvolutionCancelled => 'Evolução cancelada',
            self::EvolutionDeleted => 'Evolução excluída',
            self::ClinicalRecordCreated => 'Registro clínico cadastrado',
            self::ClinicalRecordUpdated => 'Registro clínico editado',
            self::ClinicalRecordDeleted => 'Registro clínico excluído',
            self::AttachmentCreated => 'Anexo inserido',
            self::AttachmentDeleted => 'Anexo excluído',
            self::AttachmentDownloaded => 'Anexo baixado',
            self::AiProcessingStarted => 'Processamento por IA iniciado',
            self::AiProcessingRetried => 'Processamento por IA retomado',
            self::AiProcessingCompleted => 'Processamento por IA concluído',
            self::AiProcessingFailed => 'Processamento por IA falhou',
        };
    }

    public function group(): string
    {
        return match (true) {
            str_starts_with($this->value, 'auth.') => 'Autenticação',
            str_starts_with($this->value, 'patient.') => 'Pacientes',
            str_starts_with($this->value, 'user.') => 'Usuários',
            str_starts_with($this->value, 'access_group.') => 'Grupos e permissões',
            str_starts_with($this->value, 'assessment.') => 'Avaliações',
            str_starts_with($this->value, 'evolution.') => 'Evoluções',
            str_starts_with($this->value, 'clinical_record.') => 'Registros clínicos',
            str_starts_with($this->value, 'attachment.') => 'Anexos',
            default => 'Inteligência artificial',
        };
    }
}
