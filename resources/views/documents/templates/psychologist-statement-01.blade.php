<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Declaração do Psicólogo</title>
    <style>
        /* Estilos básicos para DOMPDF */
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.6;
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
            margin-bottom: 25px;
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
            margin: 10px 0 20px;
            font-style: italic;
        }

        /* Quadro de identificação: resume paciente e profissional antes do texto */
        .identification {
            background: #f5f5f5;
            border-left: 4px solid #333;
            padding: 12px 18px;
            margin: 10px 0 25px;
        }

        .identification-row {
            margin: 4px 0;
        }

        .info-label {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #555;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 12pt;
            font-weight: bold;
        }

        .patient-name {
            text-transform: uppercase;
        }

        /* Corpo do texto */
        .declaration-text {
            margin: 20px 0;
            text-align: justify;
        }

        .declaration-text p {
            margin: 0 0 16px;
        }

        /* Campos para preenchimento manual (quando o dado não é informado) */
        .fill-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            height: 16px;
        }

        .fill-line.short {
            width: 90px;
        }

        .fill-line.medium {
            width: 170px;
        }

        .fill-line.full {
            display: block;
            width: 100%;
            height: 22px;
            margin-bottom: 6px;
        }

        .text-subline {
            border-bottom: 1px solid;
            text-transform: uppercase;
            padding: 0 20px;
        }

        /* Nota de sigilo */
        .confidentiality {
            border: 1px solid #999;
            padding: 10px 14px;
            margin: 25px 0 0;
            font-size: 10pt;
            text-align: justify;
            color: #333;
        }

        /* Assinatura */
        .signature {
            margin-top: 55px;
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
        <div class="document-title">DECLARAÇÃO DO PSICÓLOGO</div>
    </div>

    @php($date = now()->setTimezone($user->timezone))
    <!-- Data -->
    <div class="date">
        <span class="text-subline">{{ $settings?->cidade ? $settings->cidade.', ' : '' }}{{ $date->day }} de {{ firstLetterCapitalized($date->getTranslatedMonthName()) }} de {{ $date->year }}</span>
    </div>

    <!-- Identificação: paciente e profissional em destaque -->
    <div class="identification">
        <div class="identification-row">
            <div class="info-label">Paciente</div>
            <div class="info-value patient-name">
                @if(!empty($data['patient_name']))
                    {{ $data['patient_name'] }}
                @else
                    <span class="fill-line full"></span>
                @endif
            </div>
        </div>
        <div class="identification-row">
            <div class="info-label">Psicólogo(a) responsável</div>
            <div class="info-value">{{ $user->name }} &middot; CRP {{ formatCRP($settings?->crp ?: $user->crp) }}</div>
        </div>
    </div>

    <!-- Corpo da declaração -->
    <div class="declaration-text">
        <p>
            Declaro, para os devidos fins, que
            <strong class="patient-name">{{ !empty($data['patient_name']) ? $data['patient_name'] : '' }}</strong>
            @if(empty($data['patient_name']))
                <span class="fill-line medium"></span>
            @endif
            encontra-se em acompanhamento psicológico com este(a) profissional desde
            @if(!empty($data['date']))
                <strong>{{ $data['date'] }}</strong>,
            @else
                <span class="fill-line short"></span>,
            @endif
            com atendimentos
            @php($frequency = !empty($data['frequency']) ? \App\Enums\DocumentFrequencyEnum::tryFrom($data['frequency']) : null)
            @if($frequency)
                <strong>{{ $frequency->adjective() }}</strong>.
            @else
                <span class="fill-line medium"></span>.
            @endif
        </p>

        <p>Esta declaração é emitida a pedido do(a) interessado(a), com a seguinte finalidade:</p>

        @if(!empty($data['text_1']))
            <p>{!! nl2br(e($data['text_1'])) !!}</p>
        @else
            <span class="fill-line full"></span>
            <span class="fill-line full"></span>
        @endif
    </div>

    <!-- Nota de sigilo -->
    <div class="confidentiality">
        <strong>Sigilo profissional:</strong> esta declaração atesta apenas a existência do acompanhamento psicológico,
        não contendo diagnóstico, hipótese diagnóstica ou informações sobre o conteúdo dos atendimentos,
        conforme o Código de Ética Profissional do Psicólogo.
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
