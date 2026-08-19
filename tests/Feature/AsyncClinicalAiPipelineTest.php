<?php

namespace Tests\Feature;

use App\Enums\ClinicalRecordStatus;
use App\Jobs\SplitClinicalAudioJob;
use App\Models\Patient;
use App\Models\PatientAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AsyncClinicalAiPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_audio_upload_creates_pending_record_and_dispatches_processing(): void
    {
        $this->seed();
        Queue::fake();
        Storage::fake('local');
        $user = User::where('email', 'andre@fisio1.com.br')->firstOrFail();
        $patient = $this->patient();

        $response = $this->actingAs($user, 'sanctum')->post('/api/v1/clinical-ai/process-audio', [
            'patient_id' => $patient->id,
            'type' => 'initial_assessment',
            'performed_at' => now()->toDateString(),
            'audio' => UploadedFile::fake()->create('atendimento.ogg', 1024, 'audio/ogg'),
        ]);

        $response->assertAccepted()
            ->assertJsonPath('data.status', ClinicalRecordStatus::Pending->value)
            ->assertJsonPath('data.ai_process.status', 'pending');

        $assessment = PatientAssessment::query()->firstOrFail();
        $this->assertNull($assessment->chief_complaint);
        Storage::disk('local')->assertExists($assessment->aiProcess->audio_path);
        Queue::assertPushed(SplitClinicalAudioJob::class, fn (SplitClinicalAudioJob $job) => $job->processId === $assessment->aiProcess->id);
    }

    public function test_professional_can_confirm_an_assessment_in_review(): void
    {
        $this->seed();
        $user = User::where('email', 'andre@fisio1.com.br')->firstOrFail();
        $assessment = PatientAssessment::create([
            'patient_id' => $this->patient()->id,
            'professional_id' => $user->id,
            'assessed_at' => now(),
            'status' => ClinicalRecordStatus::InReview,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/assessments/'.$assessment->id.'/confirm', ['chief_complaint' => 'Dor lombar revisada'])
            ->assertOk()
            ->assertJsonPath('data.status', ClinicalRecordStatus::Completed->value)
            ->assertJsonPath('data.chief_complaint', 'Dor lombar revisada');

        $assessment->refresh();
        $this->assertSame($user->id, $assessment->confirmed_by);
        $this->assertNotNull($assessment->confirmed_at);
    }

    private function patient(): Patient
    {
        return Patient::create([
            'name' => 'Paciente de teste',
            'document' => fake()->unique()->numerify('###########'),
            'birth_date' => '1990-01-01',
            'phone' => '85999999999',
        ]);
    }
}
