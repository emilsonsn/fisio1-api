<?php

namespace Tests\Feature;

use App\Enums\AuditEventCategory;
use App\Enums\ClinicalRecordStatus;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PatientAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_filter_audit_logs_with_changed_fields(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'andre@fisio1.com.br')->firstOrFail();
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'andre',
        ])->assertOk()->json('data.token');
        $headers = ['Authorization' => 'Bearer '.$token];

        $patientId = $this->postJson('/api/v1/patients', [
            'name' => 'Paciente auditado',
            'document' => '12345678900',
            'birth_date' => '1990-01-01',
            'phone' => '85999999999',
            'profession' => 'Professora',
        ], $headers)->assertCreated()->json('data.id');

        $this->patchJson('/api/v1/patients/'.$patientId, [
            'profession' => 'Coordenadora',
            'phone' => '85988888888',
        ], $headers)->assertOk();

        $audit = AuditLog::query()
            ->where('event', AuditEventCategory::PatientUpdated->value)
            ->where('auditable_id', $patientId)
            ->firstOrFail();

        $this->assertSame('Professora', $audit->old_values['profession']);
        $this->assertSame('Coordenadora', $audit->new_values['profession']);
        $this->assertSame('85999999999', $audit->old_values['phone']);
        $this->assertSame('85988888888', $audit->new_values['phone']);
        $this->assertSame($admin->id, $audit->user_id);

        $this->getJson('/api/v1/audit-logs?'.http_build_query([
            'event' => AuditEventCategory::PatientUpdated->value,
            'user_id' => $admin->id,
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
        ]), $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', AuditEventCategory::PatientUpdated->value)
            ->assertJsonPath('data.0.event_label', 'Paciente editado')
            ->assertJsonPath('data.0.old_values.profession', 'Professora')
            ->assertJsonPath('data.0.new_values.profession', 'Coordenadora');

        $this->getJson('/api/v1/audit-logs/options', $headers)
            ->assertOk()
            ->assertJsonFragment(['value' => AuditEventCategory::Login->value, 'label' => 'Login realizado'])
            ->assertJsonFragment(['id' => $admin->id, 'email' => $admin->email]);
    }

    public function test_only_administrator_can_access_audit_logs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/audit-logs')
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/audit-logs/options')
            ->assertForbidden();
    }

    public function test_authentication_and_sensitive_user_changes_are_audited_safely(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'andre@fisio1.com.br')->firstOrFail();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'inexistente@example.com',
            'password' => 'invalid-password',
        ])->assertUnprocessable();

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email,
            'password' => 'andre',
        ])->assertOk()->json('data.token');
        $headers = ['Authorization' => 'Bearer '.$token];
        $groupId = $admin->accessGroups()->firstOrFail()->id;

        $userId = $this->postJson('/api/v1/users', [
            'name' => 'Usuário auditado',
            'email' => 'auditoria@example.com',
            'password' => 'secret123',
            'access_group_ids' => [$groupId],
        ], $headers)->assertCreated()->json('data.id');

        $created = AuditLog::query()
            ->where('event', AuditEventCategory::UserCreated->value)
            ->where('auditable_id', $userId)
            ->firstOrFail();

        $this->assertSame('[REDACTED]', $created->new_values['password']);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditEventCategory::LoginFailed->value]);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditEventCategory::Login->value, 'user_id' => $admin->id]);

        $this->postJson('/api/v1/auth/logout', [], $headers)->assertNoContent();
        $this->assertDatabaseHas('audit_logs', ['event' => AuditEventCategory::Logout->value, 'user_id' => $admin->id]);
    }

    public function test_confirming_a_clinical_document_uses_the_specific_audit_category(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'andre@fisio1.com.br')->firstOrFail();
        $this->actingAs($admin, 'sanctum');
        $patient = Patient::query()->create([
            'name' => 'Paciente em revisão',
            'document' => '98765432100',
            'birth_date' => '1985-05-10',
            'phone' => '85977777777',
        ]);
        $assessment = PatientAssessment::query()->create([
            'patient_id' => $patient->id,
            'professional_id' => $admin->id,
            'assessed_at' => now()->toDateString(),
            'status' => ClinicalRecordStatus::InReview,
        ]);

        $this->postJson('/api/v1/assessments/'.$assessment->id.'/confirm', [
            'chief_complaint' => 'Queixa revisada',
        ])->assertOk();

        $audit = AuditLog::query()
            ->where('event', AuditEventCategory::AssessmentConfirmed->value)
            ->where('auditable_id', $assessment->id)
            ->firstOrFail();

        $this->assertSame(ClinicalRecordStatus::InReview->value, $audit->old_values['status']);
        $this->assertSame(ClinicalRecordStatus::Completed->value, $audit->new_values['status']);
        $this->assertSame('Queixa revisada', $audit->new_values['chief_complaint']);
    }
}
