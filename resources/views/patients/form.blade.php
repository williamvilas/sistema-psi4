{{-- Formulário de cadastro/edição de paciente. Recebe $formId e, na edição, $patient. --}}
@php($patient = $patient ?? null)

<form id="{{ $formId }}" class="pat-form" autocomplete="off" novalidate data-patient-form>
    @if($patient)
        <input type="hidden" value="{{ encrypt($patient->id) }}" id="inputId"/>
    @endif

    <p class="pat-form-legend"><span class="text-danger">*</span> Campos obrigatórios</p>

    <section class="pat-form-section">
        <h4 class="dash-title"><i class="fa-regular fa-id-card"></i> Dados principais</h4>

        <div class="row">
            <div class="col-md-6 col-12">
                <div class="form-group">
                    <label for="name">Nome <span class="text-danger">*</span></label>
                    <div class="pat-input">
                        <i class="fa-regular fa-user"></i>
                        <input type="text" name="name" id="name" class="form-control" value="{{ $patient?->name }}"
                               maxlength="255" autocomplete="off" placeholder="Nome completo" @if(!$patient) autofocus @endif required/>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-12">
                <div class="form-group">
                    <label for="phone">WhatsApp <span class="text-danger">*</span></label>
                    <div class="pat-input">
                        <i class="fab fa-whatsapp"></i>
                        <input type="tel" name="phone" id="phone" class="form-control whatsapp" inputmode="tel" autocomplete="off"
                               value="{{ $patient ? formatPhone($patient->phone) : '' }}" required/>
                    </div>
                    <small class="pat-hint">
                        Usado para lembretes e confirmações de consulta.
                        @if($patient)
                            <a href="{{ route('whatsapp.send', ['phone' => $patient->phone]) }}" target="_blank" rel="noopener" class="pat-hint-link">
                                Abrir conversa <i class="fas fa-external-link-alt"></i>
                            </a>
                        @endif
                    </small>
                    <div class="form-check form-switch mt-2">
                        {{-- Desmarcado envia 0: sem o campo (ex.: cadastro rápido pela agenda) os avisos ficam ligados --}}
                        <input type="hidden" name="whatsapp_notifications" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="whatsapp_notifications" id="whatsapp_notifications" value="1"
                               @checked($patient?->whatsapp_notifications ?? true)>
                        <label class="form-check-label" for="whatsapp_notifications">Enviar confirmações e lembretes automáticos</label>
                    </div>
                    <small class="pat-hint">O paciente também pode parar de receber respondendo SAIR.</small>
                </div>
            </div>
        </div>
    </section>

    <section class="pat-form-section">
        <h4 class="dash-title"><i class="fa-regular fa-address-book"></i> Informações complementares</h4>

        <div class="row">
            <div class="col-md-6 col-12">
                <div class="form-group">
                    <label for="birth_date">Data de nascimento</label>
                    <div class="pat-input">
                        <i class="fa-regular fa-calendar"></i>
                        <input type="tel" name="birth_date" id="birth_date" class="form-control date" inputmode="numeric" autocomplete="off"
                               value="{{ $patient?->birth_date ? formatDate($patient->birth_date, 'd/m/Y') : '' }}"/>
                    </div>
                    <small class="pat-hint">Permite lembrar o aniversário do paciente.</small>
                </div>
            </div>

            <div class="col-md-6 col-12">
                <div class="form-group">
                    @php($noDocument = $patient && empty($patient->document))
                    <label for="document">CPF <span class="text-danger">*</span></label>
                    <div class="pat-input">
                        <i class="fa-regular fa-address-card"></i>
                        <input type="tel" name="document" id="document" class="form-control cpf" inputmode="numeric" autocomplete="off"
                               value="{{ $patient?->document ? formatCPF($patient->document) : '' }}" @disabled($noDocument)/>
                    </div>
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" name="no_document" id="no_document" value="1" @checked($noDocument) data-no-document>
                        <label class="form-check-label" for="no_document">Paciente sem CPF</label>
                    </div>
                    <small class="pat-hint">Também é usado no recibo e para entrar na sala de vídeo. Sem CPF, o paciente entra pelo telefone.</small>
                </div>
            </div>

            @php($hasPayer = !empty($patient?->payer_document) || !empty($patient?->payer_name))
            <div class="col-12">
                <div class="form-check mt-1 mb-2">
                    <input class="form-check-input" type="checkbox" name="has_payer" id="has_payer" value="1" @checked($hasPayer) data-toggle-payer>
                    <label class="form-check-label" for="has_payer">Quem paga as sessões é outra pessoa (responsável financeiro)</label>
                </div>
                <small class="pat-hint">Ex.: mãe ou pai de um paciente menor de idade. O CPF do responsável vai como pagador no recibo do Receita Saúde.</small>
            </div>

            <div class="col-md-6 col-12" data-payer-field @if(!$hasPayer) hidden @endif>
                <div class="form-group">
                    <label for="payer_name">Nome do responsável financeiro</label>
                    <div class="pat-input">
                        <i class="fa-regular fa-user"></i>
                        <input type="text" name="payer_name" id="payer_name" class="form-control" maxlength="255" autocomplete="off"
                               value="{{ $patient?->payer_name }}" placeholder="Nome completo"/>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-12" data-payer-field @if(!$hasPayer) hidden @endif>
                <div class="form-group">
                    <label for="payer_document">CPF do responsável financeiro</label>
                    <div class="pat-input">
                        <i class="fa-regular fa-address-card"></i>
                        <input type="tel" name="payer_document" id="payer_document" class="form-control cpf" inputmode="numeric" autocomplete="off"
                               value="{{ $patient?->payer_document ? formatCPF($patient->payer_document) : '' }}"/>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="form-group">
                    <label for="email">E-mail</label>
                    <div class="pat-input">
                        <i class="fa-regular fa-envelope"></i>
                        <input type="email" name="email" id="email" class="form-control" inputmode="email" autocomplete="off"
                               value="{{ $patient?->email }}" placeholder="nome@exemplo.com"/>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="pat-form-section">
        <h4 class="dash-title"><i class="fa-regular fa-note-sticky"></i> Observações</h4>

        <div class="form-group">
            <label for="notes" class="visually-hidden">Observação</label>
            <textarea name="notes" id="notes" class="form-textarea" rows="5"
                      placeholder="Informações úteis sobre o paciente (ex.: responsável, convênio, preferências de horário)">{{ $patient?->notes }}</textarea>
        </div>
    </section>

    <div class="pat-form-actions">
        <a href="{{ $cancelUrl }}" class="btn btn-outline-secondary">Cancelar</a>

        @if(!$patient)
            <button type="button" class="btn btn-outline-primary" id="btn-store-patients-and-new">
                <i class="fa-solid fa-plus me-1"></i> Salvar e cadastrar outro
            </button>
        @endif

        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-check me-1"></i> Salvar
        </button>
    </div>
</form>
