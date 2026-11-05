{{-- Preenchido pelo two-factor.js; os códigos só existem no DOM enquanto a tela estiver aberta. --}}
<div class="tf-recovery" id="tf-recovery" data-account="{{ Auth::user()->email }}">
    <div class="tf-recovery-alert">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <span>Guarde estes códigos em um local seguro. Cada um funciona <strong>uma única vez</strong> e eles <strong>não serão exibidos novamente</strong>.</span>
    </div>

    <ol class="tf-recovery-list" id="tf-recovery-list" aria-label="Códigos de recuperação"></ol>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="dash-btn-soft" id="tf-copy-codes">
            <i class="fa-regular fa-copy me-1" aria-hidden="true"></i> Copiar códigos
        </button>
        <button type="button" class="dash-btn-soft" id="tf-download-codes">
            <i class="fa-solid fa-download me-1" aria-hidden="true"></i> Baixar .txt
        </button>
    </div>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="tf-saved-codes">
        <label class="form-check-label" for="tf-saved-codes">Guardei meus códigos de recuperação em local seguro</label>
    </div>

    <button type="button" class="btn btn-primary tf-btn" id="tf-finish" disabled>
        <i class="fa-solid fa-check me-1" aria-hidden="true"></i> Concluir
    </button>
</div>
