@extends('layouts.legal')
@section('title', 'Segurança e Privacidade')
@section('content')
    <div class="mb-8">
        <p class="mb-4">Prontuário psicológico é dado pessoal sensível (art. 11 da LGPD) e protegido por sigilo profissional. Aqui explicamos, de forma direta, como o {{ config('app.name') }} protege essas informações: o que é criptografado, onde os dados ficam e quem pode acessá-los.</p>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">1. Criptografia de ponta a ponta dos prontuários</h2>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li>O conteúdo de cada prontuário é <strong>criptografado no seu navegador</strong>, com AES-256-GCM, <strong>antes</strong> de ser enviado aos nossos servidores. Nós recebemos e guardamos apenas o conteúdo cifrado.</li>
            <li>A chave que abre os prontuários é protegida pela sua <strong>senha mestre</strong> (derivação PBKDF2-SHA256 com 600.000 iterações). A senha mestre <strong>nunca é enviada</strong> ao servidor e não é armazenada por nós.</li>
            <li>Por isso, <strong>nem a equipe do {{ config('app.name') }}</strong>, nem quem tivesse acesso ao banco de dados ou ao armazenamento, consegue ler o conteúdo dos prontuários.</li>
            <li>Na configuração você recebe uma <strong>chave de recuperação</strong>. Se perder a senha mestre e também a chave de recuperação, os prontuários não podem ser recuperados por ninguém.</li>
            <li>Os rascunhos guardados temporariamente no navegador também são cifrados.</li>
            <li>As <strong>respostas e a pontuação das escalas</strong> (como PHQ-9 e GAD-7) também são criptografadas no seu navegador. O gráfico de evolução é montado no seu dispositivo.</li>
        </ul>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">2. O que não é criptografado de ponta a ponta</h2>
        <p class="mb-2">Para o sistema funcionar (agenda, buscas, lembretes, relatórios), algumas informações precisam ser lidas pelo servidor. Elas trafegam com HTTPS e têm acesso restrito, mas não usam a criptografia de ponta a ponta:</p>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li>cadastro do paciente (nome, contato, data de nascimento, CPF e observações);</li>
            <li>agenda, pagamentos, recibos do Receita Saúde e documentos emitidos;</li>
            <li>título e data da sessão de cada prontuário. Por isso, recomendamos <strong>não colocar dados clínicos no título</strong>;</li>
            <li>qual escala foi aplicada e em que data (as respostas e a pontuação continuam cifradas);</li>
            <li>o texto enviado ao <strong>assistente de IA</strong>, quando você decide usá-lo: para gerar a resposta, o conteúdo é enviado ao provedor de IA;</li>
            <li>o <strong>áudio e o texto da transcrição da sessão</strong>, quando o paciente aceita: por alguns minutos, enquanto são transcritos e resumidos automaticamente pela Amazon Web Services. Ninguém da equipe tem acesso a eles, e tudo é apagado logo depois. O rascunho que fica guardado é cifrado com a chave do profissional, e só o texto que ele aprovar entra no prontuário, com a criptografia de ponta a ponta.</li>
        </ul>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">3. Onde os dados ficam</h2>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li>Os arquivos dos prontuários, já cifrados, ficam armazenados na <strong>Amazon Web Services (AWS), região de São Paulo</strong>.</li>
            <li>Os demais dados ficam em nosso banco de dados, com acesso restrito à equipe técnica responsável pela operação.</li>
            <li>Alguns prestadores (como o provedor de IA) podem processar dados fora do Brasil, com garantias compatíveis com a LGPD. A lista de prestadores está na <a href="{{ route('privacy-policy') }}" class="text-teal-700 hover:underline">Política de Privacidade</a>.</li>
        </ul>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">4. Quem acessa os prontuários</h2>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li>Somente o profissional dono da conta, depois de desbloquear com a senha mestre.</li>
            <li>Cada vez que um prontuário é <strong>criado, aberto, editado ou apagado</strong>, registramos data, hora, IP e dispositivo. Os logins na conta também são registrados. Você consulta tudo em <strong>Configurações &gt; Segurança &gt; Atividade de acesso</strong>.</li>
            <li>Opcionalmente, a <strong>autenticação de 2 fatores</strong> passa a exigir um código do aplicativo para entrar na conta e para abrir ou apagar prontuários.</li>
            <li>Desbloqueados, os prontuários ficam acessíveis no navegador por até 30 minutos sem uso. Ao sair da conta, a chave é descartada do navegador.</li>
        </ul>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">5. Sessões por vídeo</h2>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li>As consultas online acontecem numa sala do próprio {{ config('app.name') }}, em <strong>meet.psi4.com.br</strong>. O áudio e o vídeo trafegam <strong>cifrados</strong> entre o navegador e o servidor de vídeo e <strong>não ficam guardados</strong> nele.</li>
            <li>Para entrar na sala, a pessoa informa o CPF cadastrado. Paciente sem CPF informa o telefone. A sala guarda só um código derivado desse dado, e a entrada fica limitada ao horário da consulta.</li>
            <li>A chamada <strong>não é cifrada de ponta a ponta</strong>: para chegar à outra pessoa, o áudio e o vídeo passam pelo servidor de vídeo. A criptografia de ponta a ponta vale para o conteúdo do prontuário.</li>
            <li>O paciente <strong>não precisa criar conta</strong> nem instalar aplicativo: entra pelo navegador, com o link enviado no WhatsApp.</li>
            <li>Para entrar, cada pessoa informa o número de WhatsApp. Só são aceitos os números do profissional e do paciente daquela consulta. Guardamos esses números apenas em forma de hash (HMAC), nunca em texto.</li>
            @php
                $videoWindow = array_filter([
                    config('psi_video.access_opens_before_minutes') !== null ? 'abre '.config('psi_video.access_opens_before_minutes').' minutos antes do horário' : null,
                    config('psi_video.access_closes_after_minutes') !== null ? 'fecha '.config('psi_video.access_closes_after_minutes').' minutos depois do fim' : null,
                ]);
            @endphp
            <li>A sala {{ $videoWindow ? implode(', ', $videoWindow).' e' : '' }} deixa de aceitar entradas se a consulta for cancelada. As tentativas de entrada são limitadas, para impedir que alguém tente adivinhar números.</li>
        </ul>
    </div>

    <div class="mb-8">
        <h2 class="text-xl font-bold section-title mb-4">6. LGPD e Conselho Federal de Psicologia</h2>
        <ul class="list-disc pl-6 space-y-2 mb-4">
            <li>Em relação aos dados dos pacientes, o profissional é o <strong>controlador</strong> e o {{ config('app.name') }} é o <strong>operador</strong>, tratando os dados apenas para prestar o serviço.</li>
            <li>A criptografia de ponta a ponta e o registro de acessos ajudam o profissional a cumprir o dever de sigilo e a guarda segura do registro documental previstos nas Resoluções CFP 001/2009 e 06/2019.</li>
            <li>Ao apagar um prontuário com menos de 5 anos, o sistema avisa sobre o prazo mínimo de guarda e pede confirmação explícita. A decisão e a responsabilidade continuam sendo do profissional, assim como manter a senha mestre e a chave de recuperação em segurança.</li>
            <li>Os prontuários de um paciente podem ser <strong>exportados</strong> num único documento (decifrado no navegador), para atender ao pedido de acesso do paciente ou para guardar os registros ao encerrar a conta. Cada exportação fica registrada na atividade de acesso.</li>
            <li>Incidentes de segurança relevantes são comunicados aos titulares e à ANPD, conforme a LGPD.</li>
        </ul>
    </div>
@endsection
