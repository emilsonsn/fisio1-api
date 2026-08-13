<?php

namespace Tests\Feature;

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
        $patientId = $this->postJson('/api/v1/patients', ['name' => 'Maria Silva', 'document' => '12345678900', 'birth_date' => '1990-01-01', 'phone' => '85999999999'], $headers)->assertCreated()->json('data.id');

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

    public function test_inactive_user_cannot_authenticate_and_physiotherapist_cannot_manage_users(): void
    {
        $inactive = User::factory()->create(['is_active' => false, 'password' => 'secret123']);
        $this->postJson('/api/v1/auth/login', ['email' => $inactive->email, 'password' => 'secret123'])->assertForbidden();

        $physiotherapist = User::factory()->create(['password' => 'secret123']);
        $this->actingAs($physiotherapist, 'sanctum')->getJson('/api/v1/users')->assertForbidden();
    }
}
