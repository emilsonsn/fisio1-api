<?php

namespace App\Services\Audit;

use App\Enums\AuditEventCategory;
use App\Models\AccessGroup;
use App\Models\AuditLog;
use App\Models\ClinicalAttachment;
use App\Models\ClinicalRecord;
use App\Models\Patient;
use App\Models\PatientAssessment;
use App\Models\PatientEvolution;
use App\Models\RecordAttachment;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'access_token',
        'api_key',
    ];

    public function record(
        AuditEventCategory $event,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        ?Authenticatable $user = null,
    ): AuditLog {
        $actor = $user ?? Auth::user();
        $request = app()->bound('request') ? request() : null;

        return AuditLog::query()->create([
            'event' => $event,
            'user_id' => $actor?->getAuthIdentifier(),
            'user_name' => $actor instanceof User ? $actor->name : null,
            'user_email' => $actor instanceof User ? $actor->email : null,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'auditable_label' => $this->label($auditable),
            'old_values' => $this->sanitize($oldValues) ?: null,
            'new_values' => $this->sanitize($newValues) ?: null,
            'metadata' => $this->sanitize($metadata + array_filter([
                'method' => $request?->method(),
                'path' => $request?->path(),
            ])) ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 1000, ''),
        ]);
    }

    private function label(?Model $model): ?string
    {
        return match (true) {
            $model instanceof Patient,
            $model instanceof User,
            $model instanceof AccessGroup => $model->name,
            $model instanceof RecordAttachment,
            $model instanceof ClinicalAttachment => $model->original_name,
            $model instanceof PatientAssessment => $this->clinicalDocumentLabel('Avaliação', $model),
            $model instanceof PatientEvolution => $this->clinicalDocumentLabel('Evolução', $model),
            $model instanceof ClinicalRecord => $this->clinicalDocumentLabel('Registro clínico', $model),
            $model !== null => class_basename($model).' #'.$model->getKey(),
            default => null,
        };
    }

    private function clinicalDocumentLabel(string $documentType, Model $model): string
    {
        return $model->patient?->name
            ? $documentType.' — '.$model->patient->name
            : $documentType.' #'.$model->getKey();
    }

    private function sanitize(array $values): array
    {
        return collect($values)->mapWithKeys(function (mixed $value, string|int $key): array {
            if (is_string($key) && in_array(Str::lower($key), self::SENSITIVE_KEYS, true)) {
                return [$key => '[REDACTED]'];
            }

            return [$key => $this->normalize($value)];
        })->all();
    }

    private function normalize(mixed $value): mixed
    {
        return match (true) {
            is_array($value) => $this->sanitize($value),
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            is_object($value) && method_exists($value, '__toString') => (string) $value,
            is_scalar($value), $value === null => $value,
            default => '[UNSERIALIZABLE]',
        };
    }
}
