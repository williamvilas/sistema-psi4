@php
    // Compartilhado entre criar e editar. Na edição ($order) o paciente é fixo; na criação pode vir pré-selecionado ($patient).
    $order ??= null;
    $patient ??= null;
    $disabled ??= false;

    $dueDate = $order?->due_date?->format('d/m/Y');
    $selectedMethods = $order ? ($order->payment_methods ?? []) : array_map(fn ($method) => $method->value, \App\Enums\PaymentMethodEnum::cases());
    $duePresets = ['none' => 'Sem vencimento', '3' => '3 dias', '7' => '7 dias', '15' => '15 dias', '30' => '30 dias', 'custom' => 'Outra data'];
    $currentPreset = $dueDate ? 'custom' : 'none';
@endphp

<div class="dash-card pay-form-card">
    <fieldset @disabled($disabled)>

        <section class="pay-section">
            <div class="pay-section-head">
                <span class="pay-step">1</span>
                <h5 class="pay-section-title">Quem vai pagar</h5>
            </div>

            @if($order)
                @php($orderPatient = $order->patient)
                @php($status = $order->getCurrentStatus())
                <div class="pay-patient">
                    @include('components.avatar-initials', ['name' => $orderPatient->name])
                    <div class="pay-patient-body">
                        <div class="pay-patient-name" title="{{ $orderPatient->name }}">{{ $orderPatient->name }}</div>
                        <div class="pay-patient-sub">{{ formatPhone($orderPatient->phone) }}</div>
                    </div>
                    <span class="badge {{ $status->color() }}">{{ $status->getText() }}</span>
                </div>
            @else
                <label class="pay-label" for="patient_id">Paciente</label>
                <select class="form-select" name="patient_id" id="patient_id" required>
                    @if($patient)
                        <option value="{{ encrypt($patient->id) }}" data-name="{{ $patient->name }}" selected>{{ $patient->name }} - {{ formatPhone($patient->phone) }}</option>
                    @endif
                </select>
            @endif
        </section>

        <section class="pay-section">
            <div class="pay-section-head">
                <span class="pay-step">2</span>
                <h5 class="pay-section-title">Valor e vencimento</h5>
            </div>

            <div class="mb-4">
                <label class="pay-label" for="amount">Valor</label>
                <input type="text" inputmode="numeric" name="amount" id="amount" class="form-control money2 pay-amount-input" placeholder="R$ 0,00"
                       value="{{ $order ? 'R$ '.number_format((float) $order->amount, 2, ',', '.') : '' }}" required>
            </div>

            <label class="pay-label d-block" id="due_presetLabel">Vencimento</label>
            <div class="cfg-choices" role="radiogroup" aria-labelledby="due_presetLabel">
                @foreach($duePresets as $value => $label)
                    <input type="radio" class="btn-check" name="due_preset" id="due_preset_{{ $value }}" value="{{ $value }}" autocomplete="off" @checked($value === $currentPreset)>
                    <label class="cfg-choice" for="due_preset_{{ $value }}">{{ $label }}</label>
                @endforeach
            </div>
            <div class="pay-due-custom" @if($currentPreset !== 'custom') hidden @endif>
                <input type="text" inputmode="numeric" name="due_date" id="due_date" class="form-control date date_only" value="{{ $dueDate }}" aria-label="Data de vencimento">
            </div>
            <div class="invalid-feedback invalid-feedback-due_date not-remove"></div>
            <small class="pay-hint"><i class="far fa-clock me-1"></i>O link de pagamento deixa de funcionar ao fim do dia do vencimento.</small>
        </section>

        <section class="pay-section">
            <div class="pay-section-head">
                <span class="pay-step">3</span>
                <h5 class="pay-section-title">Como o paciente pode pagar</h5>
            </div>

            <div class="pay-methods-grid">
                @foreach(\App\Enums\PaymentMethodEnum::cases() as $method)
                    <input type="checkbox" class="btn-check" name="payment_methods[]" id="payment_method_{{ $method->value }}" value="{{ $method->value }}" autocomplete="off" @checked(in_array($method->value, $selectedMethods, true))>
                    <label class="pay-method" for="payment_method_{{ $method->value }}">
                        <span class="pay-method-icon"><i class="{{ $method->icon() }}"></i></span>
                        <span class="pay-method-body">
                            <strong>{{ $method->getText() }}</strong>
                            <small>{{ $method->description() }}</small>
                        </span>
                        <i class="fas fa-circle-check pay-method-check" aria-hidden="true"></i>
                    </label>
                @endforeach
            </div>
            <div class="invalid-feedback invalid-feedback-payment_methods not-remove"></div>
        </section>

        <section class="pay-section">
            <div class="d-flex justify-content-between align-items-end">
                <label class="pay-label" for="description">Descrição <span class="pay-optional">(opcional)</span></label>
                <small class="pay-optional"><span id="description-count">{{ mb_strlen((string) $order?->description) }}</span>/255</small>
            </div>
            <input type="text" name="description" id="description" class="form-control" maxlength="255" placeholder="Ex.: Sessões de outubro" value="{{ $order?->description }}">
            <small class="pay-hint">Ajuda a identificar a cobrança na sua lista e no Mercado Pago.</small>
        </section>

    </fieldset>
</div>
