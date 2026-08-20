<?php

use App\Http\Controllers\Api\AccessGroupController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClinicalAiController;
use App\Http\Controllers\Api\ClinicalRecordController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PatientAssessmentController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PatientEvolutionController;
use App\Http\Controllers\Api\PatientHistoryController;
use App\Http\Controllers\Api\PatientReportController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RecordAttachmentController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
    Route::post('auth/forgot-password/verify', [AuthController::class, 'verifyPasswordRecoveryCode'])->middleware('throttle:10,1');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('dashboard', DashboardController::class)->middleware('permission:dashboard.view');
        Route::get('patients', [PatientController::class, 'index'])->middleware('permission:patients.view');
        Route::post('patients', [PatientController::class, 'store'])->middleware('permission:patients.create');
        Route::get('patients/{patient}/history', PatientHistoryController::class)->middleware('permission:patients.view');
        Route::get('patients/{patient}', [PatientController::class, 'show'])->middleware('permission:patients.view');
        Route::get('patients/{patient}/photo', [PatientController::class, 'photo'])->middleware('permission:patients.view')->name('patients.photo');
        Route::match(['put', 'patch'], 'patients/{patient}', [PatientController::class, 'update'])->middleware('permission:patients.update');
        Route::delete('patients/{patient}', [PatientController::class, 'destroy'])->middleware('permission:patients.delete');
        Route::get('patients/{patient}/history.pdf', PatientReportController::class)->middleware('permission:patients.export')->name('patients.history.pdf');
        Route::get('clinical-records', [ClinicalRecordController::class, 'index'])->middleware('permission:clinical_records.view');
        Route::post('clinical-ai/process-audio', [ClinicalAiController::class, 'processAudio'])->middleware('permission:clinical_records.create');
        Route::post('clinical-ai/processes/{process}/retry', [ClinicalAiController::class, 'retry'])->middleware('permission:clinical_records.update');

        Route::get('assessments', [PatientAssessmentController::class, 'index'])->middleware('permission:clinical_records.view');
        Route::post('assessments', [PatientAssessmentController::class, 'store'])->middleware('permission:clinical_records.create');
        Route::get('assessments/{assessment}', [PatientAssessmentController::class, 'show'])->middleware('permission:clinical_records.view');
        Route::match(['put', 'patch'], 'assessments/{assessment}', [PatientAssessmentController::class, 'update'])->middleware('permission:clinical_records.update');
        Route::post('assessments/{assessment}/confirm', [PatientAssessmentController::class, 'confirm'])->middleware('permission:clinical_records.update');
        Route::post('assessments/{assessment}/cancel', [PatientAssessmentController::class, 'cancel'])->middleware('permission:clinical_records.cancel');
        Route::delete('assessments/{assessment}', [PatientAssessmentController::class, 'destroy'])->middleware('permission:clinical_records.delete');
        Route::get('evolutions', [PatientEvolutionController::class, 'index'])->middleware('permission:clinical_records.view');
        Route::post('evolutions', [PatientEvolutionController::class, 'store'])->middleware('permission:clinical_records.create');
        Route::get('evolutions/{evolution}', [PatientEvolutionController::class, 'show'])->middleware('permission:clinical_records.view');
        Route::match(['put', 'patch'], 'evolutions/{evolution}', [PatientEvolutionController::class, 'update'])->middleware('permission:clinical_records.update');
        Route::post('evolutions/{evolution}/confirm', [PatientEvolutionController::class, 'confirm'])->middleware('permission:clinical_records.update');
        Route::post('evolutions/{evolution}/cancel', [PatientEvolutionController::class, 'cancel'])->middleware('permission:clinical_records.cancel');
        Route::delete('evolutions/{evolution}', [PatientEvolutionController::class, 'destroy'])->middleware('permission:clinical_records.delete');
        Route::get('record-attachments/{recordAttachment}/download', [RecordAttachmentController::class, 'download'])->middleware('permission:attachments.download')->name('record-attachments.download');
        Route::delete('record-attachments/{recordAttachment}', [RecordAttachmentController::class, 'destroy'])->middleware('permission:clinical_records.update');
        Route::post('clinical-records', [ClinicalRecordController::class, 'store'])->middleware('permission:clinical_records.create');
        Route::get('clinical-records/{clinicalRecord}', [ClinicalRecordController::class, 'show'])->middleware('permission:clinical_records.view');
        Route::match(['put', 'patch'], 'clinical-records/{clinicalRecord}', [ClinicalRecordController::class, 'update'])->middleware('permission:clinical_records.update');
        Route::delete('clinical-records/{clinicalRecord}', [ClinicalRecordController::class, 'destroy'])->middleware('permission:clinical_records.delete');
        Route::get('attachments/{attachment}/download', [ClinicalRecordController::class, 'downloadAttachment'])->middleware('permission:attachments.download')->name('attachments.download');
        Route::get('permissions', PermissionController::class)->middleware('permission:permissions.view');
        Route::get('audit-logs/options', [AuditLogController::class, 'options'])->middleware(['admin', 'permission:audit_logs.view']);
        Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware(['admin', 'permission:audit_logs.view']);
        Route::get('groups', [AccessGroupController::class, 'index'])->middleware('permission:groups.view');
        Route::post('groups', [AccessGroupController::class, 'store'])->middleware('permission:groups.manage');
        Route::get('groups/{accessGroup}', [AccessGroupController::class, 'show'])->middleware('permission:groups.view');
        Route::match(['put', 'patch'], 'groups/{accessGroup}', [AccessGroupController::class, 'update'])->middleware('permission:groups.manage');
        Route::delete('groups/{accessGroup}', [AccessGroupController::class, 'destroy'])->middleware('permission:groups.manage');
        Route::apiResource('users', UserController::class)->middleware('permission:users.manage')->except('destroy');
        Route::get('users/{user}/photo', [UserController::class, 'photo'])->middleware('permission:users.manage')->name('users.photo');
    });
});
