/**
 * Criptografia de ponta a ponta dos prontuários (Web Crypto, sem biblioteca externa).
 *
 * - Cada usuário tem uma chave AES-GCM-256 aleatória (a "chave dos prontuários"), gerada no navegador.
 * - Essa chave é guardada no servidor apenas embrulhada (cifrada) por duas chaves derivadas com PBKDF2-SHA256:
 *   uma da senha mestre e outra da chave de recuperação. Nenhuma das duas, nem a chave em claro, sai do navegador.
 * - Conteúdo cifrado: "PSI" + versão (1 byte) + iv (12 bytes) + cifra com tag do GCM.
 *   O AAD amarra o arquivo ao usuário e ao caminho no storage (não dá para trocar arquivos entre prontuários).
 * - Desbloqueada, a chave fica no IndexedDB como CryptoKey não extraível, com expiração por inatividade.
 * - Escalas respondidas pelo paciente por link: par ECDH P-256 por usuário. A pública fica no servidor e cifra
 *   as respostas no navegador do paciente; a privada fica embrulhada pela chave dos prontuários.
 * - Dinâmicas da sessão de vídeo: o mesmo par. O Jitsi cifra sem login no PSI4 e nunca decifra (formato "PSA").
 */
const MEDICAL_CRYPTO = {
    magic: [0x50, 0x53, 0x49], // "PSI"
    version: 1,
    iterations: 600000,
    idleMinutes: 30,
    minPassphraseLength: 10,
    dbName: 'psi4-medical-records',
    dbStore: 'keys',
};

let medicalRecordKeyInMemory = null;
let unlockMedicalRecordKeyPending = null;

function bytesToBase64(bytes) {
    let binary = '';
    bytes.forEach(b => binary += String.fromCharCode(b));

    return btoa(binary);
}

function base64ToBytes(base64) {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);

    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }

    return bytes;
}

function bytesToHex(bytes) {
    return Array.from(bytes).map(b => b.toString(16).padStart(2, '0')).join('');
}

function cryptoAad(...parts) {
    return new TextEncoder().encode(parts.join('|'));
}

// --- Chave de recuperação: 20 bytes aleatórios em base32 (Crockford), em grupos de 4 ---
const RECOVERY_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

function generateRecoveryKey() {
    const bytes = crypto.getRandomValues(new Uint8Array(20));
    let bits = '';
    bytes.forEach(b => bits += b.toString(2).padStart(8, '0'));

    let key = '';
    for (let i = 0; i < bits.length; i += 5) {
        key += RECOVERY_ALPHABET[parseInt(bits.slice(i, i + 5), 2)];
    }

    return key.match(/.{4}/g).join('-');
}

function normalizeRecoveryKey(value) {
    return String(value || '').toUpperCase().replace(/[^0-9A-Z]/g, '')
        .replace(/O/g, '0').replace(/[IL]/g, '1');
}

function normalizePassphrase(value) {
    return String(value || '').normalize('NFKC');
}

// --- Embrulho da chave dos prontuários ---
async function deriveWrappingKey(secret, salt, iterations) {
    const material = await crypto.subtle.importKey('raw', new TextEncoder().encode(secret), 'PBKDF2', false, ['deriveKey']);

    return crypto.subtle.deriveKey(
        { name: 'PBKDF2', salt: salt, iterations: iterations, hash: 'SHA-256' },
        material,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt']
    );
}

async function wrapRecordKey(rawKey, secret) {
    const salt = crypto.getRandomValues(new Uint8Array(16));
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const kek = await deriveWrappingKey(secret, salt, MEDICAL_CRYPTO.iterations);
    const cipher = new Uint8Array(await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv: iv, additionalData: cryptoAad('psi4-key', window.USER_UUID) }, kek, rawKey
    ));

    const wrapped = new Uint8Array(iv.length + cipher.length);
    wrapped.set(iv, 0);
    wrapped.set(cipher, iv.length);

    return { wrapped: bytesToBase64(wrapped), salt: bytesToBase64(salt), iterations: MEDICAL_CRYPTO.iterations };
}

// Lança erro se o segredo estiver errado (a tag do GCM não confere).
async function unwrapRecordKey(wrappedBase64, saltBase64, iterations, secret) {
    const wrapped = base64ToBytes(wrappedBase64);
    const kek = await deriveWrappingKey(secret, base64ToBytes(saltBase64), iterations);

    return new Uint8Array(await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: wrapped.slice(0, 12), additionalData: cryptoAad('psi4-key', window.USER_UUID) }, kek, wrapped.slice(12)
    ));
}

async function computeKeyCheck(rawKey) {
    return bytesToHex(new Uint8Array(await crypto.subtle.digest('SHA-256', rawKey)));
}

function importRecordKey(rawKey) {
    return crypto.subtle.importKey('raw', rawKey, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt']);
}

// --- Conteúdo ---
async function encryptMedicalContent(html, key, ...aad) {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const cipher = new Uint8Array(await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv: iv, additionalData: cryptoAad(window.USER_UUID, ...aad) }, key, new TextEncoder().encode(html)
    ));

    const header = [...MEDICAL_CRYPTO.magic, MEDICAL_CRYPTO.version];
    const result = new Uint8Array(header.length + iv.length + cipher.length);
    result.set(header, 0);
    result.set(iv, header.length);
    result.set(cipher, header.length + iv.length);

    return result;
}

async function decryptMedicalContent(bytes, key, ...aad) {
    const header = MEDICAL_CRYPTO.magic.length + 1;

    // Arquivo vazio = prontuário sem conteúdo.
    if (!bytes.length) {
        return '';
    }

    const validMagic = MEDICAL_CRYPTO.magic.every((b, i) => bytes[i] === b);
    if (!validMagic || bytes[MEDICAL_CRYPTO.magic.length] !== MEDICAL_CRYPTO.version) {
        throw new Error('Formato de prontuário não reconhecido.');
    }

    const iv = bytes.slice(header, header + 12);
    const plain = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: iv, additionalData: cryptoAad(window.USER_UUID, ...aad) }, key, bytes.slice(header + 12)
    );

    return new TextDecoder().decode(plain);
}

async function downloadAndDecryptMedicalRecord(url, notesPath) {
    const response = await fetch(url);

    if (!response.ok) {
        throw new Error('Não foi possível carregar o arquivo.');
    }

    const bytes = new Uint8Array(await response.arrayBuffer());

    // Dinâmica guardada na sessão de vídeo: ponto único para a visualização, a edição e a exportação.
    if (isSessionActivityContent(bytes)) {
        return renderSessionActivityHtml(await decryptSessionActivity(bytes, await getScalePrivateKey(), notesPath));
    }

    const key = await unlockMedicalRecordKey();
    if (!key) {
        const error = new Error('Prontuários bloqueados.');
        error.cancelled = true;
        throw error;
    }

    return decryptMedicalContent(bytes, key, notesPath);
}

// --- Cache da chave desbloqueada (IndexedDB, por usuário, com expiração por inatividade) ---
function openMedicalKeyDb() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(MEDICAL_CRYPTO.dbName, 1);
        request.onupgradeneeded = () => request.result.createObjectStore(MEDICAL_CRYPTO.dbStore);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function medicalKeyDbRequest(mode, callback) {
    const db = await openMedicalKeyDb();

    try {
        return await new Promise((resolve, reject) => {
            const request = callback(db.transaction(MEDICAL_CRYPTO.dbStore, mode).objectStore(MEDICAL_CRYPTO.dbStore));
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    } finally {
        db.close();
    }
}

async function cacheMedicalRecordKey(key) {
    const entry = { key: key, expiresAt: Date.now() + MEDICAL_CRYPTO.idleMinutes * 60000 };

    try {
        await medicalKeyDbRequest('readwrite', store => store.put(entry, window.USER_UUID));
    } catch (e) {
        // Sem IndexedDB (ex.: navegação privada) a chave vale só para esta página.
    }

    medicalRecordKeyInMemory = key;
}

async function getCachedMedicalRecordKey() {
    if (medicalRecordKeyInMemory) {
        return medicalRecordKeyInMemory;
    }

    try {
        const entry = await medicalKeyDbRequest('readonly', store => store.get(window.USER_UUID));

        if (!entry || entry.expiresAt < Date.now()) {
            await clearMedicalRecordKey();
            return null;
        }

        await cacheMedicalRecordKey(entry.key); // renova a expiração (inatividade)

        return entry.key;
    } catch (e) {
        return null;
    }
}

async function clearMedicalRecordKey() {
    medicalRecordKeyInMemory = null;
    scalePrivateKeyInMemory = null;

    try {
        await medicalKeyDbRequest('readwrite', store => store.delete(window.USER_UUID));
    } catch (e) {}
}

// --- Desbloqueio ---
async function fetchEncryptionKeyDetails() {
    const response = await fetch('/encryption-key/details', {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (!response.ok) {
        throw httpError('Chave dos prontuários indisponível.', response.status);
    }

    return response.json();
}

// Desbloqueia com a senha mestre e devolve a chave crua (para re-embrulhar) e a CryptoKey.
async function unlockWithPassphrase(details, passphrase) {
    const raw = await unwrapRecordKey(details.passphrase_wrapped_key, details.passphrase_salt, details.passphrase_iterations, normalizePassphrase(passphrase));

    return { raw: raw, key: await importRecordKey(raw) };
}

async function unlockWithRecoveryKey(details, recoveryKey) {
    const raw = await unwrapRecordKey(details.recovery_wrapped_key, details.recovery_salt, details.recovery_iterations, normalizeRecoveryKey(recoveryKey));

    return { raw: raw, key: await importRecordKey(raw) };
}

/**
 * Devolve a chave dos prontuários: do cache ou pedindo a senha mestre.
 * Resolve null se o usuário cancelar.
 */
async function unlockMedicalRecordKey() {
    const cached = await getCachedMedicalRecordKey();
    if (cached) {
        return cached;
    }

    if (unlockMedicalRecordKeyPending) {
        return unlockMedicalRecordKeyPending;
    }

    unlockMedicalRecordKeyPending = promptMedicalRecordPassphrase().finally(() => {
        unlockMedicalRecordKeyPending = null;
    });

    return unlockMedicalRecordKeyPending;
}

async function promptMedicalRecordPassphrase() {
    let details;

    try {
        details = await fetchEncryptionKeyDetails();
    } catch (error) {
        if (error.status === 400) {
            redirectTo('/encryption-key?redirect=' + encodeURIComponent(window.location.pathname));
        }

        return null;
    }

    // Nova aba: quem está no meio de uma evolução não perde o texto. A chave recuperada vai para o
    // IndexedDB, compartilhado entre abas, e é encontrada ao clicar em "Desbloquear" de novo.
    const recoverUrl = '/encryption-key?mode=recover&redirect=' + encodeURIComponent(window.location.pathname);

    const result = await appDialog({
        tone: 'lock',
        title: 'Desbloquear prontuários',
        text: 'Só você tem a chave. Nem a equipe PSI4 consegue ler o que você escreve.',
        content: passphraseModalHtml(recoverUrl),
        footer: '<i class="far fa-clock me-1"></i>' + passphraseSessionText(),
        confirmText: 'Desbloquear',
        confirmIcon: 'fas fa-lock-open',
        cancelText: 'Cancelar',
        allowOutsideClick: false,
        didOpen: bindPassphraseModal,
        preConfirm: async () => {
            const popup = Swal.getPopup();
            const input = popup.querySelector('#medical-passphrase');
            const passphrase = input.value;

            if (!passphrase) {
                Swal.showValidationMessage('Informe a senha mestre.');
                input.focus();
                return false;
            }

            setPassphraseModalBusy(popup, true);

            try {
                return (await unlockWithPassphrase(details, passphrase)).key;
            } catch (e) {
                setPassphraseModalBusy(popup, false);
                Swal.showValidationMessage('Senha mestre incorreta. Confira se o Caps Lock está desligado.');
                shakeAppDialog();
                input.focus();
                input.select();
                return false;
            }
        },
    });

    if (!result.isConfirmed || !result.value) {
        return null;
    }

    await cacheMedicalRecordKey(result.value);

    // Contas antigas: cria o par sem novo pedido de senha, para poder guardar dinâmicas sem login depois.
    ensureScaleKeyPair().catch((error) => console.log('Error', error));

    return result.value;
}

function passphraseModalHtml(recoverUrl) {
    return '<form class="swal-passphrase-form" autocomplete="on" onsubmit="return false;">' +
            // Identifica a credencial no gerenciador de senhas, para não sobrescrever a senha de login.
            '<input type="text" id="medical-passphrase-username" name="username" autocomplete="username" ' +
                'tabindex="-1" aria-hidden="true" class="d-none">' +
            '<label for="medical-passphrase" class="app-dialog-label">Senha mestre</label>' +
            '<div class="swal-passphrase-field">' +
                '<input type="password" id="medical-passphrase" name="password" class="form-control" ' +
                    'autocomplete="current-password" autocapitalize="off" autocorrect="off" spellcheck="false" ' +
                    'aria-describedby="medical-passphrase-capslock swal2-validation-message">' +
                '<button type="button" class="swal-passphrase-toggle" aria-label="Mostrar senha" aria-pressed="false">' +
                    '<i class="fas fa-eye"></i>' +
                '</button>' +
            '</div>' +
            '<div id="medical-passphrase-capslock" class="swal-passphrase-capslock d-none" role="status">' +
                '<i class="fas fa-exclamation-triangle me-1"></i>Caps Lock está ativado' +
            '</div>' +
            '<div class="text-end">' +
                '<a href="' + recoverUrl + '" target="_blank" rel="noopener" class="swal-passphrase-forgot">Esqueci a senha mestre</a>' +
            '</div>' +
        '</form>';
}

function passphraseSessionText() {
    const hasIndexedDb = typeof indexedDB !== 'undefined';

    return hasIndexedDb
        ? 'Fica desbloqueado neste navegador por ' + MEDICAL_CRYPTO.idleMinutes + ' min sem uso.'
        : 'Fica desbloqueado só enquanto esta página estiver aberta.';
}

function bindPassphraseModal(popup) {
    const input = popup.querySelector('#medical-passphrase');
    const toggle = popup.querySelector('.swal-passphrase-toggle');
    const capsLock = popup.querySelector('#medical-passphrase-capslock');

    popup.querySelector('#medical-passphrase-username').value = window.USER_EMAIL || window.USER_UUID || '';

    toggle.addEventListener('click', () => {
        const show = input.type === 'password';

        input.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(show));
        toggle.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
        toggle.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        input.focus();
    });

    const updateCapsLock = (event) => {
        if (typeof event.getModifierState === 'function') {
            capsLock.classList.toggle('d-none', !event.getModifierState('CapsLock'));
        }
    };

    input.addEventListener('keydown', updateCapsLock);
    input.addEventListener('keyup', updateCapsLock);

    input.addEventListener('input', () => Swal.resetValidationMessage());

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            Swal.clickConfirm();
        }
    });

    input.focus();
}

// A derivação da chave (PBKDF2, 600 mil iterações) pode levar alguns segundos no celular.
function setPassphraseModalBusy(popup, busy) {
    popup.querySelector('#medical-passphrase').disabled = busy;
    popup.querySelector('.swal-passphrase-toggle').disabled = busy;

    setAppDialogBusy(busy, 'Desbloqueando…');
}

// Sair da conta descarta a chave desbloqueada deste navegador.
function logoutClearingMedicalKey(event) {
    event.preventDefault();
    clearMedicalRecordKey().finally(() => document.getElementById('logout-form').submit());
}


// --- Escalas respondidas pelo paciente por link (ECDH P-256 + HKDF-SHA256 + AES-GCM-256) ---
// Conteúdo: "PSE" + versão (1 byte) + pública efêmera do paciente (65 bytes) + iv (12 bytes) + cifra com tag.
// O AAD amarra o arquivo à escala e ao caminho no storage.
const SCALE_LINK_CRYPTO = {
    magic: [0x50, 0x53, 0x45], // "PSE"
    version: 1,
    curve: { name: 'ECDH', namedCurve: 'P-256' },
    publicKeyLength: 65,
};

let scalePrivateKeyInMemory = null;

function scaleLinkAad(scale, contentPath) {
    return cryptoAad('psi4-scale-link', scale, contentPath);
}

async function deriveScaleLinkKey(privateKey, publicKey, salt, usage, info = 'psi4-scale-link') {
    const secret = await crypto.subtle.deriveBits({ name: 'ECDH', public: publicKey }, privateKey, 256);
    const material = await crypto.subtle.importKey('raw', secret, 'HKDF', false, ['deriveKey']);

    return crypto.subtle.deriveKey(
        { name: 'HKDF', hash: 'SHA-256', salt: salt, info: new TextEncoder().encode(info) },
        material,
        { name: 'AES-GCM', length: 256 },
        false,
        [usage]
    );
}

// Navegador do paciente: só a psicóloga (com a chave privada) consegue decifrar.
async function encryptScaleForPsychologist(text, publicKeyBase64, scale, contentPath) {
    const recipient = await crypto.subtle.importKey('spki', base64ToBytes(publicKeyBase64), SCALE_LINK_CRYPTO.curve, false, []);
    const ephemeral = await crypto.subtle.generateKey(SCALE_LINK_CRYPTO.curve, true, ['deriveBits']);
    const ephemeralPublic = new Uint8Array(await crypto.subtle.exportKey('raw', ephemeral.publicKey));
    const key = await deriveScaleLinkKey(ephemeral.privateKey, recipient, ephemeralPublic, 'encrypt');
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const cipher = new Uint8Array(await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv: iv, additionalData: scaleLinkAad(scale, contentPath) }, key, new TextEncoder().encode(text)
    ));

    const header = [...SCALE_LINK_CRYPTO.magic, SCALE_LINK_CRYPTO.version];
    const result = new Uint8Array(header.length + ephemeralPublic.length + iv.length + cipher.length);
    result.set(header, 0);
    result.set(ephemeralPublic, header.length);
    result.set(iv, header.length + ephemeralPublic.length);
    result.set(cipher, header.length + ephemeralPublic.length + iv.length);

    return result;
}

async function decryptScaleFromPatient(bytes, privateKey, scale, contentPath) {
    const header = SCALE_LINK_CRYPTO.magic.length + 1;
    const validMagic = SCALE_LINK_CRYPTO.magic.every((b, i) => bytes[i] === b);

    if (!validMagic || bytes[SCALE_LINK_CRYPTO.magic.length] !== SCALE_LINK_CRYPTO.version) {
        throw new Error('Formato de escala não reconhecido.');
    }

    const ephemeralPublic = bytes.slice(header, header + SCALE_LINK_CRYPTO.publicKeyLength);
    const iv = bytes.slice(header + SCALE_LINK_CRYPTO.publicKeyLength, header + SCALE_LINK_CRYPTO.publicKeyLength + 12);
    const sender = await crypto.subtle.importKey('raw', ephemeralPublic, SCALE_LINK_CRYPTO.curve, false, []);
    const key = await deriveScaleLinkKey(privateKey, sender, ephemeralPublic, 'decrypt');
    const plain = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: iv, additionalData: scaleLinkAad(scale, contentPath) }, key, bytes.slice(header + SCALE_LINK_CRYPTO.publicKeyLength + 12)
    );

    return new TextDecoder().decode(plain);
}

async function wrapScalePrivateKey(pkcs8, recordKey) {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const cipher = new Uint8Array(await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv: iv, additionalData: cryptoAad('psi4-scale-private-key', window.USER_UUID) }, recordKey, pkcs8
    ));

    const wrapped = new Uint8Array(iv.length + cipher.length);
    wrapped.set(iv, 0);
    wrapped.set(cipher, iv.length);

    return bytesToBase64(wrapped);
}

async function unwrapScalePrivateKey(wrappedBase64, recordKey) {
    const wrapped = base64ToBytes(wrappedBase64);
    const pkcs8 = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: wrapped.slice(0, 12), additionalData: cryptoAad('psi4-scale-private-key', window.USER_UUID) }, recordKey, wrapped.slice(12)
    );

    return crypto.subtle.importKey('pkcs8', pkcs8, SCALE_LINK_CRYPTO.curve, false, ['deriveBits']);
}

// Responde ao desafio do servidor: só quem abriu a chave privada calcula este valor, e nem ela nem a senha saem daqui.
async function proveScalePrivateKey(privateKey, serverPublicKeyBase64, info) {
    const server = await crypto.subtle.importKey('raw', base64ToBytes(serverPublicKeyBase64), SCALE_LINK_CRYPTO.curve, false, []);
    const secret = await crypto.subtle.deriveBits({ name: 'ECDH', public: server }, privateKey, 256);
    const key = await crypto.subtle.importKey('raw', secret, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);

    return bytesToBase64(new Uint8Array(await crypto.subtle.sign('HMAC', key, new TextEncoder().encode(info))));
}

/**
 * Garante o par de chaves das escalas (gerado no primeiro link). Resolve false se a psicóloga cancelar o desbloqueio.
 */
async function ensureScaleKeyPair() {
    const recordKey = await unlockMedicalRecordKey();
    if (!recordKey) {
        return false;
    }

    const details = await fetchEncryptionKeyDetails();
    if (details.scale_public_key) {
        return true;
    }

    const pair = await crypto.subtle.generateKey(SCALE_LINK_CRYPTO.curve, true, ['deriveBits']);
    const publicKey = bytesToBase64(new Uint8Array(await crypto.subtle.exportKey('spki', pair.publicKey)));
    const pkcs8 = new Uint8Array(await crypto.subtle.exportKey('pkcs8', pair.privateKey));

    const response = await fetch('/encryption-key/scale-key-pair', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
        },
        body: JSON.stringify({ public_key: publicKey, wrapped_private_key: await wrapScalePrivateKey(pkcs8, recordKey) }),
    });

    // 400: outra aba gerou o par ao mesmo tempo; vale o que ficou gravado.
    if (!response.ok && response.status !== 400) {
        throw httpError('Não foi possível configurar a chave das escalas.', response.status);
    }

    return true;
}

async function getScalePrivateKey() {
    if (scalePrivateKeyInMemory) {
        return scalePrivateKeyInMemory;
    }

    const recordKey = await unlockMedicalRecordKey();
    if (!recordKey) {
        const error = new Error('Prontuários bloqueados.');
        error.cancelled = true;
        throw error;
    }

    const details = await fetchEncryptionKeyDetails();
    if (!details.scale_wrapped_private_key) {
        throw new Error('Chave das escalas indisponível.');
    }

    scalePrivateKeyInMemory = await unwrapScalePrivateKey(details.scale_wrapped_private_key, recordKey);

    return scalePrivateKeyInMemory;
}

async function downloadAndDecryptScaleFromPatient(url, scale, contentPath) {
    const response = await fetch(url);

    if (!response.ok) {
        throw new Error('Não foi possível carregar o arquivo.');
    }

    const privateKey = await getScalePrivateKey();

    return decryptScaleFromPatient(new Uint8Array(await response.arrayBuffer()), privateKey, scale, contentPath);
}

// --- Dinâmicas da sessão de vídeo (ECDH P-256 + HKDF-SHA256 + AES-GCM-256, com o par das escalas) ---
// Conteúdo: "PSA" + versão (1 byte) + pública efêmera (65 bytes) + iv (12 bytes) + cifra com tag.
// O JSON é montado como HTML só aqui: um token de moderador não pode plantar HTML na página que tem a chave.
const SESSION_ACTIVITY_CRYPTO = {
    magic: [0x50, 0x53, 0x41], // "PSA"
    version: 1,
    info: 'psi4-session-activity',
    maxText: 4000,
    maxItems: 60,
};

function isSessionActivityContent(bytes) {
    return SESSION_ACTIVITY_CRYPTO.magic.every((b, i) => bytes[i] === b);
}

function sessionActivityUuid(path) {
    const match = /(?:sa-|session-activities\/)([0-9a-f-]{36})\.psa$/.exec(path || '');

    if (!match) {
        throw new Error('Registro de dinâmica não reconhecido.');
    }

    return match[1];
}

async function decryptSessionActivity(bytes, privateKey, path) {
    const header = SESSION_ACTIVITY_CRYPTO.magic.length + 1;

    if (!isSessionActivityContent(bytes) || bytes[SESSION_ACTIVITY_CRYPTO.magic.length] !== SESSION_ACTIVITY_CRYPTO.version) {
        throw new Error('Registro de dinâmica não reconhecido.');
    }

    const keyLength = SCALE_LINK_CRYPTO.publicKeyLength;
    const uuid = sessionActivityUuid(path);
    const ephemeralPublic = bytes.slice(header, header + keyLength);
    const iv = bytes.slice(header + keyLength, header + keyLength + 12);
    const sender = await crypto.subtle.importKey('raw', ephemeralPublic, SCALE_LINK_CRYPTO.curve, false, []);
    const key = await deriveScaleLinkKey(privateKey, sender, ephemeralPublic, 'decrypt', SESSION_ACTIVITY_CRYPTO.info);
    const plain = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: iv, additionalData: cryptoAad(SESSION_ACTIVITY_CRYPTO.info, uuid) }, key, bytes.slice(header + keyLength + 12)
    );

    return validateSessionActivityRecord(JSON.parse(new TextDecoder().decode(plain)), uuid);
}

async function downloadAndDecryptSessionActivity(url, path) {
    const response = await fetch(url);

    if (!response.ok) {
        throw new Error('Não foi possível carregar o arquivo.');
    }

    return decryptSessionActivity(new Uint8Array(await response.arrayBuffer()), await getScalePrivateKey(), path);
}

// O conteúdo veio de fora da origem que tem a chave: tudo é conferido contra o esquema antes de ser usado.
function validateSessionActivityRecord(record, uuid) {
    const fail = () => {
        throw new Error('Registro de dinâmica fora do formato.');
    };
    const isText = (value) => value === undefined || value === null || (typeof value === 'string' && value.length <= SESSION_ACTIVITY_CRYPTO.maxText);
    const isScale = (value) => value === undefined || value === null || (Number.isInteger(value) && value >= 0 && value <= 10);
    const isList = (value) => Array.isArray(value) && value.length <= SESSION_ACTIVITY_CRYPTO.maxItems;
    const allText = (object, keys) => keys.every((key) => isText(object[key]));

    if (!record || typeof record !== 'object' || record.uuid !== uuid || !record.data || typeof record.data !== 'object'
        || !allText(record, ['title', 'notes'])) {
        fail();
    }

    const data = record.data;
    const valid = {
        emotion_wheel: () => allText(data, ['emotion', 'feeling', 'body', 'cause']) && isScale(data.intensity),
        thermometer: () => isScale(data.initial) && isScale(data.final) && isText(data.comment),
        control_circles: () => ['control', 'influence', 'outside'].every((area) => isList(data[area]) && data[area].every((item) => typeof item === 'string' && isText(item))),
        question_cards: () => isList(data.cards) && data.cards.every((card) => card && typeof card === 'object' && allText(card, ['category', 'level', 'text', 'note'])
            && ['answered', 'skipped', 'pending'].includes(card.status)),
        life_timeline: () => isList(data.events) && data.events.every((event) => event && typeof event === 'object' && allText(event, ['period', 'type', 'text'])),
    }[record.activity];

    if (!valid || !valid()) {
        fail();
    }

    return record;
}

function escapeSessionActivityText(value) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

    return String(value === undefined || value === null ? '' : value).replace(/[&<>"']/g, (char) => map[char]);
}

function renderSessionActivityHtml(record) {
    const text = escapeSessionActivityText;
    const data = record.data;
    const line = (label, value) => (value === undefined || value === null || value === ''
        ? ''
        : '<p><strong>' + text(label) + ':</strong> ' + text(value) + '</p>');
    const list = (items) => (items.length ? '<ul>' + items.map((item) => '<li>' + text(item) + '</li>').join('') + '</ul>' : '<p>Nenhuma situação.</p>');
    const body = {
        emotion_wheel: () => line('Emoção escolhida', data.emotion) + line('O que estou sentindo', data.feeling) + line('Onde sinto no corpo', data.body)
            + line('O que pode ter provocado', data.cause) + line('Intensidade percebida (0 a 10)', data.intensity),
        thermometer: () => line('Marcação inicial (0 a 10)', data.initial) + line('Marcação final (0 a 10)', data.final) + line('Comentário', data.comment),
        control_circles: () => '<h4>Está sob meu controle</h4>' + list(data.control) + '<h4>Posso influenciar</h4>' + list(data.influence)
            + '<h4>Não está sob meu controle</h4>' + list(data.outside),
        question_cards: () => data.cards.map((card) => '<p><strong>' + text(card.text) + '</strong><br>'
            + text({ answered: 'Respondida', skipped: 'Prefiriu não responder', pending: 'Sem resposta registrada' }[card.status])
            + (card.note ? ': ' + text(card.note) : '') + '</p>').join(''),
        life_timeline: () => (data.events.length
            ? '<ul>' + data.events.map((event) => '<li>' + (event.period ? '<strong>' + text(event.period) + '</strong>: ' : '') + text(event.text)
                + (event.type ? ' (' + text(event.type) + ')' : '') + '</li>').join('') + '</ul>'
            : '<p>Nenhum acontecimento.</p>'),
    }[record.activity]();

    return '<h3>' + text(record.title) + '</h3>'
        + '<p><em>Registro de dinâmica de apoio realizada na sessão de vídeo. Não é teste psicológico.</em></p>'
        + body
        + line('Observações da psicóloga', record.notes);
}

// --- Rascunho da sessão de vídeo ---
// "PSN" + versão (1 byte) + pública efêmera (65 bytes) + iv (12 bytes) + cifra com tag. A fila do PSI4 cifra com a
// chave pública logo depois de gerar o rascunho e nunca decifra. O JSON é montado como HTML só aqui, com cada campo
// escapado.
const SESSION_NOTE_CRYPTO = {
    magic: [0x50, 0x53, 0x4e], // "PSN"
    version: 1,
    info: 'psi4-session-note',
    fields: [
        ['evolucao', 'Evolução'],
        ['procedimentos', 'Procedimentos'],
        ['observacoes', 'Observações'],
    ],
    maxField: 6000,
    maxText: 20000,
    maxItems: 5000,
};

async function decryptSessionNote(bytes, privateKey, uuid) {
    const magic = SESSION_NOTE_CRYPTO.magic;
    const header = magic.length + 1;

    if (!magic.every((b, i) => bytes[i] === b) || bytes[magic.length] !== SESSION_NOTE_CRYPTO.version) {
        throw new Error('Rascunho não reconhecido.');
    }

    const keyLength = SCALE_LINK_CRYPTO.publicKeyLength;
    const ephemeralPublic = bytes.slice(header, header + keyLength);
    const iv = bytes.slice(header + keyLength, header + keyLength + 12);
    const sender = await crypto.subtle.importKey('raw', ephemeralPublic, SCALE_LINK_CRYPTO.curve, false, []);
    const key = await deriveScaleLinkKey(privateKey, sender, ephemeralPublic, 'decrypt', SESSION_NOTE_CRYPTO.info);
    // O uuid no AAD impede trocar o rascunho de uma sessão pelo de outra.
    const plain = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: iv, additionalData: cryptoAad(SESSION_NOTE_CRYPTO.info, uuid) }, key, bytes.slice(header + keyLength + 12)
    );

    return validateSessionNote(JSON.parse(new TextDecoder().decode(plain)), uuid);
}

function validateSessionNote(note, uuid) {
    const isTime = (value) => Number.isInteger(value) && value >= 0;
    const isList = (value) => Array.isArray(value) && value.length <= SESSION_NOTE_CRYPTO.maxItems;
    const valid = note && typeof note === 'object' && note.uuid === uuid
        && note.draft && typeof note.draft === 'object'
        && SESSION_NOTE_CRYPTO.fields.every(([field]) => typeof note.draft[field] === 'string' && note.draft[field].length <= SESSION_NOTE_CRYPTO.maxField)
        && isList(note.transcript) && isList(note.gaps)
        && note.transcript.every((s) => s && (s.channel === 0 || s.channel === 1) && isTime(s.startMs)
            && typeof s.text === 'string' && s.text.length <= SESSION_NOTE_CRYPTO.maxText)
        && note.gaps.every((g) => g && isTime(g.startMs) && isTime(g.endMs) && g.endMs >= g.startMs);

    if (!valid) {
        throw new Error('Rascunho fora do formato.');
    }

    return note;
}

function formatSessionTranscriptTime(ms) {
    const seconds = Math.floor(ms / 1000);
    const pad = (value) => String(value).padStart(2, '0');

    return pad(Math.floor(seconds / 60)) + ':' + pad(seconds % 60);
}

// O que vai para o editor: só o rascunho, que ela edita. A transcrição fica fora dele.
function renderSessionNoteHtml(note) {
    const text = escapeSessionActivityText;
    const paragraphs = (value) => value.split(/\n+/).filter((line) => line.trim()).map((line) => '<p>' + text(line) + '</p>').join('');
    const filled = SESSION_NOTE_CRYPTO.fields.filter(([field]) => note.draft[field].trim());

    if (!filled.length) {
        return '<p><em>A IA não gerou um rascunho para esta sessão. Escreva o registro a partir da transcrição.</em></p>';
    }

    return filled.map(([field, label]) => '<h3>' + label + '</h3>' + paragraphs(note.draft[field])).join('');
}

function renderSessionTranscriptHtml(note) {
    const text = escapeSessionActivityText;
    const events = note.transcript.map((segment) => ({ at: segment.startMs, segment }));

    note.gaps.forEach((gap) => {
        const minutes = Math.max(1, Math.round((gap.endMs - gap.startMs) / 60000));

        events.push({ at: gap.startMs, html: '<p><em>[trecho não gravado: ' + minutes + ' min]</em></p>' });
    });

    events.sort((a, b) => a.at - b.at);

    let html = '';
    let open = null;

    const close = () => {
        if (open) {
            html += '<p><strong>' + (open.channel === 0 ? 'Psicóloga' : 'Paciente') + ' (' + formatSessionTranscriptTime(open.startMs) + '):</strong> ' + text(open.lines.join(' ')) + '</p>';
            open = null;
        }
    };

    events.forEach((event) => {
        if (event.html) {
            close();
            html += event.html;
        } else if (open && open.channel === event.segment.channel) {
            open.lines.push(event.segment.text);
        } else {
            close();
            open = { channel: event.segment.channel, startMs: event.segment.startMs, lines: [event.segment.text] };
        }
    });
    close();

    return html;
}

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
