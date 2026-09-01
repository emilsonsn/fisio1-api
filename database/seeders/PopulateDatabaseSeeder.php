<?php

namespace Database\Seeders;

use App\Enums\ClinicalRecordStatus;
use App\Models\ClinicalRecord;
use App\Models\Patient;
use App\Models\PatientAssessment;
use App\Models\PatientEvolution;
use App\Models\User;
use Illuminate\Database\Seeder;

class PopulateDatabaseSeeder extends Seeder
{
    /**
     * Populate the local database with representative clinical data.
     *
     * This seeder does not delete existing records and can be run repeatedly.
     */
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);

        $professional = User::where('email', 'andre@fisio1.com.br')->firstOrFail();

        $patients = collect([
            [
                'name' => 'Mariana Costa',
                'document' => '123.456.789-01',
                'birth_date' => '1988-04-15',
                'phone' => '(85) 99991-0101',
                'email' => 'mariana.costa@example.test',
                'indication' => 'Ortopedista',
                'birthplace' => 'Fortaleza - CE',
                'marital_status' => 'Casada',
                'gender' => 'Feminino',
                'profession' => 'Analista financeira',
                'address' => 'Aldeota, Fortaleza - CE',
                'notes' => 'Prefere atendimentos no período da manhã.',
            ],
            [
                'name' => 'João Pedro Almeida',
                'document' => '234.567.890-12',
                'birth_date' => '1979-09-22',
                'phone' => '(85) 99992-0202',
                'email' => 'joao.almeida@example.test',
                'indication' => 'Indicação de familiar',
                'birthplace' => 'Sobral - CE',
                'marital_status' => 'Casado',
                'gender' => 'Masculino',
                'profession' => 'Professor',
                'address' => 'Cocó, Fortaleza - CE',
                'notes' => 'Acompanhar retorno gradual às atividades físicas.',
            ],
            [
                'name' => 'Camila Rodrigues',
                'document' => '345.678.901-23',
                'birth_date' => '1994-01-08',
                'phone' => '(85) 99993-0303',
                'email' => 'camila.rodrigues@example.test',
                'indication' => 'Academia',
                'birthplace' => 'Juazeiro do Norte - CE',
                'marital_status' => 'Solteira',
                'gender' => 'Feminino',
                'profession' => 'Designer',
                'address' => 'Meireles, Fortaleza - CE',
                'notes' => null,
            ],
        ])->map(fn (array $attributes): Patient => Patient::updateOrCreate(
            ['document' => $attributes['document']],
            $attributes,
        ));

        $mariana = $patients->firstWhere('document', '123.456.789-01');
        $joao = $patients->firstWhere('document', '234.567.890-12');
        $camila = $patients->firstWhere('document', '345.678.901-23');

        $this->seedAssessment($mariana, $professional, now()->subWeeks(5)->toDateString(), 'Dor lombar recorrente após jornada prolongada sentada.');
        $this->seedEvolution($mariana, $professional, now()->subWeeks(4)->toDateString(), 7, 'Dor lombar ao final do expediente.');
        $this->seedEvolution($mariana, $professional, now()->subWeeks(3)->toDateString(), 5, 'Relata redução da dor e melhor tolerância ao trabalho.');
        $this->seedEvolution($mariana, $professional, now()->subWeek()->toDateString(), 2, 'Sem dor em repouso; desconforto leve após longos períodos sentada.');

        $this->seedAssessment($joao, $professional, now()->subWeeks(3)->toDateString(), 'Dor no ombro direito durante movimentos acima da cabeça.');
        $this->seedEvolution($joao, $professional, now()->subWeeks(2)->toDateString(), 6, 'Mantém limitação em elevação do membro superior direito.');
        $this->seedEvolution($joao, $professional, now()->subDays(5)->toDateString(), 4, 'Evolução favorável, com melhora da amplitude de movimento.');

        $this->seedAssessment($camila, $professional, now()->subWeek()->toDateString(), 'Dor anterior no joelho esquerdo após corrida.');
        $this->seedEvolution($camila, $professional, now()->subDays(2)->toDateString(), 5, 'Dor presente apenas após treino de corrida.');

        foreach ([
            [$mariana, 'evolution', now()->subDays(3)->toDateString(), 2, 'Reavaliação de dor lombar e progressão de exercícios.'],
            [$joao, 'evolution', now()->subDays(5)->toDateString(), 4, 'Acompanhamento da mobilidade do ombro direito.'],
            [$camila, 'initial_assessment', now()->subWeek()->toDateString(), 6, 'Avaliação inicial de joelho esquerdo.'],
            [$camila, 'evolution', now()->toDateString(), 5, 'Revisão de exercícios para retorno à corrida.'],
        ] as [$patient, $type, $performedAt, $painLevel, $complaint]) {
            ClinicalRecord::updateOrCreate(
                ['patient_id' => $patient->id, 'type' => $type, 'performed_at' => $performedAt],
                [
                    'professional_id' => $professional->id,
                    'pain_level' => $painLevel,
                    'complaint' => $complaint,
                    'history' => 'Quadro acompanhado em fisioterapia.',
                    'functional_limitations' => 'Limitação leve nas atividades relacionadas à queixa.',
                    'treatment_objective' => 'Reduzir a dor e recuperar a função.',
                    'physical_assessment' => 'Avaliação compatível com o quadro relatado.',
                    'conduct' => 'Exercícios terapêuticos e orientações posturais.',
                    'next_steps' => 'Manter plano terapêutico e reavaliar na próxima sessão.',
                    'observations' => 'Registro demonstrativo para ambiente local.',
                    'reviewed_at' => now(),
                ],
            );
        }
    }

    private function seedAssessment(Patient $patient, User $professional, string $assessedAt, string $chiefComplaint): void
    {
        PatientAssessment::updateOrCreate(
            ['patient_id' => $patient->id, 'assessed_at' => $assessedAt],
            [
                'professional_id' => $professional->id,
                ...$patient->only(Patient::DEMOGRAPHIC_FIELDS),
                'chief_complaint' => $chiefComplaint,
                'condition_history' => 'Início gradual, sem sinais de alerta.',
                'life_habits' => 'Mantém rotina ativa e sono regular.',
                'personal_family_history' => 'Sem antecedentes relevantes para o quadro atual.',
                'previous_treatments' => 'Uso eventual de analgésico e orientações médicas.',
                'physical_examination' => 'Sensibilidade e limitação funcional compatíveis com a queixa.',
                'complementary_exams' => 'Sem exames complementares anexados.',
                'physical_therapy_diagnosis' => 'Disfunção musculoesquelética em acompanhamento.',
                'cbdf' => 'Classificação funcional compatível com limitação leve a moderada.',
                'planned_sessions' => 10,
                'resources_methods_techniques' => 'Cinesioterapia, terapia manual e educação em dor.',
                'therapeutic_objectives' => 'Reduzir dor, recuperar mobilidade e promover autonomia.',
                'physical_therapy_prognosis' => 'Favorável com adesão ao plano terapêutico.',
                'status' => ClinicalRecordStatus::Completed,
                'confirmed_by' => $professional->id,
                'confirmed_at' => now(),
            ],
        );
    }

    private function seedEvolution(Patient $patient, User $professional, string $evolvedAt, int $painLevel, string $dailyComplaint): void
    {
        PatientEvolution::updateOrCreate(
            ['patient_id' => $patient->id, 'evolved_at' => $evolvedAt],
            [
                'professional_id' => $professional->id,
                'daily_complaint' => $dailyComplaint,
                'pain_level' => $painLevel,
                'home_guidance_adherence' => 'Refere boa adesão aos exercícios domiciliares.',
                'therapeutic_conduct' => 'Exercícios terapêuticos com progressão conforme tolerância.',
                'session_final_impression' => 'Sessão bem tolerada, sem intercorrências.',
                'observations' => 'Registro demonstrativo para ambiente local.',
                'status' => ClinicalRecordStatus::Completed,
                'confirmed_by' => $professional->id,
                'confirmed_at' => now(),
            ],
        );
    }
}
