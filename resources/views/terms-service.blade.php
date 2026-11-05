@extends('layouts.legal')
@section('title', 'Termos de Uso')
@section('content')
    @php
        $company = config('info.legal.company_name');
        $document = config('info.legal.company_document');
    @endphp

    <div class="mb-8">
        <p class="mb-4">Estes <strong>Termos de Uso</strong> regulam o uso do {{ config('app.name') }} ("Plataforma"), um sistema de gestão para psicólogos e profissionais de saúde que atendem online, oferecido por {{ $company }}@if($document) (CNPJ {{ $document }})@endif. Ao criar uma conta ou usar a Plataforma, você declara que leu e concorda com estes Termos e com a <a href="{{ route('privacy-policy') }}" target="_blank" class="text-teal-700 hover:underline">Política de Privacidade</a>.</p>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">1. O que a Plataforma oferece</h2>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li>Agenda e link de agendamento para o paciente marcar a consulta sozinho;</li>
            <li>Mensagens automáticas pelo WhatsApp: confirmação, lembretes, link da sala de vídeo e cobrança;</li>
            <li>Sala de vídeo para atendimento online;</li>
            <li>Gravação da sessão, somente com o aceite do paciente, e geração de notas com inteligência artificial;</li>
            <li>Prontuários, cadastro de pacientes e emissão de documentos;</li>
            <li>Cobrança com desconto por pagamento rápido e envio automático do comprovante;</li>
            <li>Suporte humano pelo WhatsApp.</li>
        </ul>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">2. Cadastro e conta</h2>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li>Você deve fornecer informações verdadeiras e mantê-las atualizadas.</li>
            <li>É necessário ser maior de 18 anos e estar habilitado a exercer a sua profissão.</li>
            <li>Você é responsável pela guarda da sua senha e por toda atividade feita na sua conta. Recomendamos ativar a autenticação em duas etapas.</li>
            <li>Avise-nos imediatamente se suspeitar de acesso indevido.</li>
        </ul>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">3. Planos e pagamentos</h2>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li>A Plataforma tem o plano <strong>Grátis</strong>, com limites mensais de agendamentos, pacientes e prontuários, e o plano <strong>Automático</strong>, pago e sem esses limites, com as automações descritas na página inicial.</li>
            <li>Valores, limites e recursos de cada plano ficam visíveis na página de planos e podem mudar, com aviso prévio.</li>
            <li>Os pagamentos de planos e das cobranças aos pacientes são processados por um gateway de pagamento parceiro. Não armazenamos dados completos de cartão.</li>
            <li>O desconto por pagamento em até 15 minutos é definido pela Plataforma e aplicado automaticamente na cobrança enviada ao paciente.</li>
        </ul>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">4. Responsabilidades do profissional</h2>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li>Você é o responsável pelo atendimento, pelo conteúdo dos prontuários e pelo cumprimento do código de ética e das normas do seu conselho profissional, incluindo o sigilo.</li>
            <li>Em relação aos dados dos seus pacientes, você atua como <strong>controlador</strong> e a Plataforma como <strong>operadora</strong>, nos termos da LGPD.</li>
            <li>Você deve informar ao paciente, e obter o consentimento dele, sobre o uso da Plataforma, o recebimento de mensagens pelo WhatsApp e a gravação da sessão. <strong>Nenhuma sessão deve ser gravada sem o aceite do paciente.</strong></li>
            <li>Você deve ter autorização para cadastrar os dados dos pacientes e para usar a Plataforma com eles.</li>
        </ul>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">5. Inteligência artificial</h2>
        <p class="mb-4">As notas geradas por IA são apenas <strong>sugestões</strong>. Elas ficam pendentes até você aprovar, editar ou rejeitar. A decisão clínica e o conteúdo final do prontuário são sempre de sua responsabilidade. A IA pode errar, e por isso a revisão é obrigatória.</p>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">6. Uso aceitável</h2>
        <p class="mb-2">É proibido:</p>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li>usar a Plataforma para fins ilegais, fraudulentos ou que violem direitos de terceiros;</li>
            <li>enviar mensagens abusivas, spam ou sem relação com o atendimento;</li>
            <li>tentar acessar dados de outras contas, burlar limites ou comprometer a segurança do sistema;</li>
            <li>copiar, revender ou fazer engenharia reversa da Plataforma.</li>
        </ul>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">7. Propriedade intelectual</h2>
        <p class="mb-4">A Plataforma, sua marca e seu código pertencem a {{ $company }}. Os dados e documentos que você insere continuam sendo seus. Concedemos a você uma licença limitada, não exclusiva e revogável para usar a Plataforma enquanto sua conta estiver ativa.</p>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">8. Disponibilidade e limitação de responsabilidade</h2>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li>Trabalhamos para manter a Plataforma disponível, mas ela pode ficar indisponível por manutenção ou por falhas de terceiros, como WhatsApp, gateway de pagamento e provedores de IA.</li>
            <li>Não nos responsabilizamos por decisões clínicas, pelo conteúdo inserido pelo profissional ou por prejuízos decorrentes do uso indevido da conta.</li>
            <li>Nossa responsabilidade, quando existir, é limitada ao valor pago pelo plano nos 12 meses anteriores ao fato, salvo se a lei determinar o contrário.</li>
        </ul>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">9. Cancelamento e encerramento</h2>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li>Você pode deixar de usar a Plataforma ou pedir o encerramento da conta a qualquer momento pelo suporte.</li>
            <li>Podemos suspender ou encerrar contas que violem estes Termos, com aviso quando possível.</li>
            <li>Após o encerramento, os dados são tratados conforme a Política de Privacidade. Solicite a exportação dos seus dados antes de encerrar. Prontuários podem exigir guarda mínima por obrigação profissional, que é sua responsabilidade como controlador.</li>
        </ul>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">10. Alterações nos Termos</h2>
        <p class="mb-4">Podemos atualizar estes Termos. Quando a mudança for relevante, avisaremos pela Plataforma ou por e-mail. Usar a Plataforma depois da atualização significa que você concorda com a nova versão.</p>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">11. Lei aplicável e foro</h2>
        <p class="mb-4">Estes Termos seguem as leis do Brasil. @if(config('info.legal.forum'))Fica eleito o foro da comarca de {{ config('info.legal.forum') }}, @else Fica eleito o foro do domicílio do usuário, @endif sem prejuízo dos direitos do consumidor, quando aplicáveis.</p>
    </div>
@endsection
