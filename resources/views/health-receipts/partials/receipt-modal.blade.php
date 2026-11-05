<div class="modal fade" id="healthReceiptModal" tabindex="-1" aria-labelledby="healthReceiptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down">
        <form class="modal-content" id="form-store-health-receipt" autocomplete="off" novalidate>
            <input type="hidden" id="health_receipt_id" value="">
            <div class="modal-header">
                <h5 class="modal-title" id="healthReceiptModalLabel">Registrar recebimento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Para pagamentos que você recebeu por fora do PSI4, como Pix direto ou dinheiro. As cobranças pagas pelo PSI4 já entram sozinhas.</p>

                <div class="form-group">
                    <label for="health_receipt_patient_id">Paciente <span class="text-danger">*</span></label>
                    <select class="form-select" name="patient_id" id="health_receipt_patient_id" required></select>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="health_receipt_amount">Valor <span class="text-danger">*</span></label>
                            <input type="text" inputmode="numeric" name="amount" id="health_receipt_amount" class="form-control money2" required>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="health_receipt_paid_on">Pago em <span class="text-danger">*</span></label>
                            <input type="text" inputmode="numeric" name="paid_on" id="health_receipt_paid_on" class="form-control date date_only" required>
                            <div class="invalid-feedback invalid-feedback-paid_on not-remove"></div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="health_receipt_description">Descrição no recibo</label>
                    <input type="text" name="description" id="health_receipt_description" class="form-control" maxlength="255" placeholder="{{ __('health-receipts.default_description') }}">
                    <small class="text-muted">Aparece no recibo do paciente. Não coloque informação clínica.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>
