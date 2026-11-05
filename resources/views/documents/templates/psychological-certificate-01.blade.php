<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Atestado Psicológico</title>
    <style>
        /* Estilos básicos para DOMPDF */
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.4;
            color: #000;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
            border: 1px solid #ccc;
            padding: 30px;
        }

        /* Cabeçalho simples */
        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .clinic-name {
            font-size: 18pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .document-title {
            font-size: 16pt;
            font-weight: bold;
            border-top: 1px solid #333;
            border-bottom: 1px solid #333;
            padding: 10px 0;
            margin: 15px 0;
        }

        /* Data */
        .date {
            text-align: right;
            margin: 20px 0;
            font-style: italic;
        }

        /* Corpo do texto */
        .declaration-text {
            margin: 30px 0;
            text-align: justify;
        }

        .patient-name {
            font-weight: bold;
            text-transform: uppercase;
        }

        /* Linhas para preenchimento */
        .info-line {
            margin: 15px 0;
            padding-bottom: 5px;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }

        .info-value {
            display: inline-block;
            margin-left: 10px;
        }

        .fill-line {
            border-bottom: 1px solid #000;
            height: 22px;
            margin-bottom: 8px;
        }

        /* Assinatura */
        .signature {
            margin-top: 50px;
            text-align: center;
        }

        .signature-line {
            width: 300px;
            margin: 0 auto;
            border-top: 1px solid #000;
            padding-top: 10px;
        }

        .psychologist {
            font-weight: bold;
            font-size: 11pt;
        }

        .credencial {
            font-size: 10pt;
            color: #666;
        }

        /* Rodapé */
        .footer {
            margin-top: 30px;
            font-size: 9pt;
            color: #666;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 15px;
        }

        .text-subline {
            border-bottom: 1px solid;
            text-transform: uppercase;
            padding: 0 20px;
        }

        .free-text {
            margin: 10px 0;
            text-align: justify;
        }

        .p-doc {
            margin: 20px 0;
        }
    </style>
</head>
<body>
<div class="container" style="background: #ffffff">
    @php($user = \Illuminate\Support\Facades\Auth::getUser())
    @php($settings = $configs['settings'] ?? null)
    @php($address = collect([
        $settings?->rua,
        $settings?->numero ? 'nº '.$settings->numero : null,
        $settings?->complemento,
        $settings?->bairro,
        $settings?->cep ? 'CEP '.$settings->cep : null,
    ])->filter()->implode(' - '))

    <div class="header">
        @if($settings?->clinic_name || $settings?->cnpj)
            @if($settings?->clinic_name)
                <div class="clinic-name">{{ $settings->clinic_name }}</div>
            @endif
            @if($settings?->cnpj)
                <div>CNPJ: {{ $settings->cnpj }}</div>
            @endif
        @endif
        <div class="document-title">ATESTADO PSICOLÓGICO</div>
    </div>

    @php($date = now()->setTimezone($user->timezone))
    <!-- Data -->
    <div class="date">
        <p><span class="text-subline">{{ $settings?->cidade ? $settings->cidade.', ' : '' }}{{ $date->day }} de {{ firstLetterCapitalized($date->getTranslatedMonthName()) }} de {{ $date->year }}</span></p>
    </div>

    <br/>

    <!-- Identificação do profissional -->
    <div class="info-line">
        <span class="info-label">Psicólogo(a):</span>
        <span class="info-value"><span class="text-subline">{{ $user->name }}</span> CRP: <span class="text-subline">{{ formatCRP($settings?->crp ?: $user->crp) }}</span></span>
    </div>

    <!-- Corpo do atestado -->
    <div class="declaration-text">
        <p>Atesto, para os devidos fins, que o(a) paciente
            <span class="patient-name text-subline">{{ !empty($data['patient_name']) ? $data['patient_name'] : '' }}</span>
            foi atendido(a) por este(a) profissional, em avaliação/acompanhamento psicológico, com a seguinte finalidade:</p>

        @if(!empty($data['text_1']))
            <p class="free-text">{!! nl2br(e($data['text_1'])) !!}</p>
        @else
            <div class="fill-line"></div>
        @endif

        <p class="p-doc">Descrição e conclusão da avaliação psicológica:</p>

        @if(!empty($data['text_2']))
            <p class="free-text">{!! nl2br(e($data['text_2'])) !!}</p>
        @else
            <div class="fill-line"></div>
            <div class="fill-line"></div>
            <div class="fill-line"></div>
        @endif
    </div>

    <!-- Área de assinatura -->
    <div class="signature">
        <div class="signature-line">
            <div class="psychologist">Assinatura do Psicólogo(a)</div>
            <div class="credencial">Carimbo e CRP</div>
        </div>
    </div>

    <!-- Rodapé -->
    <div class="footer">
        <p>Documento elaborado conforme a Resolução CFP nº 06/2019</p>
        @if($address || $user->whatsapp)
            <p>{{ $address }}{{ $address && $user->whatsapp ? ' - ' : '' }}{{ $user->whatsapp ? 'Tel: '.$user->whatsapp : '' }}</p>
        @endif
    </div>
</div>
</body>
</html>
