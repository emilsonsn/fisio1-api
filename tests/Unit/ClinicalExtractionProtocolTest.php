<?php

namespace Tests\Unit;

use App\Services\Gemini\ClinicalExtractionProtocol;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ClinicalExtractionProtocolTest extends TestCase
{
    public function test_assessment_prompt_preserves_the_full_clinical_protocol(): void
    {
        $protocol = new ClinicalExtractionProtocol;
        $prompt = $protocol->prompt(ClinicalExtractionProtocol::INITIAL_ASSESSMENT);

        foreach (['Não relatado.', 'Não avaliado.', 'Anamnese', 'ADM', 'dinamometria', 'testes ortopédicos', 'baropodometria', 'diagnóstico cinesiofuncional', 'frequência semanal'] as $term) {
            $this->assertStringContainsStringIgnoringCase($term, $prompt);
        }

        $this->assertSame('Não avaliado.', $protocol->missingValue(ClinicalExtractionProtocol::INITIAL_ASSESSMENT, 'physical_examination'));
        $this->assertNull($protocol->missingValue(ClinicalExtractionProtocol::INITIAL_ASSESSMENT, 'planned_sessions'));
    }

    public function test_evolution_prompt_requires_detailed_session_information(): void
    {
        $protocol = new ClinicalExtractionProtocol;
        $prompt = $protocol->prompt(ClinicalExtractionProtocol::EVOLUTION);

        foreach (['relato subjetivo', 'achados objetivos', 'séries', 'repetições', 'carga', 'resposta pós-sessão', 'sessões anteriores'] as $term) {
            $this->assertStringContainsStringIgnoringCase($term, $prompt);
        }

        $this->assertSame('Não relatado.', $protocol->missingValue(ClinicalExtractionProtocol::EVOLUTION, 'therapeutic_conduct'));
        $this->assertSame('Não avaliado.', $protocol->missingValue(ClinicalExtractionProtocol::EVOLUTION, 'observations'));
    }

    public function test_it_rejects_an_unknown_record_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ClinicalExtractionProtocol)->prompt('unknown');
    }
}
