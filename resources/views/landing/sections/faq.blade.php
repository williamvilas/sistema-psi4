@php
    // Objeções de communication.html (seção 10). Resposta: validar → explicar → provar.
    $faq = [
        [
            'q' => 'Não confio em sistema online para prontuário.',
            'a' => ['Essa desconfiança é saudável e mostra cuidado com seus pacientes.', 'Por isso o conteúdo do prontuário é cifrado no seu navegador, antes de chegar até nós, com uma chave que só a sua senha mestre destranca. Nem a nossa equipe consegue ler.', 'A página de segurança explica o funcionamento, inclusive o que não é cifrado.'],
            'security' => true,
        ],
        [
            'q' => 'E se eu esquecer a senha mestre?',
            'a' => ['É a pergunta mais importante.', 'Ao configurar, você recebe uma chave de recuperação, e só ela destranca os prontuários se a senha for esquecida. Como nem nós temos acesso, não temos como recuperar por você.', 'Por isso pedimos que você guarde a chave de recuperação com o mesmo cuidado da chave do consultório.'],
        ],
        [
            'q' => 'As mensagens não vão soar frias para o paciente?',
            'a' => ['Esse foi o nosso maior cuidado.', 'No plano ' . $proName . ', a confirmação, o lembrete e o link da sala chegam automáticos no WhatsApp, curtos e cordiais, e não citam terapia nem sessão, porque outra pessoa pode ver a notificação no celular do paciente. No lembrete, ele confirma presença ou pede para remarcar com um toque, e você é avisada.', 'Você escolhe com quanta antecedência o lembrete sai, pode desligar os avisos de um paciente, e ele mesmo pode parar de receber respondendo SAIR.'],
        ],
        [
            'q' => 'É seguro atender por vídeo pelo sistema?',
            'a' => ['É uma preocupação justa, a sessão online merece o mesmo sigilo da sala.', 'A chamada acontece numa sala nossa, em meet.psi4.com.br. ' . ($transcriptionEnabled ? 'O vídeo trafega e não fica guardado no nosso servidor, mas passa por ele para chegar ao seu paciente. O áudio só é guardado, por pouco tempo, se o paciente aceitar a transcrição.' : 'O áudio e o vídeo trafegam e não ficam guardados no nosso servidor, mas passam por ele para chegar ao seu paciente.') . ' O paciente não cria conta: ele informa o CPF cadastrado (ou o telefone, se não tiver CPF) e só entra se existir uma consulta marcada com esse dado.', 'A página de segurança explica como o acesso à sala é conferido.'],
            'security' => true,
        ],
        [
            'q' => 'Como funcionam as dinâmicas na sessão online?',
            'a' => ['Você escolhe uma dinâmica na sala de vídeo, como a Roda das emoções, o Termômetro emocional ou as Cartas de perguntas, e ela aparece na hora para o paciente. Os dois interagem juntos, no computador ou no celular, sem preparar material antes.', 'Ao encerrar, você revisa o resultado e ele vai para o prontuário já cifrado. Nem nós conseguimos ler.', 'Não é teste psicológico: não há nota, faixa nem interpretação. É um apoio para a conversa, e quem conduz é você.'],
        ],
        ...($transcriptionEnabled ? [[
            'q' => 'A sessão é gravada?',
            'a' => ['Só se você ativar a transcrição e o paciente aceitar, na própria chamada. Ele pode recusar ou parar a qualquer momento, sem prejuízo ao atendimento.', 'O áudio é transcrito pela Amazon Web Services, que pode processá-lo fora do Brasil, e vira um rascunho de registro cifrado com a sua chave. O áudio e a transcrição são apagados em no máximo 24 horas e não são usados para treinar modelos.', 'O rascunho nunca entra no prontuário: só o texto que você aprovar. A transcrição não vale para pacientes menores de 18 anos.'],
            'security' => true,
        ]] : []),
        [
            'q' => 'Não quero IA no meu prontuário.',
            'a' => ['Você não precisa usar.', 'A IA é opcional a cada vez e nada entra no prontuário sem a sua decisão. Quando você escolhe usar, o texto enviado a ela sai da proteção de ponta a ponta' . ($transcriptionEnabled ? ', e o mesmo vale para o áudio da sessão enquanto é transcrito.' : '.'), 'O sistema avisa isso antes, na própria tela.'],
        ],
        [
            'q' => 'Tenho anos de prontuário em outro sistema.',
            'a' => ['Esse é o maior medo de trocar, e é legítimo.', 'Ainda não temos importação automática. Estamos construindo, com cifragem no seu navegador, para que nem na migração alguém leia os prontuários em claro.', 'Se quiser, chame a gente no WhatsApp e avisamos quando estiver pronta.'],
        ],
        [
            'q' => 'Preciso de cartão? Posso cancelar?',
            'a' => ['Pode começar sem compromisso.', 'No plano ' . $proName . ' mensal não há fidelidade. Você cancela quando quiser. O anual sai por R$ ' . $annualMonthly . '/mês, com fidelidade de 12 meses.', 'Se o ' . $proName . ' acabar, sua conta volta para o Grátis: seus prontuários continuam guardados e cifrados, e a taxa por cobrança paga volta a ser de ' . config('plans.plans.free.payment_fee_percent') . '%.'],
        ],
    ];
@endphp

<section id="duvidas" class="lp-section lp-section-alt" aria-labelledby="faq-title">
    <div class="lp-container">
        <div class="lp-measure lp-center lp-reveal">
            <span class="lp-eyebrow">Dúvidas</span>
            <h2 id="faq-title">Perguntas que fazem sentido</h2>
            <p class="lp-lead">Se a sua não estiver aqui, pergunte para uma pessoa do nosso time, pelo WhatsApp.</p>
        </div>
        <div class="lp-faq">
            @foreach($faq as $item)
                <details>
                    <summary>{{ $item['q'] }} <i class="bi bi-plus-lg" aria-hidden="true"></i></summary>
                    <div class="lp-faq-body">
                        <p>{{ implode(' ', $item['a']) }}</p>
                        @if($item['security'] ?? false)
                            <p><a href="{{ $linkSecurity }}" class="lp-link-arrow" data-funnel="landing_cta_security">Ver a página de segurança <i class="bi bi-arrow-right" aria-hidden="true"></i></a></p>
                        @endif
                    </div>
                </details>
            @endforeach
        </div>

        {{-- Cuidar de quem cuida: suporte humano junto das dúvidas --}}
        <div id="suporte" class="lp-support lp-faq lp-reveal" aria-labelledby="support-title">
            <div>
                <h3 id="support-title">Quem passa o dia acolhendo também merece ser acolhida.</h3>
                <p class="lp-muted">Fale com uma pessoa pelo WhatsApp. Em português, sem robô, sem fila.</p>
                <a href="{{ $supportLink }}" target="_blank" rel="noopener" class="lp-btn lp-btn-ghost">
                    <i class="bi bi-whatsapp" aria-hidden="true"></i> Conversar com a gente
                </a>
            </div>
            <div class="lp-card lp-hours">
                <h4 class="h6" style="font-family:var(--lp-sans); font-weight:600"><i class="bi bi-clock me-2" aria-hidden="true" style="color:var(--lp-teal)"></i>Horário de atendimento</h4>
                <dl>
                    @foreach($supportSchedule as $row)
                        <div>
                            <dt>{{ $row['days'] }}</dt>
                            <dd>{{ $row['hours'] }}</dd>
                        </div>
                    @endforeach
                </dl>
                <p class="lp-small lp-muted mt-3 mb-0">Fora do horário, pode mandar mensagem. Respondemos assim que voltarmos.</p>
            </div>
        </div>
    </div>
</section>
