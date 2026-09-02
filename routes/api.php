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
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RecordAttachmentController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Autenticação pública
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
        Route::post('forgot-password/verify', [AuthController::class, 'verifyPasswordRecoveryCode'])->middleware('throttle:10,1');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        // Sessão autenticada
        Route::prefix('auth')->group(function (): void {
            Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
        });

        // Perfil do usuário autenticado
        Route::prefix('profile')->group(function (): void {
            Route::match(['put', 'patch'], '/', [ProfileController::class, 'update']);
            Route::put('password', [ProfileController::class, 'updatePassword']);
            Route::get('photo', [ProfileController::class, 'photo'])->name('profile.photo');
        });

        // Visão geral
        Route::get('dashboard', DashboardController::class)->middleware('permission:dashboard.view');

        // Pacientes e histórico clínico
        Route::prefix('patients')->group(function (): void {
            Route::get('/', [PatientController::class, 'index'])->middleware('permission:patients.view');
            Route::post('/', [PatientController::class, 'store'])->middleware('permission:patients.create');
            Route::get('{patient}/history', PatientHistoryController::class)->middleware('permission:patients.view');
            Route::get('{patient}/history.pdf', PatientReportController::class)
                ->middleware('permission:patients.export')
                ->name('patients.history.pdf');
            Route::get('{patient}', [PatientController::class, 'show'])->middleware('permission:patients.view');
            Route::get('{patient}/photo', [PatientController::class, 'photo'])
                ->middleware('permission:patients.view')
                ->name('patients.photo');
            Route::match(['put', 'patch'], '{patient}', [PatientController::class, 'update'])
                ->middleware('permission:patients.update');
            Route::delete('{patient}', [PatientController::class, 'destroy'])->middleware('permission:patients.delete');
        });

        // Processamento de áudio por IA
        Route::prefix('clinical-ai')->group(function (): void {
            Route::post('process-audio', [ClinicalAiController::class, 'processAudio'])
                ->middleware('permission:clinical_records.create');
            Route::post('processes/{process}/retry', [ClinicalAiController::class, 'retry'])
                ->middleware('permission:clinical_records.update');
        });

        // Avaliações iniciais
        Route::prefix('assessments')->group(function (): void {
            Route::get('/', [PatientAssessmentController::class, 'index'])->middleware('permission:clinical_records.view');
            Route::post('/', [PatientAssessmentController::class, 'store'])->middleware('permission:clinical_records.create');
            Route::get('{assessment}', [PatientAssessmentController::class, 'show'])->middleware('permission:clinical_records.view');
            Route::match(['put', 'patch'], '{assessment}', [PatientAssessmentController::class, 'update'])
                ->middleware('permission:clinical_records.update');
            Route::post('{assessment}/confirm', [PatientAssessmentController::class, 'confirm'])
                ->middleware('permission:clinical_records.update');
            Route::post('{assessment}/cancel', [PatientAssessmentController::class, 'cancel'])
                ->middleware('permission:clinical_records.cancel');
            Route::delete('{assessment}', [PatientAssessmentController::class, 'destroy'])
                ->middleware('permission:clinical_records.delete');
        });

        // Evoluções
        Route::prefix('evolutions')->group(function (): void {
            Route::get('/', [PatientEvolutionController::class, 'index'])->middleware('permission:clinical_records.view');
            Route::post('/', [PatientEvolutionController::class, 'store'])->middleware('permission:clinical_records.create');
            Route::get('{evolution}', [PatientEvolutionController::class, 'show'])->middleware('permission:clinical_records.view');
            Route::match(['put', 'patch'], '{evolution}', [PatientEvolutionController::class, 'update'])
                ->middleware('permission:clinical_records.update');
            Route::post('{evolution}/confirm', [PatientEvolutionController::class, 'confirm'])
                ->middleware('permission:clinical_records.update');
            Route::post('{evolution}/cancel', [PatientEvolutionController::class, 'cancel'])
                ->middleware('permission:clinical_records.cancel');
            Route::delete('{evolution}', [PatientEvolutionController::class, 'destroy'])
                ->middleware('permission:clinical_records.delete');
        });

        // Registros clínicos legados e seus anexos
        Route::prefix('clinical-records')->group(function (): void {
            Route::get('/', [ClinicalRecordController::class, 'index'])->middleware('permission:clinical_records.view');
            Route::post('/', [ClinicalRecordController::class, 'store'])->middleware('permission:clinical_records.create');
            Route::get('{clinicalRecord}', [ClinicalRecordController::class, 'show'])->middleware('permission:clinical_records.view');
            Route::match(['put', 'patch'], '{clinicalRecord}', [ClinicalRecordController::class, 'update'])
                ->middleware('permission:clinical_records.update');
            Route::delete('{clinicalRecord}', [ClinicalRecordController::class, 'destroy'])
                ->middleware('permission:clinical_records.delete');
        });

        Route::prefix('record-attachments')->group(function (): void {
            Route::get('{recordAttachment}/download', [RecordAttachmentController::class, 'download'])
                ->middleware('permission:attachments.download');
            Route::delete('{recordAttachment}', [RecordAttachmentController::class, 'destroy'])
                ->middleware('permission:clinical_records.update');
        });

        Route::prefix('attachments')->group(function (): void {
            Route::get('{attachment}/download', [ClinicalRecordController::class, 'downloadAttachment'])
                ->middleware('permission:attachments.download')
                ->name('attachments.download');
        });

        // Administração e segurança
        Route::get('permissions', PermissionController::class)->middleware('permission:permissions.view');

        Route::prefix('audit-logs')->middleware(['admin', 'permission:audit_logs.view'])->group(function (): void {
            Route::get('options', [AuditLogController::class, 'options']);
            Route::get('/', [AuditLogController::class, 'index']);
        });

        Route::prefix('groups')->group(function (): void {
            Route::get('/', [AccessGroupController::class, 'index'])->middleware('permission:groups.view');
            Route::post('/', [AccessGroupController::class, 'store'])->middleware('permission:groups.manage');
            Route::get('{accessGroup}', [AccessGroupController::class, 'show'])->middleware('permission:groups.view');
            Route::match(['put', 'patch'], '{accessGroup}', [AccessGroupController::class, 'update'])
                ->middleware('permission:groups.manage');
            Route::delete('{accessGroup}', [AccessGroupController::class, 'destroy'])->middleware('permission:groups.manage');
        });

        Route::prefix('users')->middleware('permission:users.manage')->group(function (): void {
            Route::get('/', [UserController::class, 'index']);
            Route::post('/', [UserController::class, 'store']);
            Route::get('{user}', [UserController::class, 'show']);
            Route::match(['put', 'patch'], '{user}', [UserController::class, 'update']);
            Route::get('{user}/photo', [UserController::class, 'photo'])->name('users.photo');
        });
    });
});
