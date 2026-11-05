{{-- data-bs-focus="false": sem o focus trap do Bootstrap, o pedido da senha mestre (Swal) recebe digitação por cima do modal. --}}
<div class="modal fade pat-scale-modal" id="scaleLinkModal" tabindex="-1" aria-labelledby="scaleLinkModalLabel" aria-hidden="true" data-bs-focus="false">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
        <form class="modal-content" id="form-store-scale-link" autocomplete="off" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="scaleLinkModalLabel">
                    <i class="fas fa-paper-plane me-1"></i> Enviar escala por link
                    <span class="pat-scale-lock" title="Respostas cifradas no navegador do paciente"><i class="fas fa-lock"></i> Cifrada</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="pat-scale-link-intro">
                    O paciente responde pelo celular, sem precisar de login. A página não mostra nome, CPF nem CRP,
                    e as respostas chegam cifradas: só você consegue ler.
                </p>

                <fieldset class="mb-3">
                    <legend class="form-label fw-semibold pat-scale-legend">Escala</legend>
                    <div class="pat-scale-picker" role="radiogroup">
                        @foreach($scales as $scale)
                            <input type="radio" class="btn-check" name="scale" id="link_scale_slug_{{ $scale['slug'] }}" value="{{ $scale['slug'] }}" autocomplete="off" @checked($loop->first)>
                            <label class="pat-scale-choice" for="link_scale_slug_{{ $scale['slug'] }}">
                                <strong>{{ $scale['name'] }}</strong>
                                <span>{{ $scale['short'] }}</span>
                                <small>{{ count($scale['items']) }} itens</small>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="pat-scale-link-result d-none" id="scale-link-result" aria-live="polite">
                    <label for="scale-link-url" class="form-label fw-semibold mb-1">
                        <i class="fas fa-check-circle me-1"></i> Link da <span id="scale-link-name"></span> pronto
                    </label>
                    <div class="pat-scale-link-copy">
                        <input type="text" class="form-control" id="scale-link-url" readonly>
                        <button type="button" class="btn btn-primary" id="btn-copy-scale-link">
                            <i class="fa-regular fa-copy me-1"></i> <span>Copiar</span>
                        </button>
                        <button type="button" class="btn btn-outline-primary d-none" id="btn-share-scale-link">
                            <i class="fas fa-share-nodes me-1"></i> Compartilhar
                        </button>
                    </div>
                    <small class="text-muted d-block mt-2">
                        Funciona até <strong id="scale-link-expires"></strong> ou até ser respondido, o que vier primeiro.
                    </small>
                </div>

                <div class="pat-scale-link-history">
                    <h6 class="pat-evo-subtitle mb-2">Links enviados</h6>
                    <div id="scale-links-list" aria-live="polite"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="submit" class="btn btn-primary" id="btn-store-scale-link"><i class="fas fa-link me-1"></i> Gerar link</button>
            </div>
        </form>
    </div>
</div>
