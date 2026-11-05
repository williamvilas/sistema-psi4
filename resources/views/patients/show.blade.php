@extends('layouts.app')
@section('title', 'Dados do Paciente')

@section('header')
    @include('components.css-dash')
    @include('patients.css')
@endsection

@section('content')

    <div class="confetti"></div>

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            @include('components.icon-patient', ['css' => 'me-2']) Dados do Paciente
        </h3>
    </div>

    <div class="row justify-content-center pat-show-page" id="patient-show-page" data-professional="{{ auth()->user()->name }}" data-crp="{{ $crp ?? '' }}">
        <div class="col-12 col-lg-8 col-xl-7">

            @if(!empty($patient))

                @php($currentDate = \App\Helpers\UserHelper::getDateCurrent())
                @php($timezoneByUser = \App\Helpers\UserHelper::getTimezone())
                @php($isToday = $patient->birthday_md == $currentDate->format('m-d'))
                @php($id = encrypt($patient->id))
                @php($age = $patient->birth_date ? calculateAge($patient->birth_date, $timezoneByUser) : null)
                @php($whatsappUrl = route('whatsapp.send', ['phone' => $patient->phone]))
                @php($whatsappActionUrl = $isToday ? route('whatsapp.send', ['phone' => $patient->phone, 'text' => app(\App\Services\WhatsAppLinkService::class)->birthdayMessage($patient->name)]) : $whatsappUrl)

                <nav class="pat-breadcrumb" aria-label="Navegação">
                    <a href="{{ route('patients.index') }}">Pacientes</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>{{ $patient->name }}</span>
                </nav>

                @if(!empty($pendingTranscriptions))
                    <div class="alert alert-info d-flex flex-wrap align-items-center gap-2" role="status">
                        <i class="fas fa-microphone-lines" aria-hidden="true"></i>
                        <span class="flex-grow-1">{{ trans_choice('session-transcriptions.patient_banner', $pendingTranscriptions, ['count' => $pendingTranscriptions]) }}</span>
                        <a href="{{ route('session-transcriptions.index') }}" class="btn btn-primary btn-sm">Revisar</a>
                    </div>
                @endif

                <div class="dash-card pat-show-card" data-patient-show>

                    <div class="pat-show-head">
                        @include('components.avatar-initials', ['name' => $patient->name])
                        <div class="pat-show-head-body">
                            <h4 class="pat-show-name" title="{{ $patient->name }}">{{ $patient->name }}</h4>
                            <div class="pat-show-sub">
                                @if(!is_null($age))
                                    <span>{{ $age }} {{ $age === 1 ? 'ano' : 'anos' }}</span>
                                    <span aria-hidden="true">·</span>
                                @endif
                                <span title="Cadastrado em {{ formatDate($patient->created_at, 'd/m/Y') }}">
                                    Cadastrado {{ $patient->created_at->locale('pt_BR')->diffForHumans(['options' => \Carbon\CarbonInterface::JUST_NOW]) }}
                                </span>
                                @if($isToday)
                                    <span class="dash-chip dash-chip-success"><i class="fas fa-birthday-cake"></i> Aniversário hoje</span>
                                @endif
                            </div>
                        </div>

                        <div class="pat-show-actions">
                            <a href="{{ $whatsappActionUrl }}" target="_blank" rel="noopener" class="btn btn-whatsapp" title="{{ $isToday ? 'Enviar parabéns pelo WhatsApp' : 'Conversar no WhatsApp' }}">
                                <i class="fab fa-whatsapp me-1"></i> {{ $isToday ? 'Dar parabéns' : 'WhatsApp' }}
                            </a>
                            <a href="{{ route('patients.edit', ['id' => $id]) }}" class="btn btn-primary" title="Editar">
                                <i class="fas fa-pencil-alt me-1 icon-size-d"></i> Editar
                            </a>
                            <div class="dropdown">
                                <button type="button" class="dash-round-btn dash-round-muted" data-bs-toggle="dropdown" aria-expanded="false" title="Mais ações" aria-label="Mais ações">
                                    <i class="fas fa-ellipsis-vertical size-icon-ac icon-refresh"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('payments.create', ['patient' => $id]) }}" title="Nova cobrança"><i class="fas fa-file-invoice-dollar me-2"></i> Nova cobrança</a></li>
                                    <li><button type="button" class="dropdown-item" title="Exportar prontuários" id="export-medical-records" onclick="exportMedicalRecords(@js($id), @js($patient->name))"><i class="fas fa-file-export me-2"></i> Exportar prontuários</button></li>
                                    <li><button type="button" class="dropdown-item text-danger" title="Apagar" onclick="deletePatients(@js($id), @js($patient->name), true)"><i class="far fa-trash-alt me-2"></i> Apagar</button></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    @if(!empty($patient->duplicate_phone_count))
                        <div class="alert alert-warning py-2 pat-show-alert">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            Existe {{ $patient->duplicate_phone_count === 1 ? 'outro paciente' : $patient->duplicate_phone_count.' outros pacientes' }} com o mesmo número de WhatsApp.
                        </div>
                    @endif

                    <div class="pat-show-section">
                        <div class="row">
                            <div class="col-12 col-md-6">
                                <h5 class="dash-title pat-show-section-title"><i class="far fa-address-book"></i> Contato</h5>
                                @include('patients.partials.show-field', [
                                    'field' => 'phone',
                                    'icon' => 'fab fa-whatsapp',
                                    'label' => 'WhatsApp',
                                    'value' => formatPhone($patient->phone),
                                    'href' => $whatsappUrl,
                                    'external' => true,
                                    'copy' => $patient->phone,
                                    'copyTitle' => 'WhatsApp copiado',
                                ])
                                @include('patients.partials.show-field', [
                                    'field' => 'email',
                                    'icon' => 'far fa-envelope',
                                    'label' => 'E-mail',
                                    'value' => $patient->email,
                                    'href' => $patient->email ? 'mailto:'.$patient->email : null,
                                    'copy' => $patient->email,
                                    'copyTitle' => 'E-mail copiado',
                                ])
                            </div>
                            <div class="col-12 col-md-6 mt-3 mt-md-0">
                                <h5 class="dash-title pat-show-section-title"><i class="far fa-id-card"></i> Dados pessoais</h5>
                                @include('patients.partials.show-field', [
                                    'field' => 'document',
                                    'icon' => 'far fa-id-badge',
                                    'label' => 'CPF',
                                    'value' => $patient->document ? formatCPF($patient->document) : null,
                                    'copy' => $patient->document,
                                    'copyTitle' => 'CPF copiado',
                                ])
                                @include('patients.partials.show-field', [
                                    'field' => 'birth_date',
                                    'icon' => 'fas fa-birthday-cake',
                                    'label' => 'Data de nascimento',
                                    'value' => $patient->birth_date ? formatDate($patient->birth_date, 'd/m/Y') : null,
                                ])
                            </div>
                        </div>
                    </div>

                    <div class="pat-show-section">
                        <h5 class="dash-title pat-show-section-title"><i class="far fa-sticky-note"></i> Observações</h5>
                        @if(filled($patient->notes))
                            <p class="pat-show-notes" data-field="notes">{!! nl2br(e($patient->notes)) !!}</p>
                        @else
                            <p class="pat-show-notes pat-show-empty" data-field="notes">Nenhuma observação registrada.</p>
                        @endif
                    </div>
                </div>

                @include('patients.partials.evolution', [
                    'id' => $id,
                    'scales' => $scales,
                    'periods' => $evolutionPeriods,
                ])
            @else
                @include('components.unauthorized')
                <p class="text-center sub-alert-p">Essa restrição garante a confidencialidade e a segurança das informações.</p>
            @endif
        </div>
    </div>

@endsection

@section('footer')
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            @if(!empty($isToday))
            setTimeout( createConfettiInPatient, 500);
            setInterval(createConfettiInPatient, 5000);
            @endif
        });
    </script>
@endsection
