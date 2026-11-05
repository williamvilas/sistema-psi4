<div class="row-exist-patient">

    @if(!empty($patient))
        <div class="confetti"></div>

        <div class="row justify-content-center">
            <div class="col-12">
                @php($timezoneByUser = \App\Helpers\UserHelper::getTimezone())
                <input type="hidden" name="patient_id" id="patient_id" value="{{ encrypt($patient->id) }}"/>
                <p><strong>Nome:</strong> <span class="text-patient-name">{{ $patient->name }}</span> </p>
                <p>
                    <strong>WhatsApp:</strong> <span class="text-patient-phone">{{ formatPhone($patient->phone) }}</span>
                    <a href="{{ route('whatsapp.send', ['phone' => $patient->phone]) }}" target="_blank" class="ms-2"><i class="fas fa-external-link-alt"></i></a>
                </p>

                <p><strong>CPF:</strong> {{ $patient->document ? formatCPF($patient->document) : '-' }}</p>

                <p class="mb-1">
                    <strong>Idade:</strong> {{ $patient->birth_date ? calculateAge($patient->birth_date, $timezoneByUser) : '-' }}
                </p>

                <p>
                    <strong>Data de Nascimento:</strong> {{ $patient->birth_date ? formatDate($patient->birth_date, 'd/m/Y') : '-' }}

                    @if($isToday)
                        @include('components.birth-date')
                    @endif
                </p>
            </div>
        </div>
    @else
        <input type="hidden" name="patient_id" id="patient_id" />
        <div class="row">
            <div class="col-md-6 col-12">
                <div class="form-group">
                    <label for="name">Nome <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" class="form-control" required/>
                </div>
            </div>

            <div class="col-md-6 col-12">
                <div class="form-group">
                    <label for="phone">WhatsApp <span class="text-danger">*</span></label>
                    <input type="tel" name="phone" value="{{ formatPhone($phone) }}" id="phone" class="form-control whatsapp" required/>
                </div>
            </div>

            <div class="col-md-6 col-12">
                <div class="form-group">
                    <label for="birth_date">Data de Nascimento <small>(Opcional)</small></label>
                    <input type="tel" name="birth_date" id="birth_date" class="form-control date"/>
                </div>
            </div>

            <div class="col-md-6 col-12">
                <div class="form-group">
                    <label for="document">CPF <span class="text-danger">*</span></label>
                    <input type="tel" name="document" id="document" class="form-control cpf"/>
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" name="no_document" id="no_document" value="1" data-no-document>
                        <label class="form-check-label" for="no_document">Paciente sem CPF</label>
                    </div>
                </div>
            </div>

            <div class="col-md-12 col-12">
                <div class="form-group">
                    <label for="email">E-mail <small>(Opcional)</small></label>
                    <input type="email" name="email" id="email" class="form-control"/>
                </div>
            </div>

            <div class="col-md-12 col-12">
                <div class="form-group">
                    <label for="notes">Observação <small>(Opcional)</small></label>
                    <textarea name="notes" id="notes" class="form-control" rows="7"></textarea>
                </div>
            </div>
        </div>
    @endif
</div>
