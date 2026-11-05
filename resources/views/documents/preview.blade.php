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
            border-bottom: 1px dotted #999;
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
    <!-- Cabeçalho -->
    <div class="header">
        <div class="clinic-name">Clínica de Psicologia</div>
        <div>CNPJ: 12.345.678/0001-90</div>
        <div class="document-title">DECLARAÇÃO DE COMPARECIMENTO</div>
    </div>
    @php($user = \Illuminate\Support\Facades\Auth::getUser())
    @php($date = now()->setTimezone($user->timezone))
    <!-- Data e número -->
    <div class="date">
        <p><span class="text-subline">{{ $date->day }} de {{ firstLetterCapitalized($date->getTranslatedMonthName()) }} de {{ $date->year }}</span></p>
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
        <span class="info-value"><span class="text-subline">{{ $user->name }}</span> CRP: <span class="text-subline">{{ formatCRP($user->crp) }}</span></span>
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
                        @php($dates = explode('*', $v))
                        <tr>
                            <td>{{ $dates[0] }}</td>
                            <td>{{ $dates[1] }} às {{ $dates[2] }}</td>
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
            _{{ $totalSessions }}_ (_{{ numberToWords($totalSessions) }}_)
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
        <p>Rua Exemplo, 123 - Centro - Cidade/UF - Tel: (XX) XXXX-XXXX</p>
    </div>
</div>
</body>
</html>
