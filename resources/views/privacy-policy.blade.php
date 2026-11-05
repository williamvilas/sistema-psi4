@extends('layouts.legal')
@section('title', 'Política de Privacidade')
@section('content')
    @php
        $company = config('info.legal.company_name');
        $document = config('info.legal.company_document');
    @endphp

    <div class="mb-8">
        <p class="mb-4">Esta Política explica como o {{ config('app.name') }}, operado por {{ $company }}@if($document) (CNPJ {{ $document }})@endif, trata dados pessoais, em conformidade com a <strong>Lei Geral de Proteção de Dados (LGPD, Lei 13.709/2018)</strong>.</p>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">1. Quem é quem</h2>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li><strong>Dados do profissional</strong> (nome, e-mail, CRP, dados de uso e de pagamento do plano): somos o <strong>controlador</strong>.</li>
            <li><strong>Dados dos pacientes</strong> inseridos pelo profissional (cadastro, agenda, prontuários, gravações): o profissional é o <strong>controlador</strong> e nós somos <strong>operadores</strong>, tratando os dados apenas para prestar o serviço.</li>
        </ul>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">2. Dados que tratamos</h2>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li><strong>Profissional:</strong> nome, e-mail, senha (armazenada de forma protegida), WhatsApp, CRP, documento, foto e configurações da conta.</li>
            <li><strong>Pacientes:</strong> nome, contato, CPF (do paciente e, quando informado, do responsável financeiro), dados de agendamento e informações de saúde registradas em prontuário. São <strong>dados sensíveis</strong> (art. 11 da LGPD).</li>
            <li><strong>Sessões:</strong> áudio/vídeo gravado somente com o aceite do paciente e as notas geradas a partir dele.</li>
            <li><strong>Pagamentos:</strong> status, valores e comprovantes. Dados completos de cartão ficam com o gateway de pagamento.</li>
            <li><strong>Uso e técnicos:</strong> endereço IP, navegador, fuso horário, registros de acesso e de erros.</li>
        </ul>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">3. Para que usamos</h2>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li>Criar e manter a conta e prestar o serviço contratado;</li>
            <li>Enviar pelo WhatsApp confirmações, lembretes, link da sala e cobranças;</li>
            <li>Conferir o CPF (ou o telefone de quem não tem CPF) para liberar a entrada na sala de vídeo;</li>
            <li>Gerar notas por IA a partir de sessões gravadas com o aceite do paciente, sempre sujeitas à aprovação do profissional;</li>
            <li>Processar pagamentos e emitir comprovantes;</li>
            <li>Dar suporte, garantir a segurança e cumprir obrigações legais.</li>
        </ul>
        <p class="mb-4">Não vendemos dados pessoais nem usamos prontuários para publicidade.</p>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">4. Com quem compartilhamos</h2>
        <p class="mb-2">Compartilhamos somente o necessário com prestadores que operam o serviço:</p>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li>provedores de hospedagem e armazenamento;</li>
            <li>serviço de mensagens do WhatsApp;</li>
            <li>gateway de pagamento;</li>
            <li>provedores de IA, para transcrição e geração de notas;</li>
            <li>autoridades, quando houver obrigação legal ou ordem judicial.</li>
        </ul>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">5. Gravação e inteligência artificial</h2>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li>A sessão só é gravada se o paciente aceitar na própria chamada. Ele pode recusar ou parar a qualquer momento, sem prejuízo ao atendimento. O que foi gravado antes de parar ainda pode ser usado no rascunho.</li>
            <li>O áudio é enviado à Amazon Web Services, que o transcreve e gera, com inteligência artificial, uma sugestão de registro. Esse processamento pode acontecer fora do Brasil. O áudio e a transcrição são apagados automaticamente assim que o rascunho fica pronto, em no máximo 24 horas, e não são usados para treinar modelos.</li>
            <li>O rascunho fica guardado cifrado, e só o profissional pode lê-lo. Ele decide se edita e aprova, ou recusa. Sem decisão, o rascunho é apagado em 30 dias.</li>
            <li>O rascunho e a transcrição nunca entram no prontuário. Só entra o texto que o profissional aprovar.</li>
        </ul>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">6. Segurança</h2>
        <p class="mb-4">Adotamos medidas técnicas e administrativas, como conexão criptografada (HTTPS), controle de acesso, autenticação em duas etapas opcional e registro de acessos aos prontuários (data, IP e dispositivo).</p>
        <p class="mb-4">O conteúdo dos prontuários tem <strong>criptografia de ponta a ponta</strong>: é cifrado no navegador do profissional com uma chave protegida pela senha mestre, que não é enviada nem armazenada por nós. Por isso não temos acesso a esse conteúdo. As respostas e a pontuação das escalas aplicadas também são cifradas no navegador. Título e data do prontuário, o nome e a data das escalas aplicadas, o cadastro do paciente e o texto enviado voluntariamente ao assistente de IA não usam essa criptografia. Os detalhes estão na página <a href="{{ route('security') }}" class="text-teal-700 hover:underline">Segurança e privacidade</a>.</p>
        <p class="mb-4">Nenhum sistema é totalmente imune, e comunicaremos incidentes relevantes conforme a LGPD.</p>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">7. Cookies</h2>
        <p class="mb-4">Usamos cookies essenciais para login e segurança da sessão e, quando você aceitar, cookies de medição de uso. Você pode recusá-los no aviso exibido no site ou nas configurações do navegador.</p>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">8. Retenção</h2>
        <p class="mb-4">Mantemos os dados enquanto a conta estiver ativa e pelo prazo necessário para obrigações legais e defesa em processos. Após o encerramento, os dados são eliminados ou anonimizados, salvo guarda exigida por lei. O profissional é responsável pelos prazos de guarda de prontuário previstos nas normas da sua profissão.</p>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">9. Seus direitos</h2>
        <p class="mb-2">Você pode pedir: confirmação de tratamento, acesso, correção, anonimização ou eliminação, portabilidade, informação sobre compartilhamento e revogação do consentimento. Pacientes devem, em regra, pedir ao profissional que os atende, que é o controlador. Também podemos ajudar pelo contato abaixo.</p>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">10. Transferência internacional</h2>
        <p class="mb-4">Alguns prestadores (como hospedagem e IA) podem processar dados fora do Brasil, inclusive a Amazon Web Services na transcrição da sessão e na geração do rascunho de registro. Nesses casos, exigimos garantias compatíveis com a LGPD.</p>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">11. Alterações</h2>
        <p class="mb-4">Podemos atualizar esta Política. Mudanças relevantes serão comunicadas pela Plataforma ou por e-mail, e a data de atualização fica no topo da página.</p>
    </div>
@endsection
