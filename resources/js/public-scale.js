/**
 * Escala respondida pelo paciente por link (página pública, sem login).
 * - Uma pergunta por tela, com avanço automático ao escolher; revisão antes de enviar.
 * - As respostas são cifradas aqui (encryptScaleForPsychologist, crypto.js) com a chave pública da psicóloga
 *   e enviadas direto ao storage; o servidor só confirma o envio e encerra o link.
 * - A pontuação não é calculada nem exibida para o paciente.
 */
const PUBLIC_SCALE_CONTENT_TYPE = 'application/octet-stream';
const PUBLIC_SCALE_ADVANCE_MS = 280;

const publicScale = {
    token: null,
    scale: null,
    publicKey: null,
    answers: [],
    current: 0,
    history: [],
    advanceTimer: null,
    sending: false,
};

document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('public-scale');
    if (!root) return;

    initPublicScale(root);
});

function initPublicScale(root) {
    publicScale.token = root.dataset.token;
    publicScale.scale = JSON.parse(document.getElementById('ps-scale').textContent);
    publicScale.publicKey = JSON.parse(document.getElementById('ps-public-key').textContent);
    publicScale.answers = restorePublicScaleDraft() || publicScale.scale.items.map(() => null);

    // Sem Web Crypto (navegador antigo ou página fora de HTTPS) não há como proteger as respostas.
    if (!window.crypto || !window.crypto.subtle) {
        return showPublicScaleError('Este navegador não consegue proteger suas respostas. Abra o link em um navegador atualizado, como Chrome, Safari ou Firefox.', false);
    }

    root.addEventListener('click', function (e) {
        const button = e.target.closest('[data-action]');
        if (!button) return;

        const actions = {
            start: () => goToPublicScaleStep(publicScale.scale.reference_event ? 'reference' : firstPendingQuestion()),
            next: nextPublicScaleStep,
            back: backPublicScaleStep,
            submit: submitPublicScale,
            retry: submitPublicScale,
        };

        if (actions[button.dataset.action]) actions[button.dataset.action]();
    });

    document.getElementById('ps-options').addEventListener('change', function (e) {
        if (e.target.name === 'ps-answer') answerPublicScale(Number(e.target.value));
    });

    document.getElementById('ps-review').addEventListener('click', function (e) {
        const item = e.target.closest('[data-review-item]');
        if (item) goToPublicScaleStep(Number(item.dataset.reviewItem));
    });

    const reference = document.getElementById('ps-reference-event');
    if (reference) {
        reference.addEventListener('input', savePublicScaleDraft);
    }

    // Atalho no computador: as teclas dos números escolhem a opção correspondente.
    document.addEventListener('keydown', function (e) {
        if (currentPublicScaleStep() !== 'question' || e.target.tagName === 'TEXTAREA') return;

        const option = publicScale.scale.options.find((item) => String(item.value) === e.key);
        if (option) {
            const input = document.getElementById('ps-option-' + option.value);
            input.checked = true;
            answerPublicScale(option.value);
        }
    });
}

// --- Navegação ---

function currentPublicScaleStep() {
    const active = document.querySelector('.ps-step.is-active');

    return active ? active.dataset.step : null;
}

// step: nome da etapa ou índice da pergunta.
function goToPublicScaleStep(step, remember = true) {
    clearTimeout(publicScale.advanceTimer);

    const previous = currentPublicScaleStep() === 'question' ? publicScale.current : currentPublicScaleStep();
    if (remember && previous !== null && previous !== step) {
        publicScale.history.push(previous);
    }

    const isQuestion = typeof step === 'number';
    if (isQuestion) {
        publicScale.current = step;
        renderPublicScaleQuestion();
    }

    if (step === 'review') {
        renderPublicScaleReview();
    }

    document.querySelectorAll('.ps-step').forEach(function (section) {
        section.classList.toggle('is-active', section.dataset.step === (isQuestion ? 'question' : step));
    });

    window.scrollTo({ top: 0, behavior: 'smooth' });
    focusPublicScaleStep();
}

function focusPublicScaleStep() {
    const active = document.querySelector('.ps-step.is-active');
    const target = active && (active.querySelector('input:checked') || active.querySelector('.ps-title, .ps-item'));
    if (!target) return;

    if (!target.matches('input')) target.setAttribute('tabindex', '-1');
    target.focus({ preventScroll: true });
}

function backPublicScaleStep() {
    const previous = publicScale.history.pop();
    goToPublicScaleStep(previous === undefined ? 'intro' : previous, false);
}

function nextPublicScaleStep() {
    const step = currentPublicScaleStep();

    if (step === 'reference') {
        return goToPublicScaleStep(firstPendingQuestion());
    }

    if (step === 'question' && publicScale.answers[publicScale.current] !== null) {
        goToPublicScaleStep(nextPendingQuestion(publicScale.current));
    }
}

function firstPendingQuestion() {
    const index = publicScale.answers.findIndex((value) => value === null);

    return index === -1 ? 'review' : index;
}

// Próxima pergunta sem resposta depois da atual; se todas estiverem respondidas, vai para a revisão.
function nextPendingQuestion(from) {
    const index = publicScale.answers.findIndex((value, i) => i > from && value === null);

    return index === -1 ? firstPendingQuestion() : index;
}

// --- Perguntas ---

function renderPublicScaleQuestion() {
    const scale = publicScale.scale;
    const index = publicScale.current;
    const answered = publicScale.answers.filter((value) => value !== null).length;
    const options = document.getElementById('ps-options');

    document.getElementById('ps-counter').textContent = 'Pergunta ' + (index + 1) + ' de ' + scale.items.length;
    document.getElementById('ps-item').textContent = scale.items[index];
    document.getElementById('ps-progress').setAttribute('aria-valuenow', answered);
    document.getElementById('ps-progress-bar').style.width = (answered / scale.items.length * 100) + '%';
    document.getElementById('ps-next').classList.toggle('d-none', publicScale.answers[index] === null);

    options.innerHTML = '';
    scale.options.forEach(function (option) {
        const input = document.createElement('input');
        input.type = 'radio';
        input.name = 'ps-answer';
        input.id = 'ps-option-' + option.value;
        input.value = option.value;
        input.className = 'ps-option-input';
        input.checked = publicScale.answers[index] === option.value;

        const label = document.createElement('label');
        label.className = 'ps-option';
        label.htmlFor = input.id;
        label.textContent = option.label;

        options.append(input, label);
    });
}

function answerPublicScale(value) {
    publicScale.answers[publicScale.current] = value;
    savePublicScaleDraft();

    document.getElementById('ps-next').classList.remove('d-none');
    const answered = publicScale.answers.filter((item) => item !== null).length;
    document.getElementById('ps-progress-bar').style.width = (answered / publicScale.answers.length * 100) + '%';
    document.getElementById('ps-progress').setAttribute('aria-valuenow', answered);

    // Pequena pausa para a pessoa ver a opção marcada antes de avançar.
    clearTimeout(publicScale.advanceTimer);
    publicScale.advanceTimer = setTimeout(nextPublicScaleStep, PUBLIC_SCALE_ADVANCE_MS);
}

function renderPublicScaleReview() {
    const scale = publicScale.scale;
    const list = document.getElementById('ps-review');

    list.innerHTML = '';
    scale.items.forEach(function (item, index) {
        const option = scale.options.find((entry) => entry.value === publicScale.answers[index]);
        const li = document.createElement('li');
        const button = document.createElement('button');
        const question = document.createElement('span');
        const answer = document.createElement('strong');

        button.type = 'button';
        button.className = 'ps-review-item';
        button.dataset.reviewItem = index;
        question.textContent = item;
        answer.textContent = option ? option.label : 'Sem resposta';

        button.append(question, answer);
        li.append(button);
        list.append(li);
    });
}

// --- Rascunho (sessionStorage: some ao fechar a aba, não fica salvo no aparelho) ---

function publicScaleDraftKey() {
    return 'psi4-scale-' + publicScale.token;
}

function savePublicScaleDraft() {
    try {
        const reference = document.getElementById('ps-reference-event');
        sessionStorage.setItem(publicScaleDraftKey(), JSON.stringify({
            version: publicScale.scale.version,
            answers: publicScale.answers,
            reference_event: reference ? reference.value : '',
        }));
    } catch (e) {}
}

function restorePublicScaleDraft() {
    try {
        const draft = JSON.parse(sessionStorage.getItem(publicScaleDraftKey()) || 'null');
        if (!draft || draft.version !== publicScale.scale.version || draft.answers.length !== publicScale.scale.items.length) {
            return null;
        }

        const reference = document.getElementById('ps-reference-event');
        if (reference && draft.reference_event) reference.value = draft.reference_event;

        return draft.answers;
    } catch (e) {
        return null;
    }
}

function clearPublicScaleDraft() {
    try {
        sessionStorage.removeItem(publicScaleDraftKey());
    } catch (e) {}
}

// --- Envio ---

async function publicScaleRequest(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) },
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(data.message || 'Falha na requisição.');
        error.status = response.status;
        error.friendly = [400, 410].includes(response.status) && data.message ? data.message : null;
        throw error;
    }

    return data;
}

async function submitPublicScale() {
    if (publicScale.sending) return;

    const missing = firstPendingQuestion();
    if (missing !== 'review') {
        return goToPublicScaleStep(missing);
    }

    publicScale.sending = true;
    goToPublicScaleStep('sending', false);

    const base = '/avaliacao/' + encodeURIComponent(publicScale.token);

    try {
        const upload = await publicScaleRequest(base + '/upload-url', { method: 'POST' });

        const reference = document.getElementById('ps-reference-event');
        const referenceEvent = reference ? reference.value.trim() : '';
        const content = JSON.stringify({
            version: publicScale.scale.version,
            answers: publicScale.answers,
            ...(referenceEvent ? { reference_event: referenceEvent } : {}),
        });
        const encrypted = await encryptScaleForPsychologist(content, publicScale.publicKey, publicScale.scale.slug, upload.content_path);

        const stored = await fetch(upload.link, {
            method: 'PUT',
            headers: { 'Content-Type': PUBLIC_SCALE_CONTENT_TYPE },
            body: new Blob([encrypted], { type: PUBLIC_SCALE_CONTENT_TYPE }),
        });

        if (!stored.ok) {
            throw new Error('Falha ao enviar o arquivo: ' + stored.status);
        }

        await publicScaleRequest(base, { method: 'POST' });

        clearPublicScaleDraft();
        publicScale.history = [];
        goToPublicScaleStep('done', false);
    } catch (error) {
        console.error(error);

        if (error.status === 429) {
            return showPublicScaleError('Muitas tentativas seguidas. Aguarde um minuto e tente novamente. Suas respostas continuam aqui.');
        }

        // 410: link encerrado (respondido, expirado ou cancelado) enquanto a pessoa respondia; não adianta tentar de novo.
        showPublicScaleError(error.friendly || undefined, error.status !== 410);
    } finally {
        publicScale.sending = false;
    }
}

function showPublicScaleError(message, canRetry = true) {
    if (message) {
        document.getElementById('ps-error-message').textContent = message;
    }

    document.getElementById('ps-retry').classList.toggle('d-none', !canRetry);
    goToPublicScaleStep('error', false);
}
