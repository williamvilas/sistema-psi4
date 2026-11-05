<div class="toolbar" role="toolbar" aria-label="Editor toolbar">
    <div class="group">
        <button type="button" title="Negrito" aria-label="Negrito" onclick="cmd('bold')"><i class="fas fa-bold"></i></button>
        <button type="button" title="Itálico" aria-label="Itálico" onclick="cmd('italic')"><i class="fas fa-italic"></i></button>
        <button type="button" title="Sublinhado" aria-label="Sublinhado" onclick="cmd('underline')"><i class="fas fa-underline"></i></button>
    </div>

    <div class="group" style="align-items:center;gap:8px">
        <select onchange="cmdArg('fontName', this.value)" title="Fonte" aria-label="Fonte">
            <option value="">Fonte</option>
            <option value="Arial">Arial</option>
            <option value="Georgia">Georgia</option>
            <option value="Times New Roman">Times</option>
            <option value="Verdana">Verdana</option>
        </select>

        <!-- Word-like font size control -->
        <div style="display:flex;align-items:center;gap:6px">
            <select id="fontSizeSelect" title="Tamanho da fonte" aria-label="Tamanho da fonte" style="min-width:72px">
                <option value="">Tamanho (pt)</option>
                <option value="8">8 pt</option>
                <option value="9">9 pt</option>
                <option value="10">10 pt</option>
                <option value="11">11 pt</option>
                <option value="12">12 pt</option>
                <option value="14" selected>14 pt</option>
                <option value="16">16 pt</option>
                <option value="18">18 pt</option>
                <option value="20">20 pt</option>
                <option value="22">22 pt</option>
                <option value="24">24 pt</option>
                <option value="26">26 pt</option>
                <option value="28">28 pt</option>
                <option value="36">36 pt</option>
                <option value="48">48 pt</option>
                <option value="72">72 pt</option>
            </select>
        </div>
        <button type="button" id="fontColorBtn" title="Cor da fonte" aria-label="Cor da fonte" style="border:1px solid transparent;padding:6px;border-radius:6px;margin-left:8px"><i class="fas fa-palette"></i></button>
        <input id="fontColorInput" type="color" title="Cor da fonte" aria-label="Cor da fonte" value="#111111" style="display:none">
    </div>

    <div class="group" style="align-items:center;gap:8px">
        <select id="lineSpacingSelect" title="Espaçamento entre linhas" aria-label="Espaçamento entre linhas" style="min-width:80px">
            <option value="1">Simples</option>
            <option value="1.15">1,15</option>
            <option value="1.5">1,5</option>
            <option value="2">Duplo</option>
        </select>
    </div>

    <div class="group">
        <button type="button" title="Alinhar à esquerda" aria-label="Alinhar à esquerda" onclick="cmd('justifyLeft')"><i class="fas fa-align-left"></i></button>
        <button type="button" title="Centralizar" aria-label="Centralizar" onclick="cmd('justifyCenter')"><i class="fas fa-align-center"></i></button>
        <button type="button" title="Alinhar à direita" aria-label="Alinhar à direita" onclick="cmd('justifyRight')"><i class="fas fa-align-right"></i></button>
    </div>

    <div class="group">
        <button type="button" title="Diminuir recuo" aria-label="Diminuir recuo" onclick="cmd('outdent')"><i class="fas fa-outdent"></i></button>
        <button type="button" title="Aumentar recuo" aria-label="Aumentar recuo" onclick="cmd('indent')"><i class="fas fa-indent"></i></button>
        <button type="button" title="Dica Rápida" aria-label="Inserir dica rápida" id="calloutBtn" onclick="insertCallout()"><i class="fas fa-lightbulb"></i></button>
        <button type="button" title="Inserir link (abrir em nova aba)" aria-label="Inserir link" id="linkBtn" onclick="promptForLink()"><i class="fas fa-external-link-alt"></i></button>
        <button type="button" title="Lista não ordenada" aria-label="Lista não ordenada" onclick="cmd('insertUnorderedList')"><i class="fas fa-list-ul"></i></button>
        <button type="button" title="Lista ordenada" aria-label="Lista ordenada" onclick="cmd('insertOrderedList')"><i class="fas fa-list-ol"></i></button>
        <button type="button" title="Tabela" aria-label="Inserir tabela" id="tableBtn"><i class="fas fa-table"></i></button>
        <button type="button" id="htmlBtn" title="Inserir bloco HTML" aria-label="Inserir bloco HTML"><i class="fas fa-code"></i></button>
        <input type="file" id="imageInput" accept="image/*" style="display:none">
        <button type="button" id="imageBtn" title="Inserir imagem" aria-label="Inserir imagem"><i class="fas fa-image"></i></button>
        <button type="button" title="Linha horizontal" aria-label="Linha horizontal" onclick="insertHorizontalRule()"><i class="fas fa-minus"></i></button>
    </div>

    <div class="spacer"></div>
</div>

<div id="editor" contenteditable="true">
    @if(!empty($text))
        {!! $text !!}
    @endif
</div>

<!-- Table picker popover -->
<div id="tablePicker" class="table-picker" role="dialog" aria-hidden="true">
    <div class="grid" id="tableGrid"></div>
    <div class="label" id="tableLabel">0 x 0</div>
</div>

<!-- Color palette popover -->
<div id="colorPalette" class="color-palette" role="dialog" aria-hidden="true">
    <div class="swatches" id="colorSwatches"></div>
    <div class="recent" id="recentColorsLabel">CORES PERSONALIZADAS</div>
    <div class="swatches" id="recentSwatches"></div>
    <div class="actions">
        <button id="clearColorBtn" type="button">Limpar</button>
        <button id="moreColorsBtn" type="button">Mais cores...</button>
    </div>
</div>

<!-- HTML block modal -->
<div id="htmlModal" class="html-modal" role="dialog" aria-hidden="true">
    <label style="font-size:14px;color:var(--muted);display:block;margin-bottom:6px">Cole o HTML do bloco abaixo:</label>
    <textarea id="htmlModalTextarea" placeholder="<div>Seu HTML aqui</div>"></textarea>
    <div class="actions">
        <button type="button" id="insertHtmlBtn">Inserir</button>
        <button type="button" id="cancelHtmlBtn">Cancelar</button>
    </div>
</div>

<!-- Table settings popover -->
<div id="tableSettings" class="table-settings" role="dialog" aria-hidden="true">
    <div class="row">
        <label>Cor da borda</label>
        <input type="color" id="tableBorderColor" value="#e6eef8">
    </div>
    <div class="row">
        <label>Cor de fundo</label>
        <input type="color" id="tableBgColor" value="#ffffff">
    </div>
    <div class="row">
        <label>Habilitar redimensionar colunas</label>
        <input type="checkbox" id="enableColResize" checked>
    </div>
    <div class="row" style="justify-content:space-between;gap:8px">
        <button type="button" id="addRowBtn" style="padding:6px 8px;border-radius:6px;border:1px solid var(--toolbar-border);background:#fff">+ Linha</button>
        <button type="button" id="addColBtn" style="padding:6px 8px;border-radius:6px;border:1px solid var(--toolbar-border);background:#fff">+ Coluna</button>
    </div>
    <div style="text-align:right;margin-top:6px">
        <button type="button" id="closeTableSettings" style="padding:6px 8px;border-radius:6px;border:1px solid var(--toolbar-border);background:#fff">Fechar</button>
    </div>
</div>

<div class="output">
    <input type="hidden" id="htmlOutput" />
</div>
