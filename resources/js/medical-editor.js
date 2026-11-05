/**
 * Editor de prontuário estilo documento (telas de criar e editar).
 *
 * Fachada sobre o motor de edição (TipTap/ProseMirror). O restante do sistema (chat de IA, upload)
 * deve falar apenas com `MedicalEditor`, nunca direto com o motor.
 *
 * Depende dos globais do app.js: $, sanitizeMedicalHtml, loadMedicalRecordContent, initOnlyDatePickers,
 * formatWhatsApp, redirectTo, toggleDocSidePanel, initDocSidePanels e Swal.
 */
import { Editor, Extension } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { TextStyleKit } from '@tiptap/extension-text-style';
import Highlight from '@tiptap/extension-highlight';
import TextAlign from '@tiptap/extension-text-align';
import { TableKit } from '@tiptap/extension-table';
import { TaskList, TaskItem } from '@tiptap/extension-list';
import { Placeholder } from '@tiptap/extensions';

const INDENT_STEP = 32;
const INDENT_MAX = 8;

// Recuo de parágrafo/título (margin-left em múltiplos de INDENT_STEP).
const Indent = Extension.create({
    name: 'indent',

    addGlobalAttributes() {
        return [{
            types: ['paragraph', 'heading'],
            attributes: {
                indent: {
                    default: 0,
                    parseHTML: (el) => Math.min(INDENT_MAX, Math.round((parseInt(el.style.marginLeft, 10) || 0) / INDENT_STEP)),
                    renderHTML: (attrs) => (attrs.indent ? { style: `margin-left: ${attrs.indent * INDENT_STEP}px` } : {}),
                },
            },
        }];
    },

    addCommands() {
        const change = (delta) => () => ({ state, tr, dispatch }) => {
            const { from, to } = state.selection;
            let changed = false;

            state.doc.nodesBetween(from, to, (node, pos) => {
                if (!['paragraph', 'heading'].includes(node.type.name)) return;

                const indent = Math.max(0, Math.min(INDENT_MAX, (node.attrs.indent || 0) + delta));
                if (indent !== node.attrs.indent) {
                    tr.setNodeMarkup(pos, undefined, { ...node.attrs, indent });
                    changed = true;
                }
            });

            if (changed && dispatch) dispatch(tr);
            return changed;
        };

        return { indent: change(1), outdent: change(-1) };
    },
});

// Espaçamento entre linhas por bloco (o Summernote gravava `line-height` no parágrafo; o LineHeight
// padrão do TipTap só atua em texto, e descartaria esse estilo dos prontuários antigos).
const BlockLineHeight = Extension.create({
    name: 'blockLineHeight',

    addGlobalAttributes() {
        return [{
            types: ['paragraph', 'heading'],
            attributes: {
                lineHeight: {
                    default: null,
                    parseHTML: (el) => el.style.lineHeight || null,
                    renderHTML: (attrs) => (attrs.lineHeight ? { style: `line-height: ${attrs.lineHeight}` } : {}),
                },
            },
        }];
    },

    addCommands() {
        return {
            setBlockLineHeight: (value) => ({ commands }) => {
                // Sem curto-circuito: a seleção pode ter parágrafos e títulos ao mesmo tempo.
                const paragraphs = commands.updateAttributes('paragraph', { lineHeight: value || null });
                const headings = commands.updateAttributes('heading', { lineHeight: value || null });

                return paragraphs || headings;
            },
        };
    },
});

const MedicalEditor = (function () {
    const EDITOR = '#editor';
    let editor = null;
    const DRAFT_DELAY = 800;

    let dirty = false;
    let ready = false;
    let draftKey = null;
    let autoDraft = true;
    let draftTimer = null;

    function isReady() {
        return editor !== null;
    }

    function isEmptyDoc() {
        return !editor || editor.isEmpty;
    }

    function getHTML() {
        return isEmptyDoc() ? '' : sanitizeMedicalHtml(editor.getHTML());
    }

    function getText() {
        return editor ? editor.getText({ blockSeparator: '\n' }).trim() : '';
    }

    function setHTML(html, asSaved = false) {
        const chain = editor.chain().setContent(sanitizeMedicalHtml(html), { emitUpdate: false });

        // Conteúdo carregado do servidor não entra no histórico (o desfazer não pode "apagar" o prontuário).
        if (asSaved) chain.setMeta('addToHistory', false);
        chain.run();

        if (asSaved) {
            markClean();
            return;
        }

        markDirty();
    }

    // Insere na posição do cursor (substitui a seleção, se houver).
    function insertHTML(html) {
        editor.chain().focus().insertContent(sanitizeMedicalHtml(html)).run();
        markDirty();
    }

    function setStatus(state) {
        const states = {
            clean: ['fa-check-circle text-success', 'Sem alterações'],
            dirty: ['fa-pen text-warning', 'Alterações não salvas'],
            restored: ['fa-history text-info', 'Rascunho restaurado (não salvo)'],
        };
        const [icon, text] = states[state];

        $('#docStatus').html(`<i class="fas ${icon}"></i> <span>${text}</span>`);
    }

    function markDirty() {
        if (!ready) return;

        dirty = true;
        setStatus('dirty');
        scheduleDraft();
    }

    function markClean() {
        dirty = false;
        ready = true;
        setStatus('clean');
    }

    // Chamado pelo fluxo de salvar com sucesso, antes do redirect.
    function markSaved() {
        dirty = false;
        clearDraft();
    }

    // --- Rascunho local ---
    // sessionStorage: não persiste entre sessões do navegador. O conteúdo vai cifrado com a chave dos
    // prontuários (crypto.js); sem a chave desbloqueada, o rascunho não é salvo.
    function scheduleDraft() {
        if (!draftKey || !autoDraft) return;

        clearTimeout(draftTimer);
        draftTimer = setTimeout(saveDraft, DRAFT_DELAY);
    }

    async function saveDraft() {
        try {
            const key = await getCachedMedicalRecordKey();
            if (!key) return;

            const content = await encryptMedicalContent(getHTML(), key, 'draft', draftKey);

            sessionStorage.setItem(draftKey, JSON.stringify({
                title: $('#title').val(),
                date: $('#date_of_consultation').val(),
                content: bytesToBase64(content),
            }));
        } catch (e) {
            // Rascunho é conveniência; falha de storage não deve atrapalhar a escrita.
        }
    }

    // Usado na expiração da sessão e em falha ao salvar: grava o que está em edição.
    async function saveDraftNow() {
        if (!draftKey || !dirty) return;

        clearTimeout(draftTimer);
        await saveDraft();
    }

    function clearDraft() {
        if (!draftKey) return;

        clearTimeout(draftTimer);
        try {
            sessionStorage.removeItem(draftKey);
        } catch (e) {}
    }

    async function restoreDraft() {
        if (!draftKey) return false;

        try {
            const draft = JSON.parse(sessionStorage.getItem(draftKey) || 'null');
            if (!draft || !draft.content) return false;

            const key = await unlockMedicalRecordKey();
            if (!key) return false;

            const html = await decryptMedicalContent(base64ToBytes(draft.content), key, 'draft', draftKey);
            if (!html.trim()) return false;

            editor.commands.setContent(sanitizeMedicalHtml(html), { emitUpdate: false });
            $('#title').val(draft.title || '');
            if (draft.date) $('#date_of_consultation').val(draft.date);

            return true;
        } catch (e) {
            return false;
        }
    }

    // --- Inicialização ---
    function initEditor() {
        editor = new Editor({
            element: document.querySelector(EDITOR),
            extensions: [
                StarterKit.configure({ link: { openOnClick: false, autolink: true, HTMLAttributes: { target: '_blank', rel: 'noopener noreferrer' } } }),
                TextStyleKit.configure({ backgroundColor: false, lineHeight: false }),
                Highlight.configure({ multicolor: true }),
                TextAlign.configure({ types: ['heading', 'paragraph'] }),
                TableKit.configure({ table: { resizable: true } }),
                TaskList,
                TaskItem.configure({ nested: true }),
                Indent,
                BlockLineHeight,
                Placeholder.configure({ placeholder: 'Escreva aqui...' }),
            ],
            editorProps: { attributes: { class: 'doc-page', spellcheck: 'true' } },
            onUpdate: markDirty,
            onTransaction: syncToolbar,
        });
    }

    // --- Toolbar ---
    function askLink() {
        const current = editor.getAttributes('link').href || '';

        appDialog({
            tone: 'info',
            title: current ? 'Editar link' : 'Inserir link',
            input: 'url',
            inputLabel: 'Endereço',
            inputValue: current,
            inputPlaceholder: 'https://',
            validationMessage: 'Informe um endereço válido, começando com https://',
            confirmText: 'Aplicar',
            denyText: current ? 'Remover link' : '',
            cancelText: 'Cancelar',
        }).then(function (result) {
            if (result.isDenied) {
                editor.chain().focus().extendMarkRange('link').unsetLink().run();
            } else if (result.isConfirmed && result.value) {
                editor.chain().focus().extendMarkRange('link').setLink({ href: result.value }).run();
            } else {
                editor.commands.focus();
            }
        });
    }

    const commands = {
        undo: (c) => c.undo(),
        redo: (c) => c.redo(),
        bold: (c) => c.toggleBold(),
        italic: (c) => c.toggleItalic(),
        underline: (c) => c.toggleUnderline(),
        strike: (c) => c.toggleStrike(),
        clear: (c) => c.unsetAllMarks().clearNodes().unsetTextAlign(),
        alignLeft: (c) => c.setTextAlign('left'),
        alignCenter: (c) => c.setTextAlign('center'),
        alignRight: (c) => c.setTextAlign('right'),
        alignJustify: (c) => c.setTextAlign('justify'),
        bulletList: (c) => c.toggleBulletList(),
        orderedList: (c) => c.toggleOrderedList(),
        taskList: (c) => c.toggleTaskList(),
        indent: (c) => (editor.isActive('listItem') ? c.sinkListItem('listItem') : c.indent()),
        outdent: (c) => (editor.isActive('listItem') ? c.liftListItem('listItem') : c.outdent()),
        table: (c) => c.insertTable({ rows: 3, cols: 3, withHeaderRow: false }),
        addRow: (c) => c.addRowAfter(),
        addColumn: (c) => c.addColumnAfter(),
        deleteRow: (c) => c.deleteRow(),
        deleteColumn: (c) => c.deleteColumn(),
        deleteTable: (c) => c.deleteTable(),
    };

    // Estado "ativo" de cada botão (data-cmd => argumentos de isActive).
    const activeStates = {
        bold: ['bold'], italic: ['italic'], underline: ['underline'], strike: ['strike'], link: ['link'],
        bulletList: ['bulletList'], orderedList: ['orderedList'], taskList: ['taskList'],
        alignLeft: [{ textAlign: 'left' }], alignCenter: [{ textAlign: 'center' }],
        alignRight: [{ textAlign: 'right' }], alignJustify: [{ textAlign: 'justify' }],
    };

    function currentBlock() {
        for (const level of [1, 2, 3]) {
            if (editor.isActive('heading', { level })) return 'h' + level;
        }

        return editor.isActive('blockquote') ? 'blockquote' : 'p';
    }

    function syncToolbar() {
        if (!editor) return;

        $('#docToolbar [data-cmd]').each(function () {
            const state = activeStates[this.dataset.cmd];
            $(this).toggleClass('active', !!state && editor.isActive(...state));
        });

        const text = editor.getAttributes('textStyle');
        $('#tbBlock').val(currentBlock());
        $('#tbFont').val(text.fontFamily ? String(text.fontFamily).replace(/["']/g, '') : '');
        $('#tbSize').val(text.fontSize || '');
        $('#tbLineHeight').val(editor.getAttributes('paragraph').lineHeight || editor.getAttributes('heading').lineHeight || '');
        $('.doc-tb-table').toggleClass('d-none', !editor.isActive('table'));
    }

    function bindToolbar() {
        const toolbar = $('#docToolbar');

        // mousedown + preventDefault evita que o botão roube o foco/seleção do editor.
        toolbar.on('mousedown', 'button', (e) => e.preventDefault());

        toolbar.on('click', '[data-cmd]', function () {
            const cmd = this.dataset.cmd;

            if (cmd === 'link') return askLink();

            commands[cmd](editor.chain().focus()).run();
        });

        $('#tbBlock').on('change', function () {
            const chain = editor.chain().focus();
            const v = this.value;

            if (v === 'blockquote') chain.setBlockquote().run();
            else if (v === 'p') chain.lift('blockquote').setParagraph().run();
            else chain.lift('blockquote').setHeading({ level: parseInt(v[1], 10) }).run();
        });

        $('#tbFont').on('change', function () {
            const chain = editor.chain().focus();
            this.value ? chain.setFontFamily(this.value).run() : chain.unsetFontFamily().run();
        });

        $('#tbSize').on('change', function () {
            const chain = editor.chain().focus();
            this.value ? chain.setFontSize(this.value).run() : chain.unsetFontSize().run();
        });

        $('#tbLineHeight').on('change', function () {
            const chain = editor.chain().focus();
            chain.setBlockLineHeight(this.value).run();
        });

        $('#tbColor').on('input', function () {
            editor.chain().focus().setColor(this.value).run();
        });

        $('#tbHighlight').on('input', function () {
            editor.chain().focus().setHighlight({ color: this.value }).run();
        });

        // Ctrl+K abre o diálogo de link, como no Google Docs.
        document.querySelector(EDITOR).addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                askLink();
            }
        });
    }

    function initPatientSelect() {
        $('.js-example-data-ajax').select2({
            language: 'pt-BR',
            placeholder: 'Pesquisar paciente...',
            minimumInputLength: 2,
            ajax: {
                url: '/patients/autocomplete',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term };
                },
                processResults: function (data) {
                    return {
                        results: data.map(function (item) {
                            return {
                                id: item.id,
                                text: item.name + ' - ' + formatWhatsApp(item.phone),
                                phone: item.phone
                            };
                        })
                    };
                }
            }
        });
    }

    function readIaPref(key) {
        try { return localStorage.getItem('docIa.' + key); } catch (e) { return null; }
    }

    function writeIaPref(key, value) {
        try { localStorage.setItem('docIa.' + key, value); } catch (e) { /* ignora */ }
    }

    // Painel lateral compartilhado (toggleDocSidePanel, em medical-records.js): abrir a IA fecha os Acessos e vice-versa.
    function toggleIaPanel(open, focus = true) {
        const show = toggleDocSidePanel('docIaPanel', open);

        if (!focus) return;
        if (show) $('#iaChatInput').trigger('focus'); else if (editor) editor.commands.focus();
    }

    function bindEvents() {
        $('#title, #date_of_consultation, #patient_id').on('input change', markDirty);

        $('#docIaToggle').on('click', () => toggleIaPanel());
        $('#docIaClose').on('click', () => toggleIaPanel(false));
        $('#docIaPanel')
            .on('doc-panel:opened', () => writeIaPref('open', '1'))
            .on('doc-panel:closed', () => writeIaPref('open', '0'));
        initDocSidePanels();

        if (readIaPref('open') === '1' && window.innerWidth >= 1200) toggleIaPanel(true, false);

        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'i') {
                e.preventDefault();
                toggleIaPanel();
                return;
            }

            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                $('.doc-form')[0].requestSubmit();
            }
        });

        window.addEventListener('beforeunload', function (e) {
            if (!dirty) return;

            e.preventDefault();
            e.returnValue = '';
        });
    }

    // Sem o conteúdo não dá para editar: oferece tentar de novo ou voltar, em vez de deixar a tela vazia.
    function showLoadError() {
        appDialog({
            tone: 'error',
            title: 'Não foi possível abrir o prontuário',
            text: 'Verifique sua conexão e tente novamente.',
            confirmText: 'Tentar novamente',
            cancelText: 'Voltar para a lista',
            allowOutsideClick: false,
        }).then(function (result) {
            if (result.isConfirmed) {
                window.location.reload();
                return;
            }

            redirectTo('/medical-records');
        });
    }

    function showForm() {
        $('.doc-form').removeClass('d-none');
        $('#loading').addClass('d-none');
    }

    function init({ contentLink = null, notesPath = null, draftKey: key = null, autoDraft: auto = true, loadContent = null, onLoadError = null } = {}) {
        draftKey = key;
        autoDraft = auto;

        document.body.classList.add('doc-mode');
        initOnlyDatePickers();
        initEditor();
        bindToolbar();
        syncToolbar();
        initPatientSelect();
        bindEvents();

        // Conteúdo montado por quem chamou (rascunho de transcrição). A edição guardada, se houver, vem por cima dele.
        if (loadContent) {
            loadContent().then(async function (html) {
                setHTML(html, true);

                const restoredContent = await restoreDraft();
                showForm();

                if (restoredContent) {
                    dirty = true;
                    setStatus('restored');
                }
            }).catch(onLoadError);
            return;
        }

        if (contentLink) {
            loadMedicalRecordContent(contentLink, notesPath, async function (response) {
                setHTML(response, true);

                // Edição: só existe rascunho se a sessão expirou com alterações não salvas.
                const restoredEdit = await restoreDraft();
                showForm();

                if (restoredEdit) {
                    dirty = true;
                    setStatus('restored');
                }
            }, function (error) {
                // Cancelou a senha mestre: sem a chave não há o que editar.
                if (error && error.cancelled) {
                    return redirectTo('/medical-records');
                }

                showLoadError();
            });
            return;
        }

        restoreDraft().then(function (restored) {
            showForm();
            markClean();

            if (restored) {
                dirty = true;
                setStatus('restored');
            }
        });
    }

    return { init, isReady, getHTML, getText, setHTML, insertHTML, markSaved, saveDraftNow };
})();

window.MedicalEditor = MedicalEditor;
