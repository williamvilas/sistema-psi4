{{-- Campos de endereço do consultório (Configurações › Psicólogo e onboarding). Com `required`, rua, número e cidade exibem o asterisco de obrigatório (`.address-required-mark`). --}}
<div class="row g-3">
    <div class="col-md-6 col-xl-5">
        <label for="cep" class="form-label">CEP</label>
        <div class="input-group">
            <input id="cep" name="cep" type="tel" class="form-control zipcode" placeholder="00000-000" value="{{ maskValue($psychologistSettings?->cep, '#####-###') }}" inputmode="numeric" autocomplete="postal-code">
            <button id="btn-search-zipcode" type="button" class="btn btn-outline-secondary" title="Buscar endereço pelo CEP" aria-label="Buscar endereço pelo CEP">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>
    <div class="col-md-6 col-xl-7">
        <label for="cidade" class="form-label">Cidade <span class="text-danger address-required-mark @unless ($required ?? false) d-none @endunless">*</span></label>
        <input id="cidade" name="cidade" type="text" class="form-control" placeholder="Cidade" value="{{ $psychologistSettings?->cidade }}">
    </div>
    <div class="col-md-8">
        <label for="rua" class="form-label">Rua <span class="text-danger address-required-mark @unless ($required ?? false) d-none @endunless">*</span></label>
        <input id="rua" name="rua" type="text" class="form-control" placeholder="Nome da rua" value="{{ $psychologistSettings?->rua }}" autocomplete="street-address">
    </div>
    <div class="col-md-4">
        <label for="numero" class="form-label">Número <span class="text-danger address-required-mark @unless ($required ?? false) d-none @endunless">*</span></label>
        <input id="numero" name="numero" type="number" class="form-control" placeholder="Número" value="{{ $psychologistSettings?->numero }}">
    </div>
    <div class="col-md-6">
        <label for="bairro" class="form-label">Bairro</label>
        <input id="bairro" name="bairro" type="text" class="form-control" placeholder="Bairro" value="{{ $psychologistSettings?->bairro }}">
    </div>
    <div class="col-md-6">
        <label for="complemento" class="form-label">Complemento</label>
        <input id="complemento" name="complemento" type="text" class="form-control" placeholder="Apartamento, sala ou complemento" value="{{ $psychologistSettings?->complemento }}">
    </div>
</div>
