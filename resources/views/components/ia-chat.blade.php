<div class="card ia-chat">
    <div class="ia-chat-header py-2 px-3 d-flex justify-content-between align-items-center">
        <strong>Chat com {{ __('plans.model_ia') }}</strong>
        <button type="button" class="btn btn-sm ia-chat-close" id="docIaClose" title="Fechar chat" aria-label="Fechar chat">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <div class="ia-chat-usage px-3 pt-2 small d-none" id="iaChatUsage">
        <div class="d-flex justify-content-between">
            <span>Pacote de IA utilizado</span><strong id="iaChatUsageText">0%</strong>
        </div>
        <div class="progress" style="height: 6px;">
            <div id="iaChatUsageBar" class="progress-bar bg-success" role="progressbar" style="width: 0%"></div>
        </div>
    </div>

    <div id="iaChatMessages" class="ia-chat-messages flex-grow-1 px-3 py-2" aria-live="polite">
        <div class="ia-chat-empty text-center small" id="iaChatEmpty">
            <i class="fas fa-comments fa-2x mb-2 d-block"></i>
            Converse com a IA sobre este prontuário.
            <div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
                <button type="button" class="ia-chat-chip" onclick="sendChatSuggestionIA(this)">Resuma o prontuário</button>
                <button type="button" class="ia-chat-chip" onclick="sendChatSuggestionIA(this)">Melhore a redação</button>
                <button type="button" class="ia-chat-chip" onclick="sendChatSuggestionIA(this)">Sugira próximos passos</button>
            </div>
        </div>
    </div>

    <div class="ia-chat-footer px-3 pt-2">
        <div class="ia-chat-composer">
            <textarea id="iaChatInput" rows="1" maxlength="1200" aria-label="Mensagem para a IA"
                placeholder="Pergunte qualquer coisa"></textarea>
            <button id="iaChatSendBtn" type="button" class="ia-chat-send" onclick="sendChatIA()" title="Enviar" aria-label="Enviar">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
        <p class="ia-chat-privacy mb-2 mt-1">
            <i class="fas fa-circle-info"></i> Ao usar a IA, o texto deste prontuário é enviado ao provedor de IA para gerar a resposta e não fica protegido pela criptografia de ponta a ponta.
        </p>
    </div>
</div>
