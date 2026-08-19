<?php

namespace App\Providers;

use App\Models\AccessGroup;
use App\Models\ClinicalAttachment;
use App\Models\ClinicalRecord;
use App\Models\Patient;
use App\Models\PatientAssessment;
use App\Models\PatientEvolution;
use App\Models\RecordAttachment;
use App\Models\User;
use App\Observers\AuditableObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([
            Patient::class,
            User::class,
            AccessGroup::class,
            PatientAssessment::class,
            PatientEvolution::class,
            ClinicalRecord::class,
            RecordAttachment::class,
            ClinicalAttachment::class,
        ] as $model) {
            $model::observe(AuditableObserver::class);
        }
    }
}
