<div class="modal fade" id="generateBatchModal" tabindex="-1" aria-labelledby="generateBatchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down">
        <form class="modal-content" id="form-store-health-receipt-batch" autocomplete="off" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="generateBatchModalLabel">Gerar arquivo para o Carnê-Leão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">Entram os recibos a emitir pagos no período. Os que têm pendência (como paciente sem CPF) ficam de fora até você corrigir.</p>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label for="batch_start_date">Pago de <span class="text-danger">*</span></label>
                            <input type="text" inputmode="numeric" name="start_date" id="batch_start_date" class="form-control date date_only" required>
                            <div class="invalid-feedback invalid-feedback-start_date not-remove"></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label for="batch_end_date">Até <span class="text-danger">*</span></label>
                            <input type="text" inputmode="numeric" name="end_date" id="batch_end_date" class="form-control date date_only" required>
                            <div class="invalid-feedback invalid-feedback-end_date not-remove"></div>
                        </div>
                    </div>
                </div>

                <small class="text-muted d-block">O Carnê-Leão aceita só pagamentos de um mesmo ano em cada arquivo.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-file-export me-1"></i> Gerar e baixar</button>
            </div>
        </form>
    </div>
</div>
