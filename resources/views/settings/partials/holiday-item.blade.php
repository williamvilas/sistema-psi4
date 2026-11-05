@php
    $id = $id ?? '__ID__';
    $work = $work ?? false;
@endphp
<div class="cfg-item feriado-item" id="feriado-{{ $id }}">
    <div class="row g-3 align-items-end">
        <div class="col-sm-4 col-lg-3">
            <label for="feriadoData{{ $id }}" class="form-label">Data <span class="text-danger">*</span></label>
            <input id="feriadoData{{ $id }}" name="holidays[{{ $id }}][date]" type="tel" class="form-control date date_only" placeholder="dd/mm/aaaa" value="{{ $date ?? '' }}">
        </div>
        <div class="col-sm-8 col-lg-5">
            <label for="feriadoNome{{ $id }}" class="form-label">Nome <small class="text-muted fw-normal">(opcional)</small></label>
            <input id="feriadoNome{{ $id }}" name="holidays[{{ $id }}][name]" type="text" class="form-control" maxlength="255" placeholder="Ex.: Natal" value="{{ $name ?? '' }}">
        </div>
        <div class="col-12 col-lg-4 d-flex flex-wrap align-items-center justify-content-between gap-3" style="min-height: 55px;">
            <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" role="switch" id="feriadoSwitch{{ $id }}" name="holidays[{{ $id }}][work_enabled]" value="1" @checked($work) onchange="toggleHorarioFeriado(this)">
                <label class="form-check-label" for="feriadoSwitch{{ $id }}">Atender neste dia</label>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="dash-chip dash-chip-warning cfg-item-new">Não salvo</span>
                <button type="button" class="dash-round-btn cfg-item-remove" onclick="removeFeriado(this)" title="Remover feriado" aria-label="Remover feriado">
                    <i class="fa-regular fa-trash-can"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="cfg-item-hours feriado-horarios {{ $work ? '' : 'd-none' }}">
        <i class="fa-regular fa-clock"></i>
        <span>Horário especial</span>
        <input type="time" class="form-control cfg-time" name="holidays[{{ $id }}][start]" value="{{ $start ?? '08:00' }}" aria-label="Início do horário especial">
        <span>até</span>
        <input type="time" class="form-control cfg-time" name="holidays[{{ $id }}][end]" value="{{ $end ?? '12:00' }}" aria-label="Fim do horário especial">
    </div>
</div>
