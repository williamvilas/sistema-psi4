<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório Psicológico</title>
    <style>
        /* Estilos básicos para DOMPDF */

        /* Documento de várias páginas: mesma margem em todas as páginas (o container só define as laterais); a de baixo reserva o espaço do rodapé fixo */
        @page {
            margin: 50px 0 110px;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #000;
            margin: 0;
            padding: 0 20px;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
            border: 1px solid #ccc;
            padding: 0 30px;
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

        /* Quadro de identificação: resume paciente e profissional antes do conteúdo */
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

        /* Seções numeradas: guiam a leitura e o preenchimento */
        .section {
            margin: 0 0 22px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #222;
            border-bottom: 1px solid #999;
            padding-bottom: 3px;
            margin: 0 0 6px;
        }

        .section-number {
            display: inline-block;
            width: 22px;
            height: 22px;
            line-height: 22px;
            margin-right: 6px;
            background: #333;
            color: #fff;
            font-size: 10pt;
            text-align: center;
        }

        /* Orientação de preenchimento: dica curta sob o título da seção */
        .section-hint {
            font-size: 9pt;
            font-style: italic;
            color: #666;
            margin: 0 0 8px;
        }

        /* Campos para preenchimento manual */
        .fill-line {
            border-bottom: 1px solid #000;
            height: 22px;
            margin-bottom: 6px;
        }

        .fill-line-inline {
            display: inline-block;
            width: 170px;
            height: 16px;
            border-bottom: 1px solid #000;
        }

        .text-subline {
            border-bottom: 1px solid;
            text-transform: uppercase;
            padding: 0 20px;
        }

        /* Data */
        .date {
            text-align: right;
            margin: 25px 0 0;
            font-style: italic;
        }

        /* Nota de sigilo */
        .confidentiality {
            border: 1px solid #999;
            padding: 10px 14px;
            margin: 25px 0 0;
            font-size: 9pt;
            text-align: justify;
            color: #333;
            page-break-inside: avoid;
        }

        /* Assinatura */
        .signature {
            margin-top: 55px;
            text-align: center;
            page-break-inside: avoid;
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

        .free-text {
            margin: 6px 0 0;
            text-align: justify;
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
        <div class="document-title">RELATÓRIO PSICOLÓGICO</div>
    </div>

    <!-- Identificação: paciente e profissional em destaque -->
    <div class="identification">
        <div class="identification-row">
            <div class="info-label">Paciente</div>
            <div class="info-value patient-name">
                @if(!empty($data['patient_name']))
                    {{ $data['patient_name'] }}
                @else
                    <div class="fill-line"></div>
                @endif
            </div>
        </div>
        <div class="identification-row">
            <div class="info-label">Psicólogo(a) responsável</div>
            <div class="info-value">{{ $user->name }} &middot; CRP {{ formatCRP($settings?->crp ?: $user->crp) }}</div>
        </div>
        <div class="identification-row">
            <div class="info-label">Solicitante</div>
            <div class="info-value">
                @if(!empty($data['text_6']))
                    {!! nl2br(e($data['text_6'])) !!}
                @else
                    <span class="fill-line-inline" style="width: 300px"></span>
                @endif
            </div>
        </div>
        <div class="identification-row">
            <div class="info-label">Finalidade</div>
            <div class="info-value">
                @if(!empty($data['text_5']))
                    {!! nl2br(e($data['text_5'])) !!}
                @else
                    <span class="fill-line-inline" style="width: 300px"></span>
                @endif
            </div>
        </div>
    </div>

    <!-- 1. Descrição da demanda -->
    <div class="section">
        <div class="section-title"><span class="section-number">1</span>Descrição da demanda</div>
        <p class="section-hint">Quem solicitou o relatório, qual o motivo e o objetivo do acompanhamento.</p>
        @if(!empty($data['text_1']))
            <p class="free-text">{!! nl2br(e($data['text_1'])) !!}</p>
        @else
            <div class="fill-line"></div>
            <div class="fill-line"></div>
            <div class="fill-line"></div>
        @endif
    </div>

    <!-- 2. Procedimento -->
    <div class="section">
        <div class="section-title"><span class="section-number">2</span>Procedimento</div>
        <p class="section-hint">Recursos e instrumentos utilizados, número de encontros e período de atendimento.</p>
        @if(!empty($data['text_2']))
            <p class="free-text">{!! nl2br(e($data['text_2'])) !!}</p>
        @else
            <div class="fill-line"></div>
            <div class="fill-line"></div>
            <div class="fill-line"></div>
        @endif
    </div>

    <!-- 3. Análise -->
    <div class="section">
        <div class="section-title"><span class="section-number">3</span>Análise</div>
        <p class="section-hint">Exposição descritiva e fundamentada das informações relevantes ao caso, sem detalhar o conteúdo das sessões.</p>
        @if(!empty($data['text_3']))
            <p class="free-text">{!! nl2br(e($data['text_3'])) !!}</p>
        @else
            <div class="fill-line"></div>
            <div class="fill-line"></div>
            <div class="fill-line"></div>
            <div class="fill-line"></div>
            <div class="fill-line"></div>
        @endif
    </div>

    <!-- 4. Conclusão -->
    <div class="section">
        <div class="section-title"><span class="section-number">4</span>Conclusão</div>
        <p class="section-hint">Síntese do processo, encaminhamentos, intervenções e sugestões de projeto terapêutico.</p>
        @if(!empty($data['text_4']))
            <p class="free-text">{!! nl2br(e($data['text_4'])) !!}</p>
        @else
            <div class="fill-line"></div>
            <div class="fill-line"></div>
            <div class="fill-line"></div>
        @endif
    </div>

    @php($date = now()->setTimezone($user->timezone))
    <!-- Local e data -->
    <div class="date">
        <span class="text-subline">{{ $settings?->cidade ? $settings->cidade.', ' : '' }}{{ $date->day }} de {{ firstLetterCapitalized($date->getTranslatedMonthName()) }} de {{ $date->year }}</span>
    </div>

    <!-- Nota de sigilo -->
    <div class="confidentiality">
        <strong>Sigilo profissional:</strong> este relatório é de uso restrito ao(à) solicitante e à finalidade indicada.
        Deve conter apenas as informações necessárias à demanda, conforme o Código de Ética Profissional do Psicólogo.
        A reprodução ou o repasse a terceiros não é autorizado sem o consentimento do(a) paciente ou de seu responsável.
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
