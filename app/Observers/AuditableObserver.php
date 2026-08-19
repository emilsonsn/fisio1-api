<?php

namespace App\Observers;

use App\Services\Audit\AuditEventResolver;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class AuditableObserver
{
    private const IGNORED_FIELDS = ['created_at', 'updated_at', 'deleted_at'];

    public function __construct(
        private readonly AuditLogger $logger,
        private readonly AuditEventResolver $events,
    ) {}

    public function created(Model $model): void
    {
        if (! Auth::check() || ! $event = $this->events->created($model)) {
            return;
        }

        $this->logger->record($event, $model, newValues: $this->withoutTimestamps($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        if (! Auth::check() || ! $event = $this->events->updated($model)) {
            return;
        }

        $newValues = $this->withoutTimestamps($model->getChanges());
        if ($newValues === []) {
            return;
        }

        $oldValues = collect(array_keys($newValues))
            ->mapWithKeys(fn (string $key): array => [$key => $model->getRawOriginal($key)])
            ->all();

        $this->logger->record($event, $model, $oldValues, $newValues);
    }

    public function deleted(Model $model): void
    {
        if (! Auth::check() || ! $event = $this->events->deleted($model)) {
            return;
        }

        $this->logger->record($event, $model, oldValues: $this->withoutTimestamps($model->getAttributes()));
    }

    private function withoutTimestamps(array $values): array
    {
        return Arr::except($values, self::IGNORED_FIELDS);
    }
}
