@php
    // Perguntas frequentes da tela de Ajuda. Para incluir/alterar, basta editar esta lista.
    $faq = [
        [
            'question' => 'Como cadastro um paciente?',
            'answer' => 'Acesse <strong>Pacientes</strong> no menu e clique em <strong>Cadastrar Paciente</strong>. Preencha os dados e salve. Depois disso, o paciente já fica disponível para agendamentos, prontuários, documentos e cobranças.',
        ],
        [
            'question' => 'Como agendo uma sessão?',
            'answer' => 'Em <strong>Agenda</strong>, clique no horário desejado, escolha o paciente e confirme. Os dias e horários de atendimento são definidos em <strong>Configurações › Agenda</strong>.',
        ],
        [
            'question' => 'Como meus pacientes podem agendar sozinhos?',
            'answer' => 'Ative a <strong>Página de agendamento online</strong> em <strong>Configurações › Agenda › Agendamento online</strong> e compartilhe o link com seus pacientes. Só os horários livres da sua agenda aparecem para eles.',
        ],
        [
            'question' => 'Meus pacientes recebem confirmação e lembrete pelo WhatsApp?',
            'answer' => 'Sim, automaticamente, pelo número oficial do PSI4. O paciente recebe a confirmação ao agendar e um lembrete antes do horário, com os botões <strong>Confirmo presença</strong> e <strong>Preciso remarcar</strong>; se ele pedir para remarcar, você é avisada. Nas consultas online, o link da sala chega para vocês dois 5 minutos antes. A antecedência do lembrete fica em <strong>Configurações › Agenda</strong>, e os avisos de um paciente podem ser desligados no cadastro dele. As mensagens não citam terapia nem sessão.',
        ],
        [
            'question' => 'Meus prontuários estão seguros?',
            'answer' => 'Sim. O conteúdo dos prontuários é criptografado no seu próprio navegador antes de ser enviado. Assim, as anotações ficam protegidas inclusive no armazenamento.',
        ],
        [
            'question' => 'Como emito um atestado ou declaração?',
            'answer' => 'Em <strong>Documentos</strong>, escolha o modelo, selecione o paciente e preencha os campos. Você acompanha a pré-visualização e, ao final, clica em <strong>Emitir Documento</strong> para baixar o arquivo.',
        ],
        [
            'question' => 'Como cobro um paciente?',
            'answer' => 'Primeiro, cadastre a conta que vai receber em <strong>Configurações › Pagamentos</strong>. Depois, em <strong>Pagamentos</strong>, crie uma nova ordem de pagamento com o paciente, o valor e as formas de pagamento aceitas.',
        ],
        [
            'question' => 'Posso sincronizar com o Google Agenda?',
            'answer' => 'Sim. Em <strong>Configurações › Agenda › Google Agenda</strong>, conecte sua conta Google. Os horários em que você já tem compromisso deixam de aparecer como livres. Lemos apenas se o horário está ocupado, nunca os detalhes dos eventos.',
        ],
        [
            'question' => 'Como deixo minha conta mais segura?',
            'answer' => 'Ative a <strong>autenticação de dois fatores</strong> em <strong>Configurações › Segurança</strong>. Com ela, além da senha, será pedido um código do seu aplicativo autenticador ao entrar.',
        ],
    ];
@endphp

<div class="dash-card help-faq">
    <div class="dash-title"><i class="far fa-lightbulb" aria-hidden="true"></i> Perguntas frequentes</div>

    <div class="help-search mb-3">
        <i class="fas fa-search" aria-hidden="true"></i>
        <input class="form-control" id="help-faq-search" type="search" maxlength="100" autocomplete="off"
               placeholder="Busque pela sua dúvida" aria-label="Buscar nas perguntas frequentes" aria-controls="help-faq-list">
    </div>

    <div class="accordion help-accordion" id="help-faq-list">
        @foreach($faq as $i => $item)
            <div class="accordion-item help-faq-item" data-help-faq>
                <h2 class="accordion-header" id="help-faq-heading-{{ $i }}">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#help-faq-answer-{{ $i }}" aria-expanded="false" aria-controls="help-faq-answer-{{ $i }}">
                        {{ $item['question'] }}
                    </button>
                </h2>
                <div id="help-faq-answer-{{ $i }}" class="accordion-collapse collapse" aria-labelledby="help-faq-heading-{{ $i }}" data-bs-parent="#help-faq-list">
                    <div class="accordion-body">{!! $item['answer'] !!}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="dash-empty d-none" id="help-faq-empty" role="status">
        <i class="far fa-comment-dots" aria-hidden="true"></i>
        Não encontramos essa dúvida por aqui.<br>
        <a href="{{ route('whatsapp.help') }}" target="_blank" rel="noopener" class="help-link">Pergunte direto para a nossa equipe</a>
    </div>
</div>
