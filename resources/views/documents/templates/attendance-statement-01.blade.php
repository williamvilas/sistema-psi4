<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Declaração de Comparecimento</title>
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

        /* Tabela simples */
        .schedule {
            margin: 25px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f0f0f0;
            font-weight: bold;
            border: 1px solid #999;
            padding: 8px;
            text-align: center;
        }

        td {
            border: 1px solid #999;
            padding: 8px;
            text-align: center;
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
        <div class="document-title">DECLARAÇÃO DE COMPARECIMENTO</div>
    </div>

    @php($date = now()->setTimezone($user->timezone))
    <!-- Data e número -->
    <div class="date">
        <p><span class="text-subline">{{ $settings?->cidade ? $settings->cidade.', ' : '' }}{{ $date->day }} de {{ firstLetterCapitalized($date->getTranslatedMonthName()) }} de {{ $date->year }}</span></p>
    </div>

    <!-- Corpo da declaração -->
    <div class="declaration-text">
        <p>Declaramos para os devidos fins que o(a) paciente
            <span class="patient-name text-subline">{{ !empty($data['patient_name']) ? $data['patient_name'] : '' }}</span>,
            compareceu às sessões de psicoterapia conforme detalhamento abaixo:</p>
    </div>

    <!-- Informações básicas -->
    <div class="info-line">
        <span class="info-label">Psicólogo(a):</span>
        <span class="info-value"><span class="text-subline">{{ $user->name }}</span> CRP: <span class="text-subline">{{ formatCRP($settings?->crp ?: $user->crp) }}</span></span>
    </div>

    <br/>

    <!-- Tabela de sessões -->
    <div class="schedule">
        <table>
            <thead>
            <tr>
                <th>Data</th>
                <th>Horário</th>
            </tr>
            </thead>
            <tbody>
            @if(!empty($data['agenda_check']))
                @foreach($data['agenda_check'] as $v)
                    @php($dates = explode('|', $v))
                    <tr>
                        @if(!empty($dates[0]) && !empty($dates[1]) && !empty($dates[2]))
                            <td>{{ formatDate($dates[0], 'd/m/Y') }}</td>
                            <td>{{ $dates[1] }} às {{ $dates[2] }}</td>
                        @else
                            <td>___/___/______</td>
                            <td>____:____ às ____:____</td>
                        @endif
                    </tr>
                @endforeach
            @else
                <tr>
                    <td>___/___/______</td>
                    <td>____:____ às ____:____</td>
                </tr>
            @endif
            </tbody>
        </table>
    </div>

    <br/>

    <!-- Total de sessões -->
    @php($totalSessions = !empty($data['agenda_check']) ? count($data['agenda_check']) : 0)

    <p class="p-doc">
        <strong>Total de sessões:</strong>
        @if($totalSessions > 0)
            <span class="text-subline">{{ $totalSessions }}</span>
            (<span class="text-subline">{{ numberToWords($totalSessions) }}</span>)
        @else
            ______ (___________) sessões
        @endif
    </p>

    <!-- Área de assinatura -->
    <div class="signature">
        <div class="signature-line">
            <div class="psychologist">Assinatura do Psicólogo(a)</div>
            <div class="credencial">Carimbo e CRP</div>
        </div>
    </div>

    <!-- Rodapé -->
    <div class="footer">
        <p>Documento válido para comprovação de comparecimento</p>
        @if($address || $user->whatsapp)
            <p>{{ $address }}{{ $address && $user->whatsapp ? ' - ' : '' }}{{ $user->whatsapp ? 'Tel: '.$user->whatsapp : '' }}</p>
        @endif
    </div>
</div>
</body>
</html>
