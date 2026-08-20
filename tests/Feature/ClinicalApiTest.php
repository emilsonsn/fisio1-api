<?php

namespace Tests\Feature;

use App\Models\AccessGroup;
use App\Models\Patient;
use App\Models\PatientAssessment;
use App\Models\PatientEvolution;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_authenticate_and_manage_a_patient_record(): void
    {
        $this->seed();
        $user = User::where('email', 'andre@fisio1.com.br')->firstOrFail();
        $token = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'andre'])->assertOk()->assertJsonFragment(['groups.manage'])->json('data.token');

        $headers = ['Authorization' => 'Bearer '.$token];
        $patientId = $this->postJson('/api/v1/patients', [
            'name' => 'Maria Silva',
            'document' => '12345678900',
            'birth_date' => '1990-01-01',
            'phone' => '85999999999',
            'indication' => 'Dr. Carlos',
            'birthplace' => 'Fortaleza/CE',
            'marital_status' => 'Casada',
            'gender' => 'Feminino',
            'profession' => 'Professora',
            'address' => 'Rua das Flores, 100',
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.indication', 'Dr. Carlos')
            ->assertJsonPath('data.birthplace', 'Fortaleza/CE')
            ->assertJsonPath('data.marital_status', 'Casada')
            ->assertJsonPath('data.gender', 'Feminino')
            ->assertJsonPath('data.profession', 'Professora')
            ->assertJsonPath('data.address', 'Rua das Flores, 100')
            ->json('data.id');

        $this->patchJson('/api/v1/patients/'.$patientId, [
            'profession' => 'Coordenadora pedagógica',
            'address' => 'Avenida Central, 200',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.profession', 'Coordenadora pedagógica')
            ->assertJsonPath('data.address', 'Avenida Central, 200');

        $this->postJson('/api/v1/assessments', [
            'patient_id' => $patientId,
            'assessed_at' => now()->toDateString(),
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('data.indication', 'Dr. Carlos')
            ->assertJsonPath('data.birthplace', 'Fortaleza/CE')
            ->assertJsonPath('data.profession', 'Coordenadora pedagógica')
            ->assertJsonPath('data.address', 'Avenida Central, 200');

        $this->postJson('/api/v1/clinical-records', ['patient_id' => $patientId, 'type' => 'initial_assessment', 'performed_at' => now()->toDateString(), 'pain_level' => 6, 'complaint' => 'Dor lombar', 'conduct' => 'Exercícios terapêuticos'], $headers)->assertCreated()->assertJsonPath('data.patient.id', $patientId)->assertJsonPath('data.professional.id', $user->id);
    }

    public function test_administrator_can_create_group_with_permissions_and_assign_it_to_a_user(): void
    {
        $this->seed();
        $token = User::where('email', 'andre@fisio1.com.br')->firstOrFail()->createToken('test')->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];
        $permissionIds = Permission::whereIn('key', ['patients.view', 'clinical_records.view'])->pluck('id')->all();

        $groupId = $this->postJson('/api/v1/groups', ['name' => 'Consulta', 'permission_ids' => $permissionIds], $headers)->assertCreated()->assertJsonCount(2, 'data.permissions')->json('data.id');
        $this->postJson('/api/v1/users', ['name' => 'Profissional de teste', 'email' => 'profissional@example.com', 'password' => 'secret123', 'access_group_ids' => [$groupId]], $headers)->assertCreated()->assertJsonPath('data.access_groups.0.name', 'Consulta');
    }

    public function test_only_an_administrator_is_seeded_with_permission_to_delete_patients(): void
    {
        $this->seed();
        $administrator = User::where('email', 'andre@fisio1.com.br')->firstOrFail();
        $this->assertTrue($administrator->hasPermission('patients.delete'));

        $professional = User::factory()->create();
        $viewGroup = AccessGroup::create(['name' => 'Consulta de pacientes']);
        $viewGroup->permissions()->attach(Permission::where('key', 'patients.view')->firstOrFail());
        $professional->accessGroups()->attach($viewGroup);
        $this->assertFalse($professional->hasPermission('patients.delete'));

        $patient = Patient::create([
            'name' => 'Paciente removível',
            'document' => '45678912300',
            'birth_date' => '1991-02-03',
            'phone' => '85977778888',
        ]);
        $assessment = PatientAssessment::create([
            'patient_id' => $patient->id,
            'professional_id' => $administrator->id,
            'assessed_at' => now()->toDateString(),
        ]);

        $this->actingAs($professional, 'sanctum')
            ->deleteJson('/api/v1/patients/'.$patient->id)
            ->assertForbidden();

        $this->actingAs($administrator, 'sanctum')
            ->deleteJson('/api/v1/patients/'.$patient->id)
            ->assertNoContent();

        $this->assertSoftDeleted('patients', ['id' => $patient->id]);
        $this->actingAs($administrator, 'sanctum')
            ->getJson('/api/v1/assessments')
            ->assertOk()
            ->assertJsonPath('data.0.id', $assessment->id)
            ->assertJsonPath('data.0.patient.name', 'Paciente removível')
            ->assertJsonPath('data.0.patient.is_deleted', true);
    }

    public function test_patient_history_is_returned_from_oldest_to_newest_with_progress_summary(): void
    {
        $this->seed();
        $administrator = User::where('email', 'andre@fisio1.com.br')->firstOrFail();
        $patient = Patient::create([
            'name' => 'Paciente em acompanhamento',
            'document' => '65432198700',
            'birth_date' => '1988-06-15',
            'phone' => '85966667777',
        ]);
        PatientAssessment::create([
            'patient_id' => $patient->id,
            'professional_id' => $administrator->id,
            'assessed_at' => '2026-06-01',
            'chief_complaint' => 'Dor lombar ao caminhar',
            'physical_therapy_diagnosis' => 'Limitação funcional lombar',
        ]);
        PatientEvolution::create([
            'patient_id' => $patient->id,
            'professional_id' => $administrator->id,
            'evolved_at' => '2026-06-20',
            'daily_complaint' => 'Dor leve após esforço',
            'pain_level' => 3,
        ]);
        PatientEvolution::create([
            'patient_id' => $patient->id,
            'professional_id' => $administrator->id,
            'evolved_at' => '2026-06-08',
            'daily_complaint' => 'Dor persistente',
            'pain_level' => 8,
        ]);

        $this->actingAs($administrator, 'sanctum')
            ->getJson('/api/v1/patients/'.$patient->id.'/history')
            ->assertOk()
            ->assertJsonPath('data.patient.id', $patient->id)
            ->assertJsonPath('data.summary.total_records', 3)
            ->assertJsonPath('data.summary.total_assessments', 1)
            ->assertJsonPath('data.summary.total_evolutions', 2)
            ->assertJsonPath('data.summary.initial_pain_level', 8)
            ->assertJsonPath('data.summary.current_pain_level', 3)
            ->assertJsonPath('data.summary.pain_change', 5)
            ->assertJsonPath('data.timeline.0.type', 'initial_assessment')
            ->assertJsonPath('data.timeline.0.recorded_at', '2026-06-01')
            ->assertJsonPath('data.timeline.1.recorded_at', '2026-06-08')
            ->assertJsonPath('data.timeline.2.recorded_at', '2026-06-20');
    }

    public function test_administrator_can_export_the_current_patient_history_as_pdf(): void
    {
        $this->seed();
        $administrator = User::where('email', 'andre@fisio1.com.br')->firstOrFail();
        $patient = Patient::create([
            'name' => 'Helena de Sousa',
            'document' => '74185296300',
            'birth_date' => '1985-04-12',
            'phone' => '85955554444',
        ]);
        PatientAssessment::create([
            'patient_id' => $patient->id,
            'professional_id' => $administrator->id,
            'assessed_at' => '2026-07-01',
            'chief_complaint' => 'Dor cervical e limitação para rotação.',
        ]);
        PatientEvolution::create([
            'patient_id' => $patient->id,
            'professional_id' => $administrator->id,
            'evolved_at' => '2026-07-08',
            'daily_complaint' => 'Menor desconforto ao trabalhar.',
            'pain_level' => 4,
        ]);

        $response = $this->actingAs($administrator, 'sanctum')
            ->get('/api/v1/patients/'.$patient->id.'/history.pdf');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=relatorio-clinico-helena-de-sousa.pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'patient.history_exported',
            'auditable_id' => $patient->id,
            'user_id' => $administrator->id,
        ]);
    }

    public function test_administrator_can_delete_only_custom_groups_without_users(): void
    {
        $this->seed();
        $administrator = User::where('email', 'andre@fisio1.com.br')->firstOrFail();
        $headers = ['Authorization' => 'Bearer '.$administrator->createToken('test')->plainTextToken];

        $emptyGroup = AccessGroup::create(['name' => 'Grupo temporário']);
        $this->deleteJson('/api/v1/groups/'.$emptyGroup->id, [], $headers)->assertNoContent();
        $this->assertDatabaseMissing('access_groups', ['id' => $emptyGroup->id]);

        $usedGroup = AccessGroup::create(['name' => 'Grupo em uso']);
        $administrator->accessGroups()->attach($usedGroup);
        $this->deleteJson('/api/v1/groups/'.$usedGroup->id, [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Remova os usuários vinculados antes de excluir o grupo.');

        $systemGroup = AccessGroup::where('is_system', true)->firstOrFail();
        $this->patchJson('/api/v1/groups/'.$systemGroup->id, ['name' => 'Administrador alterado'], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Grupos de sistema não podem ser alterados.');
        $this->deleteJson('/api/v1/groups/'.$systemGroup->id, [], $headers)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Grupos de sistema não podem ser removidos.');
    }

    public function test_inactive_user_cannot_authenticate_and_physiotherapist_cannot_manage_users(): void
    {
        $inactive = User::factory()->create(['is_active' => false, 'password' => 'secret123']);
        $this->postJson('/api/v1/auth/login', ['email' => $inactive->email, 'password' => 'secret123'])->assertForbidden();

        $physiotherapist = User::factory()->create(['password' => 'secret123']);
        $this->actingAs($physiotherapist, 'sanctum')->getJson('/api/v1/users')->assertForbidden();
    }
}
