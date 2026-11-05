@php
    $index = $index ?? 0;
@endphp
<div class="cfg-item servico-item">
    <input type="hidden" name="services[{{ $index }}][id]" value="{{ $service?->id }}">
    <div class="row g-3 align-items-end">
        <div class="col-md-7">
            <label class="form-label" for="servicoNome{{ $index }}">Nome do serviço <span class="text-danger">*</span></label>
            <input id="servicoNome{{ $index }}" name="services[{{ $index }}][name]" type="text" class="form-control" maxlength="100" placeholder="Ex.: Terapia individual" value="{{ $service?->name }}">
        </div>
        <div class="col-8 col-md-3">
            <label class="form-label" for="servicoValor{{ $index }}">Valor (R$) <span class="text-danger">*</span></label>
            <input id="servicoValor{{ $index }}" name="services[{{ $index }}][price]" type="text" class="form-control money-service" inputmode="numeric" placeholder="0,00" value="{{ $service ? number_format((float) $service->price, 2, ',', '.') : '' }}">
        </div>
        <div class="col-4 col-md-2 d-flex justify-content-end" style="min-height: 55px;">
            <button type="button" class="dash-round-btn cfg-item-remove align-self-center" onclick="removeServico(this)" title="Remover serviço" aria-label="Remover serviço">
                <i class="fa-regular fa-trash-can"></i>
            </button>
        </div>
    </div>
</div>
