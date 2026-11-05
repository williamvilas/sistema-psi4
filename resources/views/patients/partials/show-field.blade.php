{{--
    Linha de informação da tela de detalhes do paciente.
    Parâmetros: icon, label, value (texto já formatado ou null), href (opcional), copy (opcional: valor a copiar), copyTitle (opcional).
--}}
<div class="pat-show-field">
    <span class="pat-show-field-icon"><i class="{{ $icon }}"></i></span>
    <div class="pat-show-field-body">
        <span class="pat-show-label">{{ $label }}</span>
        @if(filled($value))
            <span class="pat-show-value" data-field="{{ $field ?? '' }}">
                @if(!empty($href))
                    <a href="{{ $href }}" @if(!empty($external)) target="_blank" rel="noopener" @endif>{{ $value }}</a>
                @else
                    {{ $value }}
                @endif
            </span>
        @else
            <span class="pat-show-value pat-show-empty" data-field="{{ $field ?? '' }}">Não informado</span>
        @endif
    </div>
    @if(filled($value) && !empty($copy))
        <button type="button" class="dash-round-btn dash-round-muted pat-show-copy" onclick="copyToClipboard(@js($copy), @js($copyTitle ?? 'Copiado'))" title="Copiar {{ mb_strtolower($label) }}" aria-label="Copiar {{ mb_strtolower($label) }}">
            <i class="far fa-copy"></i>
        </button>
    @endif
</div>
