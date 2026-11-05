@foreach($fields as $field)
    <div class="col-md-12 col-12">
        <div class="form-group">
            <label for="field-{{ $field['key'] }}">{{ $field['label'] }}</label>

            @if($field['type'] === 'date')
                <input type="tel" name="{{ $field['key'] }}" id="field-{{ $field['key'] }}" class="form-control date date_only document-field">
            @elseif($field['type'] === 'select')
                <select name="{{ $field['key'] }}" id="field-{{ $field['key'] }}" class="form-select document-field">
                    <option value="">Selecione</option>
                    @foreach($field['options'] as $value => $text)
                        <option value="{{ $value }}">{{ $text }}</option>
                    @endforeach
                </select>
            @else
                <textarea name="{{ $field['key'] }}" id="field-{{ $field['key'] }}" class="form-control document-field" rows="{{ $field['rows'] }}" maxlength="5000">{{ $field['value'] }}</textarea>
                <div class="d-flex justify-content-end gap-3 mt-1">
                    <button type="button" class="btn btn-link btn-sm p-0 text-secondary" onclick="saveDocumentDefault('{{ $field['key'] }}')">Salvar como padrão</button>
                    <button type="button" class="btn btn-link btn-sm p-0 text-secondary" onclick="resetDocumentDefault('{{ $field['key'] }}')">Restaurar padrão</button>
                </div>
            @endif
            <div class="invalid-feedback"></div>
        </div>
    </div>
@endforeach
