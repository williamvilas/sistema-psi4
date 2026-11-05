function cmd(command) {
    document.execCommand(command, false, null);
    updateOutput();
}
function cmdArg(command, arg) {
    document.execCommand(command, false, arg);
    updateOutput();
}

function updateOutput() {
    document.getElementById('htmlOutput').value = document.getElementById('editor').innerHTML;
}

document.getElementById('editor').addEventListener('input', updateOutput);

// Permite pular linha após uma imagem: se Enter for pressionado com o caret
// dentro ou imediatamente após um `.image-wrapper`, insere um parágrafo vazio depois da imagem.
document.getElementById('editor').addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return;
    const range = sel.getRangeAt(0);
    let node = range.startContainer;

    // se estiver em um text node, procurar wrapper pai (imagem/video/html)
    function findMediaWrapper(n) {
        while (n && n !== document.body) {
            if (n.nodeType === 1 && n.classList) {
                if (n.classList.contains('image-wrapper') || n.classList.contains('video-wrapper') || n.classList.contains('html-wrapper')) return n;
            }
            n = n.parentNode;
        }
        return null;
    }

    const wrapper = findMediaWrapper(node);
    // também handle caso caret esteja imediatamente depois do wrapper (node may be editor and offset after wrapper)
    if (!wrapper) {
        // se startContainer é o editor e offset referencia um child anterior que é .image-wrapper
        if (node === document.getElementById('editor')) {
            const child = node.childNodes[range.startOffset - 1];
            if (child && child.classList && child.classList.contains && (child.classList.contains('image-wrapper') || child.classList.contains('video-wrapper') || child.classList.contains('html-wrapper'))) {
                e.preventDefault();
                const p = document.createElement('p');
                p.innerHTML = '<br>';
                if (child.nextSibling) node.insertBefore(p, child.nextSibling); else node.appendChild(p);
                // colocar caret dentro do parágrafo
                const newRange = document.createRange();
                newRange.setStart(p, 0);
                newRange.collapse(true);
                sel.removeAllRanges();
                sel.addRange(newRange);
                updateOutput();
            }
        }
        return;
    }

    // se encontrou wrapper, insere parágrafo depois
    e.preventDefault();
    const parent = wrapper.parentNode;
    const p = document.createElement('p');
    p.innerHTML = '<br>';
    if (wrapper.nextSibling) parent.insertBefore(p, wrapper.nextSibling); else parent.appendChild(p);
    const newRange = document.createRange();
    newRange.setStart(p, 0);
    newRange.collapse(true);
    sel.removeAllRanges();
    sel.addRange(newRange);
    updateOutput();
});

// Botão visível para abrir o seletor de arquivos
document.getElementById('imageBtn').addEventListener('click', function() {
    document.getElementById('imageInput').click();
});

document.getElementById('imageInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(event) {
        const img = new Image();
        img.onload = function() {
            // Redimensionar se for muito grande
            const maxWidth = 800;
            let width = img.width;
            let height = img.height;

            if (width > maxWidth) {
                height = height * (maxWidth / width);
                width = maxWidth;
            }

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);

            const dataUrl = canvas.toDataURL('image/jpeg', 0.9);

            insertImage(dataUrl);
        };
        img.src = event.target.result;
    };
    reader.readAsDataURL(file);
});

function insertImage(src) {
    const wrapper = document.createElement('div');
    wrapper.className = 'image-wrapper';
    // usa display block + largura fixa do wrapper para permitir alinhamento via margin
    wrapper.style.display = 'block';
    wrapper.style.margin = '8px auto';
    // evitar que o wrapper e controles sejam editáveis dentro do contentEditable
    wrapper.contentEditable = 'false';
    wrapper.tabIndex = -1;

    const img = document.createElement('img');
    img.src = src;
    img.style.maxWidth = '100%';
    img.style.display = 'block';
    // quando imagem carregar, ajustar a largura do wrapper para a largura atual da imagem
    img.addEventListener('load', () => {
        try {
            const w = img.getBoundingClientRect().width || img.naturalWidth;
            wrapper.style.width = (w ? (Math.round(w) + 'px') : 'auto');
            updateOutput();
        } catch (e) {}
    });

    wrapper.appendChild(img);

    // --- Selection handles and floating toolbar (Word-like) ---
    const dirs = ['nw','n','ne','e','se','s','sw','w'];
    const handles = {};
    dirs.forEach(dir => {
        const h = document.createElement('div');
        h.className = 'img-handle ' + dir;
        // position handles around the wrapper
        if (dir === 'nw') { h.style.left = '-7px'; h.style.top = '-7px'; }
        if (dir === 'ne') { h.style.right = '-7px'; h.style.top = '-7px'; }
        if (dir === 'sw') { h.style.left = '-7px'; h.style.bottom = '-7px'; }
        if (dir === 'se') { h.style.right = '-7px'; h.style.bottom = '-7px'; }
        if (dir === 'n') { h.style.left = '50%'; h.style.top = '-7px'; h.style.transform = 'translateX(-50%)'; }
        if (dir === 's') { h.style.left = '50%'; h.style.bottom = '-7px'; h.style.transform = 'translateX(-50%)'; }
        if (dir === 'w') { h.style.left = '-7px'; h.style.top = '50%'; h.style.transform = 'translateY(-50%)'; }
        if (dir === 'e') { h.style.right = '-7px'; h.style.top = '50%'; h.style.transform = 'translateY(-50%)'; }
        wrapper.appendChild(h);
        handles[dir] = h;
    });

    const toolbar = document.createElement('div');
    toolbar.className = 'image-toolbar';
    const tLeft = document.createElement('button'); tLeft.type = 'button'; tLeft.title = 'Alinhar à esquerda'; tLeft.innerHTML = '<i class="fas fa-align-left"></i>';
    const tCenter = document.createElement('button'); tCenter.type = 'button'; tCenter.title = 'Centralizar'; tCenter.innerHTML = '<i class="fas fa-align-center"></i>';
    const tRight = document.createElement('button'); tRight.type = 'button'; tRight.title = 'Alinhar à direita'; tRight.innerHTML = '<i class="fas fa-align-right"></i>';
    toolbar.appendChild(tLeft); toolbar.appendChild(tCenter); toolbar.appendChild(tRight);
    wrapper.appendChild(toolbar);

    function setWrapperAlign(mode) {
        if (mode === 'left') { wrapper.style.display = 'block'; wrapper.style.margin = '8px 0 8px 0'; wrapper.style.marginLeft = '0'; wrapper.style.marginRight = ''; }
        if (mode === 'center') { wrapper.style.display = 'block'; wrapper.style.margin = '8px auto'; }
        if (mode === 'right') { wrapper.style.display = 'block'; wrapper.style.margin = '8px 0 8px 0'; wrapper.style.marginLeft = 'auto'; wrapper.style.marginRight = '0'; }
    }
    tLeft.addEventListener('click', (e) => { e.stopPropagation(); setWrapperAlign('left'); updateOutput(); });
    tCenter.addEventListener('click', (e) => { e.stopPropagation(); setWrapperAlign('center'); updateOutput(); });
    tRight.addEventListener('click', (e) => { e.stopPropagation(); setWrapperAlign('right'); updateOutput(); });

    // show selection when clicking image wrapper
    wrapper.addEventListener('click', function(e) {
        e.stopPropagation();
        document.querySelectorAll('.image-wrapper.selected').forEach(w => w.classList.remove('selected'));
        wrapper.classList.add('selected');
    });

    // global click to clear selection (init once)
    if (!window._imageSelectionGlobalInit) {
        window._imageSelectionGlobalInit = true;
        document.addEventListener('click', () => {
            document.querySelectorAll('.image-wrapper.selected, .table-wrapper.selected').forEach(w => {
                w.classList.remove('selected');
                w.style.outline = '';
            });
        });
    }

    // permitir apagar imagem selecionada com Backspace/Delete
    if (!window._imageDeleteHandlerInit) {
        window._imageDeleteHandlerInit = true;
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Backspace' && e.key !== 'Delete') return;
            const selected = document.querySelectorAll('.image-wrapper.selected, .table-wrapper.selected');
            if (!selected || selected.length === 0) return;
            e.preventDefault();
            selected.forEach(wrapper => {
                const editor = document.getElementById('editor');
                const next = wrapper.nextSibling;
                wrapper.remove();
                // place caret into following paragraph or create one
                let p;
                if (next && next.nodeType === 1 && next.tagName && next.tagName.toLowerCase() === 'p') {
                    p = next;
                } else {
                    p = document.createElement('p'); p.innerHTML = '<br>';
                    if (next) editor.insertBefore(p, next); else editor.appendChild(p);
                }
                const sel = window.getSelection();
                const range = document.createRange();
                range.setStart(p, 0); range.collapse(true);
                sel.removeAllRanges(); sel.addRange(range);
            });
            updateOutput();
        });
    }

    // pointer handlers for handles
    const aspect = img.naturalWidth && img.naturalHeight ? img.naturalWidth / img.naturalHeight : null;
    Object.keys(handles).forEach(dir => {
        const h = handles[dir];
        h.addEventListener('mousedown', startDrag);
        h.addEventListener('touchstart', startDrag, { passive: false });
    });

    function startDrag(e) {
        e.preventDefault(); e.stopPropagation();
        const handle = e.currentTarget;
        const dir = handle.className.split(' ').pop();
        const clientX = (e.touches && e.touches[0]) ? e.touches[0].clientX : e.clientX;
        const clientY = (e.touches && e.touches[0]) ? e.touches[0].clientY : e.clientY;
        const rect = img.getBoundingClientRect();
        const startW = rect.width;
        const startH = rect.height;
        const startLeft = rect.left;
        const startTop = rect.top;

        function onMove(ev) {
            const mx = (ev.touches && ev.touches[0]) ? ev.touches[0].clientX : ev.clientX;
            const my = (ev.touches && ev.touches[0]) ? ev.touches[0].clientY : ev.clientY;
            const dx = mx - clientX;
            const dy = my - clientY;

            let newW = startW;
            let newH = startH;

            // horizontal change
            if (dir.includes('e')) newW = Math.max(50, startW + dx);
            if (dir.includes('w')) newW = Math.max(50, startW - dx);
            // vertical change
            if (dir.includes('s')) newH = Math.max(50, startH + dy);
            if (dir.includes('n')) newH = Math.max(50, startH - dy);

            // maintain aspect ratio when possible
            if (aspect) {
                if (dir === 'n' || dir === 's') {
                    newW = Math.max(50, Math.round(newH * aspect));
                } else if (dir === 'e' || dir === 'w') {
                    newH = Math.max(50, Math.round(newW / aspect));
                } else {
                    // corner: prefer horizontal change magnitude
                    newH = Math.max(50, Math.round(newW / aspect));
                }
            }

            img.style.width = newW + 'px';
            img.style.height = 'auto';
            // keep wrapper sized to image so margins/toolbar align correctly
            wrapper.style.width = img.style.width;
            updateOutput();
        }

        function onUp() {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
            document.removeEventListener('touchmove', onMove);
            document.removeEventListener('touchend', onUp);
        }

        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
        document.addEventListener('touchmove', onMove, { passive: false });
        document.addEventListener('touchend', onUp);
    }

    const editor = document.getElementById('editor');
    // tentar inserir no caret se houver seleção dentro do editor
    const sel = window.getSelection();
    let inserted = false;
    if (sel && sel.rangeCount > 0) {
        const range = sel.getRangeAt(0);
        let container = range.commonAncestorContainer;
        if (container.nodeType === 3) container = container.parentNode;
        if (editor.contains(container)) {
            range.collapse(true);
            range.insertNode(wrapper);
            inserted = true;
        }
    }
    if (!inserted) editor.appendChild(wrapper);

    // colocar um parágrafo vazio depois da imagem e posicionar o caret nele
    const p = document.createElement('p'); p.innerHTML = '<br>';
    if (wrapper.nextSibling) editor.insertBefore(p, wrapper.nextSibling); else editor.appendChild(p);
    const newRange = document.createRange(); newRange.setStart(p, 0); newRange.collapse(true);
    sel.removeAllRanges(); sel.addRange(newRange);

    updateOutput();
}

// HTML block modal logic
const htmlBtn = document.getElementById('htmlBtn');
const htmlModal = document.getElementById('htmlModal');
const htmlTextarea = document.getElementById('htmlModalTextarea');
const insertHtmlBtn = document.getElementById('insertHtmlBtn');
const cancelHtmlBtn = document.getElementById('cancelHtmlBtn');

if (htmlBtn) htmlBtn.addEventListener('click', (e) => { e.stopPropagation(); showHtmlModal(); });

function showHtmlModal() {
    htmlTextarea.value = '';
    htmlModal.style.display = 'block';
    htmlModal.setAttribute('aria-hidden','false');
    htmlTextarea.focus();
}

function hideHtmlModal() {
    htmlModal.style.display = 'none';
    htmlModal.setAttribute('aria-hidden','true');
}

insertHtmlBtn.addEventListener('click', () => {
    const html = htmlTextarea.value || '';
    if (!html.trim()) { hideHtmlModal(); return; }
    insertHtmlBlock(html);
    hideHtmlModal();
});
cancelHtmlBtn.addEventListener('click', () => hideHtmlModal());

function insertHtmlBlock(html) {
    const wrapper = document.createElement('div');
    wrapper.className = 'html-wrapper';
    wrapper.contentEditable = 'false';
    wrapper.tabIndex = -1;

    const inner = document.createElement('div');
    inner.className = 'html-inner';
    inner.contentEditable = 'true';
    // sanitize minimally by allowing the user-provided HTML (developer note: consider sanitizing)
    inner.innerHTML = html;

    wrapper.appendChild(inner);
    attachHtmlActions(wrapper);

    const editor = document.getElementById('editor');
    const sel = window.getSelection();
    let inserted = false;
    if (sel && sel.rangeCount > 0) {
        const range = sel.getRangeAt(0);
        let container = range.commonAncestorContainer;
        if (container.nodeType === 3) container = container.parentNode;
        if (editor.contains(container)) {
            range.collapse(true);
            range.insertNode(wrapper);
            inserted = true;
        }
    }
    if (!inserted) editor.appendChild(wrapper);

    // place caret after the block
    const p = document.createElement('p'); p.innerHTML = '<br>';
    if (wrapper.nextSibling) editor.insertBefore(p, wrapper.nextSibling); else editor.appendChild(p);
    const newRange = document.createRange(); newRange.setStart(p, 0); newRange.collapse(true);
    sel.removeAllRanges(); sel.addRange(newRange);
    updateOutput();
}

function attachHtmlActions(wrapper) {
    if (!wrapper || wrapper._hasActions) return;
    const actions = document.createElement('div');
    actions.className = 'html-actions';

    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.title = 'Remover bloco HTML';
    deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
    deleteBtn.addEventListener('click', function(e) {
        e.preventDefault(); e.stopPropagation();
        // remove wrapper and place caret after where it was
        const editor = document.getElementById('editor');
        const next = wrapper.nextSibling;
        wrapper.remove();
        // ensure there's a paragraph to place caret into
        let p;
        if (next && next.nodeType === 1 && next.tagName.toLowerCase() === 'p') {
            p = next;
        } else {
            p = document.createElement('p'); p.innerHTML = '<br>';
            if (next) editor.insertBefore(p, next); else editor.appendChild(p);
        }
        const sel = window.getSelection();
        const range = document.createRange();
        range.setStart(p, 0); range.collapse(true);
        sel.removeAllRanges(); sel.addRange(range);
        updateOutput();
    });

    actions.appendChild(deleteBtn);
    wrapper.appendChild(actions);
    wrapper._hasActions = true;
}

// Attach actions to any existing html-wrapper elements on load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.html-wrapper').forEach(w => attachHtmlActions(w));
});

// Handle Backspace/Delete next to media/html-wrapper to remove block cleanly
document.getElementById('editor').addEventListener('keydown', function(e) {
    const key = e.key;
    if (key !== 'Backspace' && key !== 'Delete') return;
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return;
    const range = sel.getRangeAt(0);
    if (!range.collapsed) return;

    // if caret is at start of a paragraph and previous sibling is a wrapper, remove it
    const editor = document.getElementById('editor');
    let node = range.startContainer;
    let offset = range.startOffset;
    if (node.nodeType === 3) {
        // if in text node
        if (offset === 0 && node.previousSibling) {
            const prev = node.previousSibling;
            if (prev.classList && (prev.classList.contains('image-wrapper') || prev.classList.contains('video-wrapper') || prev.classList.contains('html-wrapper'))) {
                e.preventDefault(); prev.remove(); updateOutput(); return;
            }
        }
    } else {
        // node is element (e.g., editor), check child at offset-1 or offset
        const childBefore = node.childNodes[offset - 1];
        const childAfter = node.childNodes[offset];
        if (key === 'Backspace' && childBefore && childBefore.classList && (childBefore.classList.contains('image-wrapper') || childBefore.classList.contains('video-wrapper') || childBefore.classList.contains('html-wrapper'))) {
            e.preventDefault(); childBefore.remove(); updateOutput(); return;
        }
        if (key === 'Delete' && childAfter && childAfter.classList && (childAfter.classList.contains('image-wrapper') || childAfter.classList.contains('video-wrapper') || childAfter.classList.contains('html-wrapper'))) {
            e.preventDefault(); childAfter.remove(); updateOutput(); return;
        }
    }
});

// --- Table picker: UI and insertion ---
const tableBtn = document.getElementById('tableBtn');
const tablePicker = document.getElementById('tablePicker');
const tableGrid = document.getElementById('tableGrid');
const tableLabel = document.getElementById('tableLabel');
const lineSpacingSelect = document.getElementById('lineSpacingSelect');
const fontSizeSelect = document.getElementById('fontSizeSelect');
const fontColorInput = document.getElementById('fontColorInput');
const fontColorBtn = document.getElementById('fontColorBtn');
const colorPalette = document.getElementById('colorPalette');
const colorSwatches = document.getElementById('colorSwatches');
const recentSwatches = document.getElementById('recentSwatches');
const clearColorBtn = document.getElementById('clearColorBtn');
const moreColorsBtn = document.getElementById('moreColorsBtn');
const tableSettings = document.getElementById('tableSettings');
const tableBorderColor = document.getElementById('tableBorderColor');
const tableBgColor = document.getElementById('tableBgColor');
const enableColResize = document.getElementById('enableColResize');
const closeTableSettings = document.getElementById('closeTableSettings');
const addRowBtn = document.getElementById('addRowBtn');
const addColBtn = document.getElementById('addColBtn');
let currentTable = null;

function buildTableGrid(maxRows = 8, maxCols = 8) {
    tableGrid.innerHTML = '';
    for (let r = 0; r < maxRows; r++) {
        for (let c = 0; c < maxCols; c++) {
            const cell = document.createElement('div');
            cell.className = 'cell';
            cell.dataset.r = r + 1;
            cell.dataset.c = c + 1;
            cell.addEventListener('mouseover', () => highlightGrid(r + 1, c + 1));
            cell.addEventListener('click', () => {
                insertTable(r + 1, c + 1);
                hideTablePicker();
            });
            tableGrid.appendChild(cell);
        }
    }
}

function highlightGrid(rows, cols) {
    const cells = tableGrid.querySelectorAll('.cell');
    cells.forEach(cell => {
        const r = parseInt(cell.dataset.r, 10);
        const c = parseInt(cell.dataset.c, 10);
        if (r <= rows && c <= cols) cell.classList.add('active'); else cell.classList.remove('active');
    });
    tableLabel.textContent = rows + ' x ' + cols;
}

function showTablePicker() {
    buildTableGrid();
    const rect = tableBtn.getBoundingClientRect();
    tablePicker.style.top = (rect.bottom + 8) + 'px';
    tablePicker.style.left = rect.left + 'px';
    tablePicker.style.display = 'block';
    tablePicker.setAttribute('aria-hidden', 'false');
}

function hideTablePicker() {
    tablePicker.style.display = 'none';
    tablePicker.setAttribute('aria-hidden', 'true');
}

tableBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    if (tablePicker.style.display === 'block') hideTablePicker(); else showTablePicker();
});

// Font size handlers
function getSelectedHtml(range) {
    const container = document.createElement('div');
    container.appendChild(range.cloneContents());
    return container.innerHTML;
}

function setFontSizeForSelection(sizePt) {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return;
    const range = sel.getRangeAt(0);
    if (range.collapsed) {
        // insert empty span with zero-width space and place caret inside
        const span = document.createElement('span');
        span.style.fontSize = sizePt + 'pt';
        span.appendChild(document.createTextNode('\u200B'));
        range.insertNode(span);
        // move caret inside span after the zero-width
        const newRange = document.createRange();
        newRange.setStart(span.firstChild, 1);
        newRange.collapse(true);
        sel.removeAllRanges();
        sel.addRange(newRange);
    } else {
        const html = getSelectedHtml(range);
        const wrapped = '<span style="font-size:' + sizePt + 'pt">' + html + '</span>';
        // replace selection with wrapped HTML
        document.execCommand('insertHTML', false, wrapped);
    }
    updateOutput();
}

// initialize font size select to 14 by default
if (fontSizeSelect) {
    fontSizeSelect.value = fontSizeSelect.value || '14';
    fontSizeSelect.addEventListener('change', function() {
        const v = parseInt(this.value, 10);
        if (v) setFontSizeForSelection(v);
    });

    function setFontColorForSelection(color) {
        const sel = window.getSelection();
        // If there's a saved selection, but the current selection is missing or outside the editor, restore it
        const editor = document.getElementById('editor');
        function selectionIsInsideEditor(s) {
            if (!s || s.rangeCount === 0) return false;
            try {
                let node = s.getRangeAt(0).commonAncestorContainer;
                if (node.nodeType === 3) node = node.parentNode;
                return editor.contains(node);
            } catch (e) { return false; }
        }
        if (!selectionIsInsideEditor(sel) && _savedSelectionRange) {
            try { sel.removeAllRanges(); sel.addRange(_savedSelectionRange); } catch (e) {}
        }
        if (!selectionIsInsideEditor(sel)) return;
        const range = sel.getRangeAt(0);
        if (range.collapsed) {
            const span = document.createElement('span');
            span.style.color = color;
            span.appendChild(document.createTextNode('\u200B'));
            range.insertNode(span);
            const newRange = document.createRange();
            newRange.setStart(span.firstChild, 1);
            newRange.collapse(true);
            sel.removeAllRanges();
            sel.addRange(newRange);
        } else {
            // if selection spans block elements (multiple paragraphs), apply color to each block
            const blocks = getBlocksInRange(range);
            if (blocks && blocks.length > 0) {
                blocks.forEach(b => { try { b.style.color = color; } catch(e){} });
            } else {
                const html = getSelectedHtml(range);
                const wrapped = '<span style="color:' + color + '">' + html + '</span>';
                document.execCommand('insertHTML', false, wrapped);
            }
        }
        updateOutput();
        _savedSelectionRange = null;
    }

    fontColorInput && fontColorInput.addEventListener('change', function() {
        setFontColorForSelection(this.value);
    });

    // Color palette logic
    let _savedSelectionRange = null;
    const defaultColors = [
        '#000000','#222222','#444444','#666666','#888888','#aaaaaa','#cccccc','#eeeeee','#ffffff','#f8f8f8',
        '#ee1c25','#d83a00','#ff9900','#ffc000','#fff100','#c6df00','#8cc63f','#4caf50','#0aa1dd','#0073e6',
        '#0057b8','#2f2fa2','#7f00ff','#ff00ff','#ff99cc','#ffcccc','#e6b8af','#d4a373','#a96f3a','#8b572a'
    ];

    function renderSwatches() {
        colorSwatches.innerHTML = '';
        defaultColors.forEach(c => {
            const s = document.createElement('div'); s.className = 'swatch'; s.style.background = c; s.dataset.color = c;
            s.title = c;
            s.addEventListener('click', () => { setFontColorForSelection(c); saveRecentColor(c); hideColorPalette(); });
            colorSwatches.appendChild(s);
        });
        renderRecent();
    }

    function getRecentColors() {
        try { return JSON.parse(localStorage.getItem('editorRecentColors') || '[]'); } catch (e) { return []; }
    }

    function saveRecentColor(c) {
        const list = getRecentColors();
        const idx = list.indexOf(c);
        if (idx !== -1) list.splice(idx,1);
        list.unshift(c);
        while (list.length > 10) list.pop();
        localStorage.setItem('editorRecentColors', JSON.stringify(list));
        renderRecent();
    }

    function renderRecent() {
        recentSwatches.innerHTML = '';
        const list = getRecentColors();
        if (!list.length) { recentSwatches.style.display = 'none'; document.getElementById('recentColorsLabel').style.display = 'none'; return; }
        recentSwatches.style.display = 'grid'; document.getElementById('recentColorsLabel').style.display = 'block';
        list.forEach(c => {
            const s = document.createElement('div'); s.className = 'swatch'; s.style.background = c; s.dataset.color = c;
            s.title = c;
            s.addEventListener('click', () => { setFontColorForSelection(c); saveRecentColor(c); hideColorPalette(); });
            recentSwatches.appendChild(s);
        });
    }

    function showColorPalette(btn) {
        // save selection so we can restore after user clicks palette
        const sel = window.getSelection();
        _savedSelectionRange = (sel && sel.rangeCount>0) ? sel.getRangeAt(0).cloneRange() : null;
        renderSwatches();
        const rect = btn.getBoundingClientRect();
        colorPalette.style.top = (rect.bottom + 8) + 'px';
        colorPalette.style.left = rect.left + 'px';
        colorPalette.style.display = 'block';
        colorPalette.setAttribute('aria-hidden','false');
    }


    function hideColorPalette() {
        colorPalette.style.display = 'none';
        colorPalette.setAttribute('aria-hidden','true');
        _savedSelectionRange = null;
    }

    fontColorBtn && fontColorBtn.addEventListener('click', (e) => { e.stopPropagation(); showColorPalette(fontColorBtn); });
    document.addEventListener('click', (e) => { if (!colorPalette.contains(e.target) && e.target !== fontColorBtn) hideColorPalette(); });

    clearColorBtn && clearColorBtn.addEventListener('click', () => {
        // apply 'inherit' so color is reset to parent's color
        setFontColorForSelection('inherit');
        hideColorPalette();
    });

    moreColorsBtn && moreColorsBtn.addEventListener('click', () => { hideColorPalette(); fontColorInput.click(); });

    fontColorInput && fontColorInput.addEventListener('change', function() {
        const c = this.value; saveRecentColor(c); setFontColorForSelection(c);
    });

    // Helpers to find block elements in a range
    function isBlock(node) {
        if (!node || node.nodeType !== 1) return false;
        const tag = node.tagName.toLowerCase();
        return ['p','div','li','td','blockquote','h1','h2','h3','h4','h5','h6'].includes(tag);
    }

    function getClosestBlock(node) {
        while (node && node !== document.body) {
            if (isBlock(node)) return node;
            node = node.parentNode;
        }
        return null;
    }

    function getBlocksInRange(range) {
        const blocks = [];
        const start = range.startContainer;
        const end = range.endContainer;
        // use TreeWalker to collect block elements within editor
        const editor = document.getElementById('editor');
        const walker = document.createTreeWalker(editor, NodeFilter.SHOW_ELEMENT, {
            acceptNode: function(node) {
                if (isBlock(node)) {
                    // check if node intersects range
                    const nodeRange = document.createRange();
                    nodeRange.selectNodeContents(node);
                    if (range.compareBoundaryPoints(Range.START_TO_END, nodeRange) < 0 && range.compareBoundaryPoints(Range.END_TO_START, nodeRange) > 0) return NodeFilter.FILTER_ACCEPT;
                }
                return NodeFilter.FILTER_SKIP;
            }
        }, false);
        let n = walker.nextNode();
        while (n) { blocks.push(n); n = walker.nextNode(); }
        return blocks;
    }

    function applyLineSpacingToSelection(value) {
        const sel = window.getSelection();
        if (!sel || sel.rangeCount === 0) return;
        const range = sel.getRangeAt(0);
        if (range.collapsed) {
            const block = getClosestBlock(range.startContainer) || document.getElementById('editor');
            block.style.lineHeight = value;
        } else {
            const blocks = getBlocksInRange(range);
            if (blocks.length === 0) {
                // wrap selection in a span with line-height
                document.execCommand('insertHTML', false, '<span style="line-height:' + value + '">' + getSelectedHtml(range) + '</span>');
            } else {
                blocks.forEach(b => b.style.lineHeight = value);
            }
        }
        updateOutput();
    }

    lineSpacingSelect && lineSpacingSelect.addEventListener('change', function() {
        const v = this.value;
        if (v) applyLineSpacingToSelection(v);
    });


}

// font size +/- buttons removed; use select only

// Click outside closes picker
document.addEventListener('click', (e) => {
    if (!tablePicker.contains(e.target) && e.target !== tableBtn) hideTablePicker();

    // If settings is open, close it when clicking outside both the settings box and the current table
    if (tableSettings.style.display === 'block') {
        const clickedInsideSettings = tableSettings.contains(e.target);
        const clickedInsideCurrentTable = currentTable && currentTable.contains(e.target);
        if (!clickedInsideSettings && !clickedInsideCurrentTable) {
            hideTableSettings();
            currentTable = null;
        }
    }
});

// Open settings when right-clicking (contextmenu) a table inside the editor
const editorEl = document.getElementById('editor');
editorEl.addEventListener('contextmenu', (e) => {
    let node = e.target;
    while (node && node !== editorEl) {
        if (node.tagName && node.tagName.toLowerCase() === 'table') {
            e.preventDefault();
            showTableSettings(node);
            // position near mouse for better UX
            tableSettings.style.top = (e.pageY + 8) + 'px';
            tableSettings.style.left = (e.pageX + 8) + 'px';
            return;
        }
        node = node.parentNode;
    }
});

// Insert table at caret or end of editor
function insertTable(rows, cols) {
    const table = document.createElement('table');
    table.style.borderCollapse = 'collapse';
    table.style.width = '100%';
    table.style.margin = '8px 0';
    table.setAttribute('border', '1');
    table.contentEditable = 'true'; // allow editing inside table
    for (let r = 0; r < rows; r++) {
        const tr = document.createElement('tr');
        for (let c = 0; c < cols; c++) {
            const td = document.createElement('td');
            td.style.padding = '8px';
            td.style.border = '1px solid #a5a5a5';
            td.contentEditable = 'true';
            td.innerHTML = '&nbsp;';
            tr.appendChild(td);
        }
        table.appendChild(tr);
    }

    // Wrap the table in a non-editable div
    const wrapper = document.createElement('div');
    wrapper.className = 'table-wrapper';
    wrapper.contentEditable = 'false';
    wrapper.style.display = 'block';
    wrapper.style.margin = '8px 0';
    wrapper.appendChild(table);

    // Add click handler for selection
    wrapper.addEventListener('click', function(e) {
        e.stopPropagation();
        document.querySelectorAll('.image-wrapper.selected, .table-wrapper.selected').forEach(w => {
            w.classList.remove('selected');
            w.style.outline = '';
        });
        wrapper.classList.add('selected');
        wrapper.style.outline = '2px solid #007bff';
    });

    // Add context menu for settings
    wrapper.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        showTableSettings(table);
    });

    const editor = document.getElementById('editor');
    const sel = window.getSelection();
    let inserted = false;
    if (sel && sel.rangeCount > 0) {
        const range = sel.getRangeAt(0);
        // se o range está dentro do editor, insere no caret
        let container = range.commonAncestorContainer;
        if (container.nodeType === 3) container = container.parentNode;
        if (editor.contains(container)) {
            range.collapse(true);
            range.insertNode(wrapper);
            inserted = true;
        }
    }
    // se não inseriu no caret (picker tinha foco), adiciona no final do editor
    if (!inserted) {
        editor.appendChild(wrapper);
    }

    // Insert a paragraph after the wrapper to allow cursor below
    const p = document.createElement('p');
    p.innerHTML = '<br>';
    if (wrapper.nextSibling) {
        wrapper.parentNode.insertBefore(p, wrapper.nextSibling);
    } else {
        wrapper.parentNode.appendChild(p);
    }

    // place caret in first cell
    const firstCell = table.querySelector('td');
    if (firstCell) {
        const newRange = document.createRange();
        newRange.selectNodeContents(firstCell);
        newRange.collapse(true);
        sel.removeAllRanges();
        sel.addRange(newRange);
        firstCell.focus();
    }

    addColResizers(table);
    enableColResize.checked = true;
    updateOutput();
}

// Find table element nearest caret or last inserted
function findClosestTable() {
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0) {
        let node = sel.getRangeAt(0).commonAncestorContainer;
        if (node.nodeType === 3) node = node.parentNode;
        while (node && node !== document.body) {
            if (node.tagName && node.tagName.toLowerCase() === 'table') return node;
            node = node.parentNode;
        }
    }
    // fallback: last table in editor
    const tables = document.getElementById('editor').querySelectorAll('table');
    return tables.length ? tables[tables.length - 1] : null;
}


function showTableSettings(table) {
    if (!table) {
        table = findClosestTable();
        if (!table) return alert('Nenhuma tabela encontrada. Insira uma tabela primeiro.');
    }
    currentTable = table;
    // position near table
    const rect = table.getBoundingClientRect();
    tableSettings.style.top = (rect.top + window.scrollY + 8) + 'px';
    tableSettings.style.left = (rect.left + window.scrollX + 8) + 'px';
    tableSettings.style.display = 'block';
    tableSettings.setAttribute('aria-hidden', 'false');
    // set current values
    tableBorderColor.value = rgbToHex(window.getComputedStyle(table).borderColor || '#a5a5a5');
    tableBgColor.value = rgbToHex(window.getComputedStyle(table).backgroundColor || '#ffffff');
    enableColResize.checked = !!table.dataset.colresizers;
}

function hideTableSettings() {
    tableSettings.style.display = 'none';
    tableSettings.setAttribute('aria-hidden', 'true');
}

// toolbar settings button was removed; opening settings is handled via right-click on table

closeTableSettings.addEventListener('click', () => hideTableSettings());

tableBorderColor.addEventListener('input', () => {
    const table = currentTable || findClosestTable(); if (!table) return;
    table.style.borderColor = tableBorderColor.value;
    table.querySelectorAll('td,th').forEach(cell => cell.style.borderColor = tableBorderColor.value);
    updateOutput();
});

tableBgColor.addEventListener('input', () => {
    const table = currentTable || findClosestTable(); if (!table) return;
    table.style.backgroundColor = tableBgColor.value;
    table.querySelectorAll('td,th').forEach(cell => cell.style.backgroundColor = tableBgColor.value);
    updateOutput();
});

enableColResize.addEventListener('change', () => {
    const table = currentTable || findClosestTable(); if (!table) return;
    if (enableColResize.checked) addColResizers(table); else removeColResizers(table);
    updateOutput();
});

addRowBtn.addEventListener('click', () => {
    const table = currentTable || findClosestTable(); if (!table) return;
    addRowToTable(table);
    updateOutput();
});

addColBtn.addEventListener('click', () => {
    const table = currentTable || findClosestTable(); if (!table) return;
    addColToTable(table);
    updateOutput();
});

// Helpers: convert rgb to hex
function rgbToHex(rgb) {
    if (!rgb) return '#ffffff';
    const m = rgb.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/i);
    if (!m) return '#ffffff';
    return '#' + [1,2,3].map(i => parseInt(m[i],10).toString(16).padStart(2,'0')).join('');
}

// Column resizers implementation
function addColResizers(table) {
    removeColResizers(table);
    table.style.position = 'relative';
    table.style.tableLayout = 'fixed';
    const firstRow = table.querySelector('tr');
    if (!firstRow) return;
    const cells = Array.from(firstRow.children);

    // create handles for each column
    cells.forEach((cell, index) => {
        const handle = document.createElement('div');
        handle.className = 'col-handle';
        handle.dataset.colIndex = index;
        table.appendChild(handle);

        let startX = 0;
        let startWidth = 0;

        function onMouseDown(e) {
            e.preventDefault();
            // capture cell current width at drag start
            startX = e.clientX;
            startWidth = cell.getBoundingClientRect().width;
            document.addEventListener('mousemove', onMouseMove);
            document.addEventListener('mouseup', onMouseUp);
        }
        function onMouseMove(e) {
            const dx = e.clientX - startX;
            const newWidth = Math.max(40, startWidth + dx);
            // apply width to all cells in this column
            table.querySelectorAll('tr').forEach(tr => {
                const c = tr.children[index];
                if (c) c.style.width = newWidth + 'px';
            });
            // update handle positions while dragging
            updateColResizersPositions(table);
        }
        function onMouseUp() {
            document.removeEventListener('mousemove', onMouseMove);
            document.removeEventListener('mouseup', onMouseUp);
            updateOutput();
        }

        handle.addEventListener('mousedown', onMouseDown);
    });

    // updater to reposition handles on resize/scroll or DOM changes
    function updater() { updateColResizersPositions(table); }
    table._colResizerUpdater = updater;
    window.addEventListener('resize', updater);
    window.addEventListener('scroll', updater, true);

    // observe mutations to table to reposition handles when structure changes
    const mo = new MutationObserver(() => updateColResizersPositions(table));
    mo.observe(table, { childList: true, subtree: true, attributes: true });
    table._colResizerObserver = mo;

    // initial position set
    updateColResizersPositions(table);
    table.dataset.colresizers = '1';
}

function removeColResizers(table) {
    if (!table) return;
    Array.from(table.querySelectorAll('.col-handle')).forEach(h => h.remove());
    delete table.dataset.colresizers;
    // remove explicit widths
    table.querySelectorAll('td,th').forEach(cell => cell.style.width = '');
    // remove listeners/observers
    if (table._colResizerUpdater) {
        window.removeEventListener('resize', table._colResizerUpdater);
        window.removeEventListener('scroll', table._colResizerUpdater, true);
        table._colResizerUpdater = null;
    }
    if (table._colResizerObserver) {
        try { table._colResizerObserver.disconnect(); } catch (e) {}
        table._colResizerObserver = null;
    }
}

function updateColResizersPositions(table) {
    if (!table) return;
    const rect = table.getBoundingClientRect();
    const firstRow = table.querySelector('tr');
    if (!firstRow) return;
    const cells = Array.from(firstRow.children);
    const handles = Array.from(table.querySelectorAll('.col-handle'));
    handles.forEach((handle, index) => {
        const cell = cells[index];
        if (!cell) { handle.remove(); return; }
        const cellRect = cell.getBoundingClientRect();
        // position handle at the right edge of the cell (relative to table)
        handle.style.left = (cellRect.right - rect.left - 4) + 'px';
        handle.style.top = '0px';
        handle.style.bottom = '0px';
    });
}

// Add a row at the end of the table
function addRowToTable(table) {
    if (!table) return;
    const cols = table.querySelectorAll('tr')[0]?.children.length || 0;
    const tr = document.createElement('tr');
    for (let i = 0; i < cols; i++) {
        const td = document.createElement('td');
        td.style.padding = '8px';
        td.style.border = '1px solid ' + (tableBorderColor.value || '#e6eef8');
        td.contentEditable = 'true';
        td.innerHTML = '&nbsp;';
        tr.appendChild(td);
    }
    table.appendChild(tr);
}

// Add a column at the end of the table
function addColToTable(table) {
    if (!table) return;
    const rows = table.querySelectorAll('tr');
    rows.forEach(row => {
        const td = document.createElement('td');
        td.style.padding = '8px';
        td.style.border = '1px solid ' + (tableBorderColor.value || '#e6eef8');
        td.contentEditable = 'true';
        td.innerHTML = '&nbsp;';
        row.appendChild(td);
    });
    // if resizers enabled, re-add to reposition handles
    if (table.dataset.colresizers) {
        addColResizers(table);
    }
}

// Insere uma linha horizontal no editor com fallback
function insertHorizontalRule() {
    // Tenta usar execCommand primeiro
    try {
        const ok = document.execCommand('insertHorizontalRule', false, null);
        if (ok) { updateOutput(); return; }
    } catch (e) {}

    // Fallback: inserir <hr> no range atual
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) {
        const editor = document.getElementById('editor');
        editor.appendChild(document.createElement('hr'));
        editor.appendChild(document.createElement('p'));
        updateOutput();
        return;
    }
    const range = sel.getRangeAt(0);
    range.collapse(true);

    const hr = document.createElement('hr');
    const br = document.createElement('p');
    br.innerHTML = '<br>';

    range.insertNode(br);
    range.insertNode(hr);

    // Move o caret para depois do elemento inserido
    range.setStartAfter(br);
    range.setEndAfter(br);
    sel.removeAllRanges();
    sel.addRange(range);

    updateOutput();
}

// Insert a 'Dica Rápida' callout box similar to the attachment
function insertCallout() {
    const editor = document.getElementById('editor');
    const wrapper = document.createElement('div');
    wrapper.className = 'callout';
    // keep wrapper non-editable so only the content region is edited
    wrapper.contentEditable = 'false';

    const icon = document.createElement('div');
    icon.className = 'callout-icon';
    icon.contentEditable = 'false';
    icon.innerHTML = '<i class="fas fa-lightbulb"></i>';

    const content = document.createElement('div');
    content.className = 'callout-content';
    content.contentEditable = 'true';

    const title = document.createElement('h4');
    title.textContent = 'Dica Rápida';
    const para = document.createElement('p');
    para.textContent = 'Escreva sua dica aqui...';

    content.appendChild(title);
    content.appendChild(para);
    wrapper.appendChild(icon);
    wrapper.appendChild(content);

    // insert at caret or at the end
    const sel = window.getSelection();
    let inserted = false;
    if (sel && sel.rangeCount > 0) {
        const range = sel.getRangeAt(0);
        let container = range.commonAncestorContainer;
        if (container.nodeType === 3) container = container.parentNode;
        if (editor.contains(container)) {
            range.collapse(true);
            range.insertNode(wrapper);
            inserted = true;
        }
    }
    if (!inserted) editor.appendChild(wrapper);

    // place caret inside paragraph to let user edit immediately
    const newRange = document.createRange();
    newRange.selectNodeContents(para);
    newRange.collapse(true);
    sel.removeAllRanges();
    sel.addRange(newRange);

    updateOutput();
}

// Prompt-based link insertion that ensures links open in a new tab
function promptForLink() {
    const url = prompt('Cole o URL do link (será aberto em nova aba):');
    if (!url) return;
    let href = url.trim();
    if (!/^[a-zA-Z][a-zA-Z0-9+.-]*:/.test(href)) href = 'https://' + href;

    const editor = document.getElementById('editor');
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) {
        const a = document.createElement('a');
        a.href = href;
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        a.textContent = href;
        editor.appendChild(a);
    } else {
        const range = sel.getRangeAt(0);
        if (range.collapsed) {
            const a = document.createElement('a');
            a.href = href;
            a.target = '_blank';
            a.rel = 'noopener noreferrer';
            a.textContent = href;
            range.insertNode(a);
            // move caret after inserted link
            range.setStartAfter(a);
            range.collapse(true);
            sel.removeAllRanges();
            sel.addRange(range);
        } else {
            // Use execCommand to create link, then ensure target/rel are set
            document.execCommand('createLink', false, href);
            const container = range.commonAncestorContainer.nodeType === 3 ? range.commonAncestorContainer.parentNode : range.commonAncestorContainer;
            const anchors = container.querySelectorAll('a');
            anchors.forEach(a => {
                // set target/rel for links that match or haven't been set
                try {
                    if (!a.getAttribute('target') || a.getAttribute('href') === href || a.href === href) {
                        a.setAttribute('target', '_blank');
                        a.setAttribute('rel', 'noopener noreferrer');
                    }
                } catch (e) {}
            });
        }
    }
    updateOutput();
}

// Exporta apenas o conteúdo do editor sem as ferramentas/controles
function exportContent() {
    const editor = document.getElementById('editor');
    if (!editor) return alert('Editor não encontrado.');

    // clone para evitar modificar o editor real
    const clone = editor.cloneNode(true);
    // remover controles e handlers de UI que não fazem parte do conteúdo
    const selectorsToRemove = [
        '.image-controls', '.html-actions', '.col-handle', '.table-settings', '.table-picker', '.color-palette', '.html-modal',
        '.img-handle', '.image-toolbar', '.image-toolbar button'
    ];
    selectorsToRemove.forEach(sel => {
        clone.querySelectorAll(sel).forEach(n => n.remove());
    });
    // remover atributos contenteditable para que o HTML exportado seja apenas conteúdo
    clone.querySelectorAll('[contenteditable]').forEach(n => n.removeAttribute('contenteditable'));

    // limpar classes/atributos criados para edição (selected, tabIndex, inline sizes)
    clone.querySelectorAll('.image-wrapper').forEach(w => {
        w.classList.remove('selected');
        w.removeAttribute('tabindex');
        // se houver largura inline (ajustada durante edição), manter a imagem, mas não o wrapper width
        w.style.width = '';
        w.style.margin = '';
        w.style.display = '';
    });
    // remover possíveis handles residuais por segurança
    clone.querySelectorAll('.img-handle, .image-toolbar, .image-controls').forEach(n => n.remove());

    const contentHtml = clone.innerHTML;

    const fullHtml = '<!doctype html><html><head><meta charset="utf-8"><title>Conteúdo exportado</title>' +
        '<style>body{font-family:Inter,system-ui,Arial,sans-serif;padding:24px;background:#fff;color:#111}img{max-width:100%;height:auto}table{border-collapse:collapse;width:100%}table td,table th{border:1px solid #e6eef8;padding:8px}</style>' +
        '</head><body>' + contentHtml + '</body></html>';

    const blob = new Blob([fullHtml], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'conteudo.html';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1500);
}
