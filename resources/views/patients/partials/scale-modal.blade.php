{{-- data-bs-focus="false": sem o focus trap do Bootstrap, o pedido da senha mestre (Swal) recebe digitação por cima do modal. --}}
<div class="modal fade pat-scale-modal" id="scaleApplicationModal" tabindex="-1" aria-labelledby="scaleApplicationModalLabel" aria-hidden="true"
     data-bs-backdrop="static" data-bs-focus="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <form class="modal-content" id="form-store-scale-application" autocomplete="off" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="scaleApplicationModalLabel">
                    <i class="fas fa-clipboard-list me-1"></i> Aplicar escala
                    <span class="pat-scale-lock" title="Respostas e pontuação cifradas no seu navegador"><i class="fas fa-lock"></i> Cifrada</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <fieldset class="mb-3">
                    <legend class="form-label fw-semibold pat-scale-legend">Escala</legend>
                    <div class="pat-scale-picker" role="radiogroup">
                        @foreach($scales as $scale)
                            <input type="radio" class="btn-check" name="scale" id="scale_slug_{{ $scale['slug'] }}" value="{{ $scale['slug'] }}" autocomplete="off" @checked($loop->first)>
                            <label class="pat-scale-choice" for="scale_slug_{{ $scale['slug'] }}">
                                <strong>{{ $scale['name'] }}</strong>
                                <span>{{ $scale['short'] }}</span>
                                <small>{{ count($scale['items']) }} itens</small>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-5">
                        <label for="scale_applied_on" class="form-label fw-semibold">Data da aplicação</label>
                        <input type="tel" class="form-control date_only" name="applied_on" id="scale_applied_on" required>
                    </div>
                </div>

                <div class="mb-3 d-none" id="scale-reference-event">
                    <label for="scale_reference_event" class="form-label fw-semibold">Evento de referência <span class="text-muted fw-normal">(opcional)</span></label>
                    <textarea class="form-control" name="reference_event" id="scale_reference_event" rows="2" maxlength="500"
                              placeholder="Uma descrição breve do pior evento, para retomar nas próximas aplicações"></textarea>
                    <small class="text-muted"><i class="fas fa-lock me-1"></i>Cifrado no seu navegador junto com as respostas.</small>
                </div>

                <div class="pat-scale-sticky">
                    <p class="pat-scale-instruction mb-2" id="scale-instruction"></p>
                    <div class="pat-scale-progress" role="progressbar" aria-label="Itens respondidos" aria-valuemin="0" aria-valuenow="0" id="scale-progress">
                        <div class="pat-scale-progress-bar" id="scale-progress-bar"></div>
                    </div>
                </div>

                <div id="scale-questions"></div>

                <p class="pat-scale-note mt-4 mb-1" id="scale-source"></p>
                <p class="pat-scale-note mb-0">Instrumento de rastreio: apoia, mas não substitui, a sua avaliação clínica.</p>
            </div>
            <div class="modal-footer pat-scale-footer">
                <div class="pat-scale-summary" id="scale-summary" aria-live="polite"></div>
                <div class="pat-scale-footer-actions">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-lock me-1"></i> Salvar</button>
                </div>
            </div>
        </form>
    </div>
</div>
