<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório clínico - {{ $patient->name }}</title>
    <style>
        @page { margin: 94px 36px 54px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #13233b; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.48; }
        .document-header { position: fixed; z-index: 900; top: -76px; right: 0; left: 0; height: 57px; border-radius: 10px; background: #074673; }
        .logo { position: fixed; z-index: 1000; top: -66px; left: 16px; width: 108px; height: auto; }
        .clinic-name { color: #ffffff; font-size: 15px; font-weight: 700; letter-spacing: .2px; }
        .clinic-subtitle { color: #9fd9fa; font-size: 7px; letter-spacing: 1px; text-transform: uppercase; }
        .header-title { position: fixed; z-index: 1000; top: -66px; right: 16px; color: #ffffff; font-size: 10px; font-weight: 700; text-align: right; }
        .header-date { position: fixed; z-index: 1000; top: -50px; right: 16px; color: #ffffff; font-size: 8px; text-align: right; }
        .document-footer { position: fixed; right: 0; bottom: -37px; left: 0; padding-top: 8px; border-top: 1px solid #dbe7f0; color: #6c7d93; font-size: 7px; }
        .document-footer table { width: 100%; border-collapse: collapse; }
        .document-footer td { padding: 0; }
        .page-number { text-align: right; }
        .page-number:after { content: counter(page); }
        .hero { padding: 20px 22px; border-radius: 13px; background: #eef7fc; }
        .eyebrow { color: #0798d4; font-size: 7px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; }
        h1 { margin: 5px 0 3px; color: #0e1c31; font-size: 23px; line-height: 1.15; }
        .hero-copy { margin: 0; color: #63758d; font-size: 9px; }
        .hero-id { margin-top: 12px; padding-top: 10px; border-top: 1px solid #d7e8f2; color: #52667f; }
        .hero-id strong { color: #17314f; }
        .section { margin-top: 18px; }
        .section-heading { margin-bottom: 8px; }
        .section-heading h2 { margin: 2px 0 0; color: #10243e; font-size: 14px; }
        .section-heading p { margin: 2px 0 0; color: #72839a; font-size: 8px; }
        .patient-table, .stats-table { width: 100%; border-collapse: separate; border-spacing: 6px; margin: -6px; }
        .patient-table td { width: 33.333%; padding: 10px; border: 1px solid #e0e9f0; border-radius: 8px; background: #ffffff; vertical-align: top; }
        .field-label { display: block; margin-bottom: 3px; color: #698097; font-size: 6.5px; font-weight: 700; letter-spacing: .45px; text-transform: uppercase; }
        .field-value { color: #182a42; font-size: 9px; font-weight: 700; }
        .stats-table td { width: 25%; padding: 11px; border-radius: 9px; background: #074673; color: #ffffff; vertical-align: top; }
        .stats-table td:nth-child(2) { background: #086fa7; }
        .stats-table td:nth-child(3) { background: #098fc2; }
        .stats-table td:nth-child(4) { background: #167c68; }
        .stat-label { display: block; color: #ccecfb; font-size: 6.5px; text-transform: uppercase; }
        .stat-value { display: block; margin: 4px 0 2px; font-size: 17px; font-weight: 700; line-height: 1; }
        .stat-detail { color: #e8f7fd; font-size: 7px; }
        .journey-intro { margin: 20px 0 10px; padding: 11px 14px; border-left: 4px solid #0aa9df; background: #f2f8fc; }
        .journey-intro strong { display: block; color: #0f2d4a; font-size: 11px; }
        .journey-intro span { color: #677b91; }
        .record { margin: 0 0 12px; padding: 13px 14px; border: 1px solid #dfe9f0; border-left: 4px solid #0a92c9; border-radius: 9px; page-break-inside: auto; }
        .record.evolution { border-left-color: #d99a26; }
        .record-header { width: 100%; border-collapse: collapse; margin-bottom: 9px; }
        .record-header td { padding: 0; vertical-align: top; }
        .record-number { display: inline-block; min-width: 24px; margin-right: 7px; padding: 5px 7px; border-radius: 6px; color: #056895; background: #e7f5fc; font-size: 8px; font-weight: 700; text-align: center; }
        .evolution .record-number { color: #865a08; background: #fff4d9; }
        .record-title { color: #10243e; font-size: 12px; font-weight: 700; }
        .record-meta { margin-top: 2px; color: #6c7e93; font-size: 7.5px; }
        .record-status { text-align: right; }
        .status { display: inline-block; padding: 4px 7px; border-radius: 20px; color: #247a58; background: #e8f6ef; font-size: 6.5px; font-weight: 700; }
        .status.in_review { color: #8c620f; background: #fff3d8; }
        .status.pending { color: #176da0; background: #e5f4fc; }
        .status.failed { color: #a33a46; background: #fcebed; }
        .status.cancelled { color: #687586; background: #edf1f4; }
        .pain-box { margin-bottom: 9px; padding: 8px 10px; border-radius: 7px; background: #f4f8fa; }
        .pain-table { width: 100%; border-collapse: collapse; }
        .pain-table td { padding: 0; vertical-align: middle; }
        .pain-label { width: 112px; color: #64798f; font-size: 7px; }
        .pain-track { width: 100%; height: 6px; border-radius: 6px; background: #dfe8ec; }
        .pain-fill { height: 6px; border-radius: 6px; background: #da9d2f; }
        .pain-value { width: 46px; color: #162b44; font-size: 10px; font-weight: 700; text-align: right; }
        .clinical-field { margin-top: 8px; padding-top: 8px; border-top: 1px solid #edf2f5; page-break-inside: avoid; }
        .clinical-field:first-child { margin-top: 0; padding-top: 0; border-top: 0; }
        .clinical-field h3 { margin: 0 0 3px; color: #087aaf; font-size: 8px; }
        .evolution .clinical-field h3 { color: #97680e; }
        .clinical-field p { margin: 0; color: #2c4058; white-space: pre-line; }
        .attachments { margin-top: 9px; padding: 7px 9px; border-radius: 6px; color: #5d7086; background: #f4f7f9; font-size: 7px; }
        .attachments strong { color: #243b54; }
        .patient-notes { margin-top: 8px; padding: 10px 12px; border: 1px solid #e2eaf0; border-radius: 8px; background: #fbfcfd; }
        .empty { padding: 24px; border: 1px dashed #ccdbe5; border-radius: 10px; color: #6d8197; background: #f8fbfd; text-align: center; }
        .signature { margin-top: 26px; page-break-inside: avoid; }
        .signature-line { width: 250px; margin: 0 auto; padding-top: 7px; border-top: 1px solid #8191a2; color: #43576d; text-align: center; }
        .signature-line strong { display: block; color: #172c44; }
    </style>
</head>
<body>
@php
    $formatDate = fn ($date) => $date?->format('d/m/Y') ?? 'Não informado';
    $statusLabels = ['pending' => 'Processando', 'in_review' => 'Em revisão', 'completed' => 'Concluído', 'failed' => 'Falha', 'cancelled' => 'Cancelado'];
    $assessmentLabels = [
        'chief_complaint' => 'Queixa principal',
        'condition_history' => 'História da condição atual',
        'life_habits' => 'Hábitos de vida',
        'personal_family_history' => 'Histórico pessoal e familiar',
        'previous_treatments' => 'Tratamentos anteriores',
        'physical_examination' => 'Exame físico',
        'complementary_exams' => 'Exames complementares',
        'physical_therapy_diagnosis' => 'Diagnóstico fisioterapêutico',
        'cbdf' => 'CBDF',
        'planned_sessions' => 'Sessões planejadas',
        'resources_methods_techniques' => 'Recursos, métodos e técnicas',
        'therapeutic_objectives' => 'Objetivos terapêuticos',
        'physical_therapy_prognosis' => 'Prognóstico fisioterapêutico',
    ];
    $evolutionLabels = [
        'daily_complaint' => 'Queixa do dia',
        'home_guidance_adherence' => 'Adesão às orientações domiciliares',
        'therapeutic_conduct' => 'Conduta terapêutica',
        'session_final_impression' => 'Impressão ao final da sessão',
        'observations' => 'Observações',
    ];
    $evolutionNumber = 0;
@endphp

<div class="document-header"></div>
@if ($logoDataUri)
    <img class="logo" src="{{ $logoDataUri }}" alt="Fisio1 Fisioterapia">
@else
    <div class="clinic-name">FISIO1</div><div class="clinic-subtitle">Fisioterapia</div>
@endif
<div class="header-title">Relatório clínico do paciente</div>
<div class="header-date">Emitido em {{ $generatedAt->format('d/m/Y') }} às {{ $generatedAt->format('H:i') }}</div>

<footer class="document-footer">
    <table><tr><td>FISIO1 Fisioterapia | Documento clínico confidencial</td><td class="page-number">Página </td></tr></table>
</footer>

<main>
    <section class="hero">
        <span class="eyebrow">Jornada do paciente</span>
        <h1>{{ $patient->name }}</h1>
        <p class="hero-copy">Visão consolidada do acompanhamento fisioterapêutico, da avaliação inicial às evoluções mais recentes.</p>
        <div class="hero-id">
            <strong>Documento:</strong> {{ $patient->document }}
            &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Nascimento:</strong> {{ $formatDate($patient->birth_date) }}
            @if ($patient->birth_date)
                &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Idade:</strong> {{ $patient->birth_date->age }} anos
            @endif
        </div>
    </section>

    <section class="section">
        <div class="section-heading"><span class="eyebrow">Identificação</span><h2>Informações do paciente</h2></div>
        <table class="patient-table">
            <tr>
                <td><span class="field-label">Telefone</span><span class="field-value">{{ $patient->phone ?: 'Não informado' }}</span></td>
                <td><span class="field-label">E-mail</span><span class="field-value">{{ $patient->email ?: 'Não informado' }}</span></td>
                <td><span class="field-label">Profissão</span><span class="field-value">{{ $patient->profession ?: 'Não informado' }}</span></td>
            </tr>
            <tr>
                <td><span class="field-label">Gênero</span><span class="field-value">{{ $patient->gender ?: 'Não informado' }}</span></td>
                <td><span class="field-label">Estado civil</span><span class="field-value">{{ $patient->marital_status ?: 'Não informado' }}</span></td>
                <td><span class="field-label">Naturalidade</span><span class="field-value">{{ $patient->birthplace ?: 'Não informado' }}</span></td>
            </tr>
            <tr>
                <td colspan="2"><span class="field-label">Endereço</span><span class="field-value">{{ $patient->address ?: 'Não informado' }}</span></td>
                <td><span class="field-label">Indicação</span><span class="field-value">{{ $patient->indication ?: 'Não informado' }}</span></td>
            </tr>
        </table>
        @if (filled($patient->notes))
            <div class="patient-notes"><span class="field-label">Observações gerais</span>{{ $patient->notes }}</div>
        @endif
    </section>

    <section class="section">
        <div class="section-heading"><span class="eyebrow">Resumo clínico</span><h2>Panorama do acompanhamento</h2></div>
        <table class="stats-table"><tr>
            <td><span class="stat-label">Registros</span><span class="stat-value">{{ $summary['total_records'] }}</span><span class="stat-detail">Total no prontuário</span></td>
            <td><span class="stat-label">Avaliações</span><span class="stat-value">{{ $summary['total_assessments'] }}</span><span class="stat-detail">Avaliações iniciais</span></td>
            <td><span class="stat-label">Evoluções</span><span class="stat-value">{{ $summary['total_evolutions'] }}</span><span class="stat-detail">Sessões registradas</span></td>
            <td>
                <span class="stat-label">Evolução da dor</span>
                @if ($summary['initial_pain_level'] !== null && $summary['current_pain_level'] !== null)
                    <span class="stat-value">{{ $summary['initial_pain_level'] }} para {{ $summary['current_pain_level'] }}</span>
                    <span class="stat-detail">
                        @if ($summary['pain_change'] > 0) Redução de {{ $summary['pain_change'] }} ponto(s)
                        @elseif ($summary['pain_change'] < 0) Aumento de {{ abs($summary['pain_change']) }} ponto(s)
                        @else Nível estável
                        @endif
                    </span>
                @else
                    <span class="stat-value">-</span><span class="stat-detail">Sem medições</span>
                @endif
            </td>
        </tr></table>
    </section>

    <div class="journey-intro">
        <strong>Linha do tempo clínica</strong>
        <span>Registros apresentados do mais antigo ao mais recente
            @if ($summary['first_record_at'] && $summary['last_record_at'])
                | período de {{ $formatDate($summary['first_record_at']) }} a {{ $formatDate($summary['last_record_at']) }}
            @endif
        </span>
    </div>

    @forelse ($timeline as $entry)
        @php
            $record = $entry['record'];
            $isEvolution = $entry['type'] === 'evolution';
            if ($isEvolution) $evolutionNumber++;
            $fields = $isEvolution ? $evolutionLabels : $assessmentLabels;
            $status = $record->status->value;
        @endphp
        <article class="record {{ $isEvolution ? 'evolution' : '' }}">
            <table class="record-header"><tr>
                <td>
                    <span class="record-number">{{ $isEvolution ? $evolutionNumber : 'A' }}</span>
                    <span class="record-title">{{ $isEvolution ? 'Evolução '.$evolutionNumber : 'Avaliação inicial' }}</span>
                    <div class="record-meta">{{ $formatDate($entry['date']) }} | Profissional: {{ $record->professional?->name ?? 'Não informado' }} | {{ $record->attachments->count() }} anexo(s)</div>
                </td>
                <td class="record-status"><span class="status {{ $status }}">{{ $statusLabels[$status] ?? $status }}</span></td>
            </tr></table>

            @if ($status === 'cancelled' && filled($record->cancellation_reason))
                <div class="clinical-field"><h3>Motivo do cancelamento</h3><p>{{ $record->cancellation_reason }}</p></div>
            @endif

            @if ($isEvolution && $record->pain_level !== null)
                <div class="pain-box"><table class="pain-table"><tr>
                    <td class="pain-label">Nível de dor nesta sessão</td>
                    <td><div class="pain-track"><div class="pain-fill" style="width: {{ max(0, min(100, $record->pain_level * 10)) }}%"></div></div></td>
                    <td class="pain-value">{{ $record->pain_level }}/10</td>
                </tr></table></div>
            @endif

            @foreach ($fields as $field => $label)
                @if (filled($record->{$field}))
                    <div class="clinical-field"><h3>{{ $label }}</h3><p>{{ $record->{$field} }}</p></div>
                @endif
            @endforeach

            @if ($record->attachments->isNotEmpty())
                <div class="attachments"><strong>Anexos:</strong> {{ $record->attachments->pluck('original_name')->join(', ') }}</div>
            @endif
        </article>
    @empty
        <div class="empty"><strong>Nenhum registro clínico cadastrado.</strong><br>As avaliações e evoluções do paciente aparecerão aqui quando forem registradas.</div>
    @endforelse

    <div class="signature"><div class="signature-line"><strong>{{ $generatedBy?->name ?? 'Profissional responsável' }}</strong>Relatório emitido pelo sistema FISIO1</div></div>
</main>
</body>
</html>
