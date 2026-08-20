<?php

namespace App\Services\Gemini;

use InvalidArgumentException;

final class ClinicalExtractionProtocol
{
    public const INITIAL_ASSESSMENT = 'initial_assessment';

    public const EVOLUTION = 'evolution';

    public function prompt(string $type): string
    {
        return match ($type) {
            self::INITIAL_ASSESSMENT => $this->assessmentPrompt(),
            self::EVOLUTION => $this->evolutionPrompt(),
            default => throw new InvalidArgumentException("Tipo de prontuário clínico inválido: {$type}."),
        };
    }

    public function fieldDefinitions(string $type): array
    {
        return match ($type) {
            self::INITIAL_ASSESSMENT => $this->assessmentFields(),
            self::EVOLUTION => $this->evolutionFields(),
            default => throw new InvalidArgumentException("Tipo de prontuário clínico inválido: {$type}."),
        };
    }

    public function missingValue(string $type, string $field): ?string
    {
        if (in_array($field, ['pain_level', 'planned_sessions'], true)) {
            return null;
        }

        $notEvaluated = $type === self::INITIAL_ASSESSMENT
            ? ['physical_examination', 'complementary_exams', 'physical_therapy_diagnosis', 'cbdf', 'physical_therapy_prognosis']
            : ['session_final_impression', 'observations'];

        return in_array($field, $notEvaluated, true) ? 'Não avaliado.' : 'Não relatado.';
    }

    private function assessmentPrompt(): string
    {
        return <<<'PROMPT'
Atue como um Fisioterapeuta Especialista e assistente clínico de alta precisão. Sua tarefa é extrair as informações da transcrição de uma avaliação inicial e organizá-las rigorosamente nos campos do protocolo da clínica fornecidos no schema JSON.

REGRAS OBRIGATÓRIAS
1. Use exclusivamente informações explicitamente presentes na transcrição. Não invente, não presuma e não complete dados por conhecimento geral.
2. Elimine repetições, hesitações, gírias e vícios de linguagem, corrigindo apenas a forma do texto sem alterar o sentido clínico.
3. Preserve todo detalhe clinicamente relevante: lateralidade, localização anatômica, duração, mecanismo, intensidade, frequência, unidades, graus, cargas, repetições, parâmetros, nomes de testes e seus resultados.
4. Mantenha a terminologia técnica de fisioterapia e, quando o áudio trouxer descrição leiga seguida de explicação técnica do profissional, redija o resultado em linguagem clínica fiel ao conteúdo informado.
5. Não reduza informações distintas a frases genéricas. Produza respostas completas, técnicas, formais, objetivas e suficientemente detalhadas para compor um prontuário.
6. Organize cada informação no campo mais adequado e evite duplicações desnecessárias entre campos.
7. Não diagnostique por conta própria. Registre diagnóstico cinesiofuncional, prognóstico, objetivos e condutas somente quando estiverem declarados ou formulados pelo profissional na transcrição.
8. Para campos de relato ou histórico não mencionados, retorne exatamente "Não relatado.". Para exames, testes, diagnóstico, classificação ou prognóstico não realizados ou não mencionados, retorne exatamente "Não avaliado.". Para campos numéricos ausentes, retorne null.
9. Nunca transforme expressões vagas em valores exatos. Por exemplo, "dor forte" não autoriza inferir um valor de EVA; "algumas sessões" não autoriza inferir planned_sessions.
10. Retorne somente o JSON definido pelo schema, sem comentários, Markdown ou campos adicionais.

PROTOCOLO DA AVALIAÇÃO INICIAL
- Identificação mencionada: indicação/encaminhamento, naturalidade, estado civil, gênero, profissão e endereço. Nome, telefone, idade e data pertencem ao cadastro do paciente/registro e não devem ser inseridos em campos clínicos diferentes.
- Anamnese: queixa principal; história da doença atual, incluindo início, mecanismo, tempo e evolução; comportamento, localização, lateralidade e caráter da dor; fatores de melhora e piora; EVA somente se numericamente informada; dor eventual, intermitente ou constante; sintomas associados e limitações funcionais.
- Antecedentes: histórico patológico e cirúrgico, comorbidades, medicamentos, antecedentes pessoais e familiares, hábitos de vida e tratamentos prévios com suas respostas.
- Exame físico: inspeção, palpação, postura, edema, amplitude de movimento (ADM) com direção e graus, força muscular ou dinamometria, testes ortopédicos/específicos com seus resultados, exame neurológico e demais achados objetivos.
- Avaliação funcional e tecnológica: marcha, corrida, baropodometria, testes funcionais e diagnóstico cinesiofuncional, quando efetivamente mencionados.
- Plano terapêutico: objetivos, recursos, métodos, técnicas, parâmetros, frequência semanal, número planejado de sessões e prognóstico, somente conforme informado.
PROMPT;
    }

    private function evolutionPrompt(): string
    {
        return <<<'PROMPT'
Atue como um Fisioterapeuta Especialista e assistente clínico de alta precisão. Sua tarefa é transformar a transcrição de uma sessão em uma evolução fisioterapêutica completa, técnica e cronologicamente coerente, organizando o conteúdo nos campos fornecidos no schema JSON.

REGRAS OBRIGATÓRIAS
1. Use exclusivamente informações explicitamente presentes nesta transcrição. Não invente, não presuma, não acrescente condutas e não reutilize dados de sessões anteriores.
2. Elimine repetições, hesitações, gírias e vícios de linguagem, corrigindo apenas a forma do texto sem alterar seu significado.
3. Preserve todo detalhe clínico: localização e lateralidade, escala de dor, sintomas, achados objetivos, nomes e resultados de testes, amplitudes, graus, cargas, séries, repetições, tempo, frequência, parâmetros dos equipamentos, progressões/regressões e resposta às intervenções.
4. Não resuma informações clínicas diferentes em frases genéricas. Redija cada campo com linguagem profissional, formal, direta e com detalhe suficiente para continuidade segura do tratamento.
5. Separe claramente: relato subjetivo do paciente, achados objetivos/reavaliação, condutas executadas, resposta pós-sessão e plano/orientações.
6. Registre dor numérica em pain_level somente quando houver valor explícito de 0 a 10. Descrições como "leve" ou "intensa" permanecem no texto e não devem ser convertidas em número.
7. Não conclua melhora, piora, diagnóstico, tolerância ou causalidade sem sustentação na transcrição. Preserve incertezas declaradas pelo profissional.
8. Para relatos, adesão ou condutas não mencionados, retorne exatamente "Não relatado.". Para achados objetivos ou impressão final não avaliados/não mencionados, retorne exatamente "Não avaliado.". Para pain_level ausente, retorne null.
9. Organize cada informação no campo mais adequado e evite duplicações desnecessárias.
10. Retorne somente o JSON definido pelo schema, sem comentários, Markdown ou campos adicionais.

PROTOCOLO DA EVOLUÇÃO
- Queixa do dia: sintomas desde a sessão anterior e no atendimento atual, localização, lateralidade, intensidade, padrão/comportamento, fatores de melhora ou piora, intercorrências e limitações funcionais.
- Adesão às orientações domiciliares: atividade prescrita, frequência/adesão relatada, dificuldades, tolerância e resposta percebida.
- Conduta terapêutica: todas as intervenções efetivamente realizadas, região/lado, recursos, técnicas, exercícios, terapia manual, educação, dosagem e parâmetros, progressões/regressões e tolerância durante a sessão.
- Impressão final: resposta imediatamente após a sessão, mudanças relatadas ou mensuradas em dor, mobilidade e função, eventos adversos, evolução clínica e próximo plano somente quando mencionados.
- Observações e reavaliação objetiva: inspeção, palpação, ADM, força/dinamometria, marcha, testes ortopédicos/funcionais e resultados, precauções, contraindicações e fatos relevantes que não pertençam aos demais campos.
PROMPT;
    }

    private function assessmentFields(): array
    {
        return [
            'indication' => 'Indicação ou origem do encaminhamento mencionada na avaliação.',
            'birthplace' => 'Naturalidade explicitamente informada.',
            'marital_status' => 'Estado civil explicitamente informado.',
            'gender' => 'Gênero explicitamente informado.',
            'profession' => 'Profissão e atividade ocupacional mencionadas.',
            'address' => 'Endereço explicitamente informado.',
            'chief_complaint' => 'Queixa principal com região, lateralidade, sintomas, intensidade informada e impacto funcional.',
            'condition_history' => 'HDA: início, mecanismo, duração, evolução, comportamento da dor, frequência, fatores de melhora/piora, sintomas associados e limitações.',
            'life_habits' => 'Atividade física, rotina, sono, tabagismo, etilismo e outros hábitos relatados.',
            'personal_family_history' => 'Antecedentes pessoais e familiares, comorbidades, cirurgias, medicamentos e precauções mencionados.',
            'previous_treatments' => 'Tratamentos anteriores, duração e resposta obtida, quando informados.',
            'physical_examination' => 'Inspeção, palpação, postura, edema, ADM, força/dinamometria, testes ortopédicos, neurológicos e funcionais com medidas e resultados.',
            'complementary_exams' => 'Exames de imagem ou laboratoriais e respectivos achados mencionados.',
            'physical_therapy_diagnosis' => 'Diagnóstico cinesiofuncional formulado pelo profissional, sem inferências do modelo.',
            'cbdf' => 'Classificação/descrição CBDF somente quando mencionada.',
            'planned_sessions' => 'Número total inteiro de sessões planejadas, somente se explicitamente informado.',
            'resources_methods_techniques' => 'Condutas propostas, recursos, métodos, técnicas, parâmetros e frequência semanal informados.',
            'therapeutic_objectives' => 'Objetivos terapêuticos informados, preservando horizonte de curto, médio ou longo prazo quando houver.',
            'physical_therapy_prognosis' => 'Prognóstico fisioterapêutico e expectativa funcional explicitamente avaliados pelo profissional.',
        ];
    }

    private function evolutionFields(): array
    {
        return [
            'daily_complaint' => 'Relato subjetivo atual: sintomas, localização/lateralidade, comportamento, mudanças, intercorrências e limitações funcionais.',
            'pain_level' => 'Valor inteiro explícito da escala de dor de 0 a 10; null quando não houver valor numérico.',
            'home_guidance_adherence' => 'Orientações domiciliares prescritas, adesão/frequência, dificuldades, tolerância e resposta relatadas.',
            'therapeutic_conduct' => 'Intervenções realizadas com região/lado, técnica, exercício/recurso, séries, repetições, carga, duração, parâmetros, progressão e tolerância.',
            'session_final_impression' => 'Resposta pós-sessão, mudanças clínicas ou funcionais, tolerância, eventos adversos e próximo plano quando mencionados.',
            'observations' => 'Achados objetivos/reavaliação, ADM, força, palpação, marcha, testes e resultados, precauções e demais informações relevantes.',
        ];
    }
}
