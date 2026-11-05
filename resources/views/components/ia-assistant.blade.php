<div class="card border border-secondary-subtle rounded-3 p-2 h-100">
    <div class="card-header bg-light py-2">
        <strong>Assistente IA com {{ __('plans.model_ia') }}</strong>
        <span class="text-muted small d-block">Ajuda a gerar ou melhorar prontuários com {{ __('plans.model_ia') }}.</span>
    </div>
    <div class="card-body py-2">
        <div id="aiAssistantResult" class="form-control bg-white border rounded-2 small text-break page_speed_ia">
            Nenhum prontuário foi gerado.
        </div>
    </div>

    <div class="footer-assistent">
        <button id="copyAiAssistantResult" type="button" class="d-none btn btn-sm btn-outline-secondary mt-2 mb-2 float-end" title="Copiar resultado">

        </button>
        <textarea id="ai_prompt" class="form-textarea mb-2" rows="9" placeholder="Ex: paciente diabético com dor torácica..." maxlength="1200">{{ __('medical-records.text_medical_record') }}</textarea>

        <div class="d-flex gap-2 mb-2">
            <button id="aiGenerateBtn" onclick="assistantIAMedicalRecords()" type="button" class="btn btn-sm btn-primary flex-grow-1">Gerar prontuário</button>
        </div>

        <button id="aiInsertBtn" type="button" onclick="addResultIAInEditor()" class="btn btn-sm btn-success w-100 mb-2" disabled>Inserir no editor</button>
    </div>
</div>
