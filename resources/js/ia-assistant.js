const aiAssistantResult = document.getElementById('aiAssistantResult');
const aiAssistantStatus = document.getElementById('aiAssistantStatus');
const copyAiAssistantResult = document.getElementById('copyAiAssistantResult');

if (copyAiAssistantResult) {
    copyAiAssistantResult.addEventListener('click', async function () {
        const htmlContent = aiAssistantResult.innerHTML.trim();
        const plainText = aiAssistantResult.innerText.trim();

        if (!plainText || plainText === 'Nenhum resultado ainda.') {
            return;
        }

        try {
            await navigator.clipboard.write([
                new ClipboardItem({
                    'text/html': new Blob([htmlContent], { type: 'text/html' }),
                    'text/plain': new Blob([plainText], { type: 'text/plain' })
                })
            ]);

            copyAiAssistantResult.innerHTML = '<i class="fas fa-check"></i>';
            aiAssistantStatus.textContent = 'Resultado copiado!';

            setTimeout(() => {
                copyAiAssistantResult.innerHTML = '<i class="fas fa-copy"></i>';
                aiAssistantStatus.textContent = 'Pronto para usar.';
            }, 1800);

        } catch (err) {
            aiAssistantStatus.textContent = 'Falha ao copiar, tente novamente.';
        }
    });
}

// --- CHAT IA (tela de criar prontuário) ---
let iaChatHistory = [];
let iaChatBusy = false;

function getChatContextIA() {
    const record = window.MedicalEditor && MedicalEditor.isReady() ? MedicalEditor.getText() : '';

    return { medical_record: record };
}

function updateChatContextInfoIA() {
    if (!elementExists('#iaChatContextInfo')) return;

    const ctx = getChatContextIA();
    $('#iaChatContextInfo').text('Contexto: ' + (ctx.medical_record ? `prontuário (${ctx.medical_record.length} caracteres)` : 'prontuário vazio'));
}

function showUsageIA(percent) {
    if (percent === null || isNaN(percent)) return;
    const value = Math.min(100, Math.max(0, percent));
    $('#iaChatUsage').removeClass('d-none');
    $('#iaChatUsageText').text(value.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%');
    $('#iaChatUsageBar')
        .css('width', value + '%')
        .toggleClass('bg-danger', value >= 90)
        .toggleClass('bg-warning', value >= 70 && value < 90)
        .toggleClass('bg-success', value < 70);
}

// Autorização de uso da IA (emitida pelo PSI4 em /ai-usage, válida por 30 min) guardada na sessão do navegador,
// por usuária, para não consultar o PSI4 a cada pedido. Sem saldo, o PSI4 não emite e a guardada é apagada.
const IA_GRANT_STORAGE_PREFIX = 'psi4_ai_grant_';
// Margem para não enviar um token que vence no meio do caminho
const IA_GRANT_MARGIN_SECONDS = 30;

function grantStorageKeyIA() {
    return IA_GRANT_STORAGE_PREFIX + (window.USER_UUID || '');
}

// Validade (exp, em segundos Unix) lida do payload base64url do token; null se não der para ler
function grantExpiresAtIA(token) {
    try {
        const payload = token.split('.')[0].replace(/-/g, '+').replace(/_/g, '/');
        return JSON.parse(atob(payload)).exp || null;
    } catch (error) {
        return null;
    }
}

function readGrantIA() {
    try {
        const token = sessionStorage.getItem(grantStorageKeyIA());
        const exp = token ? grantExpiresAtIA(token) : null;
        if (exp && exp - IA_GRANT_MARGIN_SECONDS > Date.now() / 1000) return token;
    } catch (error) {
        // sessionStorage indisponível (aba privada, bloqueio): segue sem cache
    }
    clearGrantIA();
    return null;
}

function saveGrantIA(token) {
    try {
        if (token) sessionStorage.setItem(grantStorageKeyIA(), token);
        else sessionStorage.removeItem(grantStorageKeyIA());
    } catch (error) {
        // sem cache: o próximo pedido busca outra autorização
    }
}

function clearGrantIA() {
    saveGrantIA(null);
}

// Consumo de IA do mês e, com saldo, a autorização para o microserviço (já guardada na sessão).
// Sem saldo, devolve reached=true e a mensagem para exibir no lugar da resposta.
async function fetchUsageIA() {
    const response = await fetch('/ai-usage', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    if (!response.ok) throw new Error(`Erro ao consultar o saldo de IA: ${response.status}`);
    const data = await response.json();
    showUsageIA(parseFloat(data.percent));
    saveGrantIA(data.reached ? null : data.token);
    return data;
}

// Autorização guardada na sessão; sem ela (ou vencida), gera outra no PSI4 antes de chamar o microserviço.
async function getGrantIA(forceRefresh = false) {
    const token = forceRefresh ? null : readGrantIA();
    if (token) return { reached: false, token: token };
    return fetchUsageIA();
}

// Chama o microserviço de IA com a autorização. Se ela não valer mais (401: vencida ou recusada),
// gera outra automaticamente e tenta de novo uma única vez.
// Retorna { reached, message } quando acabou a cota ou { response } com a resposta do microserviço.
async function requestMedicalRecordsIA(body) {
    const send = token => fetch(`${window.API_INTEGRATION_MICROSERVICE}/medical-records`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...body, token: token })
    });

    let grant = await getGrantIA();
    if (grant.reached) return grant;

    let response = await send(grant.token);
    if (response.status !== 401) return { response: response };

    clearGrantIA();
    grant = await getGrantIA(true);
    if (grant.reached) return grant;

    response = await send(grant.token);
    if (response.status === 401) clearGrantIA();
    return { response: response };
}

// Carrega o percentual já utilizado ao abrir o chat (criar/editar prontuário) e após cada resposta.
// Também renova a autorização guardada, ou a apaga quando a cota acabou.
async function loadUsageIA() {
    try {
        await fetchUsageIA();
    } catch (error) {
        console.error(error);
    }
}

// --- Renderização de HTML nas respostas da IA (com sanitização por lista de permissão) ---
const IA_ALLOWED_TAGS = ['H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'S', 'UL', 'OL', 'LI',
    'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD', 'BLOCKQUOTE', 'HR', 'SPAN', 'DIV', 'SECTION', 'HEADER', 'MAIN', 'FOOTER', 'CODE', 'PRE'];
const IA_DROP_WITH_CONTENT = ['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'TITLE', 'HEAD', 'NOSCRIPT', 'TEMPLATE'];

function hasHtmlIA(text) {
    return /<\/?[a-z][a-z0-9]*(\s[^>]*)?>/i.test(text);
}

function sanitizeNodeIA(node) {
    Array.from(node.childNodes).forEach(child => {
        if (child.nodeType === Node.TEXT_NODE) return;
        if (child.nodeType !== Node.ELEMENT_NODE) return child.remove();

        if (IA_DROP_WITH_CONTENT.includes(child.tagName)) return child.remove();

        sanitizeNodeIA(child);

        if (!IA_ALLOWED_TAGS.includes(child.tagName)) {
            child.replaceWith(...Array.from(child.childNodes)); // remove só a tag, mantém o conteúdo
            return;
        }
        Array.from(child.attributes).forEach(attr => child.removeAttribute(attr.name));
    });
}

function sanitizeHtmlIA(html) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    sanitizeNodeIA(doc.body);
    return doc.body.innerHTML;
}

// Preenche o balão: HTML sanitizado quando a resposta contém tags; texto puro caso contrário.
function renderChatContentIA(el, text) {
    if (!hasHtmlIA(text)) {
        el.removeClass('ia-chat-html').text(text);
        return;
    }
    // Oculta tag parcial ainda chegando no stream (ex.: "<stro")
    el.addClass('ia-chat-html').html(sanitizeHtmlIA(text.replace(/<[^>]*$/, '')));
}

function appendChatMessageIA(role, text = '', extraClass = '') {
    $('#iaChatEmpty').remove();
    const box = $('#iaChatMessages');
    const msg = $('<div>').addClass(`ia-chat-msg ${role} ${extraClass}`);
    if (role === 'assistant') renderChatContentIA(msg, text); else msg.text(text);
    box.append(msg);
    box.scrollTop(box[0].scrollHeight);
    return msg;
}

function setChatBusyIA(busy) {
    iaChatBusy = busy;
    $('#iaChatSendBtn, #iaChatInput').prop('disabled', busy);
    $('#iaChatSendBtn').html(busy ? '<i class="fas fa-spinner fa-spin"></i>' : '<i class="fas fa-paper-plane"></i>');
    if (!busy) $('#iaChatInput').trigger('focus');
}

function sendChatSuggestionIA(btn) {
    $('#iaChatInput').val($(btn).text());
    sendChatIA();
}

function getChatMessageHtmlIA(btn) {
    const msg = $(btn).closest('.ia-chat-msg-actions').prev('.ia-chat-msg');
    if (msg.hasClass('ia-chat-html')) return msg.html();

    return msg.text().split(/\n{2,}/)
        .map(p => '<p>' + $('<div>').text(p).html().replace(/\n/g, '<br>') + '</p>')
        .join('');
}

// Insere na posição do cursor (ou no lugar da seleção)
function insertChatMessageInEditorIA(btn) {
    MedicalEditor.insertHTML(getChatMessageHtmlIA(btn));
}

// Substitui o documento inteiro, pedindo confirmação quando já há conteúdo
function replaceEditorWithChatMessageIA(btn) {
    if (MedicalEditor.getText() && !confirm('Substituir todo o conteúdo do prontuário por esta resposta?')) return;
    MedicalEditor.setHTML(getChatMessageHtmlIA(btn));
}

async function sendChatIA() {
    const input = $('#iaChatInput');
    const text = input.val().trim();
    if (!text || iaChatBusy) return;

    setChatBusyIA(true);
    input.val('');
    appendChatMessageIA('user', text);
    iaChatHistory.push({ role: 'user', content: text });

    const answer = appendChatMessageIA('assistant', '', 'ia-chat-typing');
    let finalText = '';

    try {
        const result = await requestMedicalRecordsIA({ messages: iaChatHistory, context: getChatContextIA() });
        if (result.reached) {
            iaChatHistory.pop();
            answer.addClass('error').text(result.message);
            return;
        }

        const response = result.response;

        if (response.status === 401) {
            const data = await response.json();
            iaChatHistory.pop();
            answer.addClass('error').text(data.error);
            return;
        }

        if (!response.ok || !response.body) {
            throw new Error(`Erro na API: ${response.status}`);
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder('utf-8');
        const box = $('#iaChatMessages');

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            finalText += decoder.decode(value, { stream: true });
            renderChatContentIA(answer, finalText);
            box.scrollTop(box[0].scrollHeight);
        }

        loadUsageIA();
        iaChatHistory.push({ role: 'assistant', content: finalText });
        answer.after(
            '<div class="ia-chat-msg-actions"><button type="button" class="btn btn-sm ia-chat-action" onclick="insertChatMessageInEditorIA(this)"><i class="fas fa-file-import me-1"></i> Inserir no cursor</button> <button type="button" class="btn btn-sm ia-chat-action" onclick="replaceEditorWithChatMessageIA(this)">Substituir documento</button></div>'
        );
    } catch (error) {
        console.error(error);
        iaChatHistory.pop();
        answer.addClass('error').text('Não foi possível obter a resposta. Por favor, tente mais tarde.');
        if (typeof sendLog === 'function') {
            sendLog('Não foi possível obter resposta do chat com IA.', 'ia-assistant.js->sendChatIA');
        }
    } finally {
        answer.removeClass('ia-chat-typing');
        setChatBusyIA(false);
    }
}

$(document).ready(function () {
    if (!elementExists('#iaChatInput')) return;

    $('#iaChatInput').on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendChatIA();
        }
    });

    setInterval(updateChatContextInfoIA, 1500);
    updateChatContextInfoIA();
    loadUsageIA();
});

// Mantém o chat fixo na tela, alinhado à coluna onde ele foi renderizado
function alignChatIA() {
    const chat = document.querySelector('.ia-chat');
    if (!chat || !chat.parentElement || chat.closest('.doc-ia-panel')) return;

    if (window.innerWidth < 768) {
        chat.style.width = chat.style.left = '';
        return;
    }

    const col = chat.parentElement.getBoundingClientRect();
    chat.style.left = col.left + 'px';
    chat.style.width = col.width + 'px';
}

$(window).on('resize load', alignChatIA);
$(document).ready(function () {
    setInterval(alignChatIA, 500);
});
