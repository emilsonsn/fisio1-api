<?php

namespace Tests\Feature;

use App\Enums\AuditEventCategory;
use App\Enums\ClinicalAiChunkStatus;
use App\Enums\ClinicalAiProcessStatus;
use App\Enums\ClinicalRecordStatus;
use App\Models\AccessGroup;
use App\Models\Patient;
use App\Models\PatientAssessment;
use App\Models\PatientEvolution;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClinicalRecordLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_edit_and_cancel_completed_assessments_and_evolutions(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'andre@fisio1.com.br')->firstOrFail();
        $patient = $this->patient();
        $assessment = PatientAssessment::query()->create([
            'patient_id' => $patient->id,
            'professional_id' => $admin->id,
            'assessed_at' => '2026-08-10',
            'status' => ClinicalRecordStatus::Completed,
            'chief_complaint' => 'Dor lombar',
        ]);
        $evolution = PatientEvolution::query()->create([
            'patient_id' => $patient->id,
            'professional_id' => $admin->id,
            'evolved_at' => '2026-08-12',
            'status' => ClinicalRecordStatus::Completed,
            'pain_level' => 7,
        ]);

        $this->assertTrue($admin->hasPermission('clinical_records.cancel'));

        $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/assessments/'.$assessment->id, [
            'patient_id' => $patient->id,
            'assessed_at' => '2026-08-10',
            'chief_complaint' => 'Dor lombar em melhora',
        ])->assertOk()
            ->assertJsonPath('data.status', ClinicalRecordStatus::Completed->value)
            ->assertJsonPath('data.chief_complaint', 'Dor lombar em melhora');

        $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/evolutions/'.$evolution->id, [
            'patient_id' => $patient->id,
            'evolved_at' => '2026-08-12',
            'pain_level' => 4,
            'observations' => 'Registro corrigido depois da conclusão.',
        ])->assertOk()
            ->assertJsonPath('data.status', ClinicalRecordStatus::Completed->value)
            ->assertJsonPath('data.pain_level', 4);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/assessments/'.$assessment->id.'/cancel', [
            'reason' => 'Registro duplicado.',
        ])->assertOk()
            ->assertJsonPath('data.status', ClinicalRecordStatus::Cancelled->value)
            ->assertJsonPath('data.cancelled_by', $admin->id)
            ->assertJsonPath('data.cancellation_reason', 'Registro duplicado.');

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/evolutions/'.$evolution->id.'/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', ClinicalRecordStatus::Cancelled->value);

        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditEventCategory::AssessmentCancelled->value,
            'auditable_id' => $assessment->id,
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditEventCategory::EvolutionCancelled->value,
            'auditable_id' => $evolution->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/assessments/'.$assessment->id, [
            'chief_complaint' => 'Alteração indevida',
        ])->assertUnprocessable()->assertJsonPath(
            'message',
            'Somente avaliações em revisão ou concluídas podem ser editadas.',
        );
    }

    public function test_cancelling_a_pending_record_stops_its_ai_process_and_removes_audio_files(): void
    {
        $this->seed();
        Storage::fake('local');
        $admin = User::query()->where('email', 'andre@fisio1.com.br')->firstOrFail();
        $assessment = PatientAssessment::query()->create([
            'patient_id' => $this->patient()->id,
            'professional_id' => $admin->id,
            'assessed_at' => now()->toDateString(),
            'status' => ClinicalRecordStatus::Pending,
        ]);
        Storage::disk('local')->put('audio/original.ogg', 'audio');
        Storage::disk('local')->put('audio/chunk-0.ogg', 'chunk');
        $process = $assessment->aiProcess()->create([
            'status' => ClinicalAiProcessStatus::Transcribing,
            'audio_disk' => 'local',
            'audio_path' => 'audio/original.ogg',
            'original_name' => 'consulta.ogg',
            'mime_type' => 'audio/ogg',
            'size' => 5,
            'chunks_count' => 1,
        ]);
        $chunk = $process->chunks()->create([
            'sequence' => 0,
            'status' => ClinicalAiChunkStatus::Pending,
            'disk' => 'local',
            'path' => 'audio/chunk-0.ogg',
            'mime_type' => 'audio/ogg',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/assessments/'.$assessment->id.'/cancel')
            ->assertOk();

        $this->assertSame(ClinicalAiProcessStatus::Cancelled, $process->fresh()->status);
        $this->assertSame(ClinicalAiChunkStatus::Cancelled, $chunk->fresh()->status);
        Storage::disk('local')->assertMissing('audio/original.ogg');
        Storage::disk('local')->assertMissing('audio/chunk-0.ogg');
    }

    public function test_user_without_cancel_permission_cannot_cancel_a_record(): void
    {
        $this->seed();
        $professional = User::factory()->create();
        $group = AccessGroup::query()->create(['name' => 'Fisioterapeuta']);
        $group->permissions()->sync(Permission::query()->whereIn('key', [
            'clinical_records.view',
            'clinical_records.update',
        ])->pluck('id'));
        $professional->accessGroups()->attach($group);
        $assessment = PatientAssessment::query()->create([
            'patient_id' => $this->patient()->id,
            'professional_id' => $professional->id,
            'assessed_at' => now()->toDateString(),
            'status' => ClinicalRecordStatus::Completed,
        ]);

        $this->actingAs($professional, 'sanctum')
            ->postJson('/api/v1/assessments/'.$assessment->id.'/cancel')
            ->assertForbidden();
    }

    private function patient(): Patient
    {
        return Patient::query()->create([
            'name' => 'Paciente de teste',
            'document' => fake()->unique()->numerify('###########'),
            'birth_date' => '1990-01-01',
            'phone' => '85999999999',
        ]);
    }
}
