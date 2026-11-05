@if(!empty($records) && $records->isNotEmpty())

    @php($total = $records->total())
    <p class="dash-total mb-3">{{ trans_choice('messages.total_records', $total, ['count' => $total]) }}: {{ formatNumber($total) }}</p>

    <div class="row">
        @foreach($records as $value)
            @php($id = encrypt($value->id))
            @php($showUrl = route('medical-records.show', ['id' => $id]))
            <div class="col-12 col-md-6 col-xl-4 col-xxl-3 mb-4">
                <div class="dash-item h-100" data-medical-record-card>

                    <div class="dash-item-head">
                        @include('components.avatar-initials', ['name' => $value->patient_name])
                        <div class="min-w-0 flex-grow-1">
                            <a href="{{ $showUrl }}" class="dash-item-name" title="{{ $value->patient_name }}">{{ $value->patient_name }}</a>
                            <div class="dash-item-sub text-truncate">
                                {{ \Carbon\Carbon::parse($value->date_of_consultation)->translatedFormat('D, d/m/Y') }}
                            </div>
                        </div>
                    </div>

                    <div class="mr-card-title" title="{{ $value->title }}">
                        <i class="far fa-file-alt"></i>
                        <span class="text-truncate">{{ $value->title }}</span>
                    </div>

                    <div class="mr-card-chips">
                        @if($value->agenda_id)
                            <span class="dash-chip dash-chip-info" title="Prontuário registrado a partir de um agendamento"><i class="far fa-calendar-check"></i> Da agenda</span>
                        @endif
                        <span class="dash-chip dash-chip-muted" title="Cifrado no seu navegador: nem nós conseguimos ler o que você escreve"><i class="fas fa-lock"></i> Criptografado</span>
                    </div>

                    <div class="dash-item-actions mt-3">
                        <a href="{{ $showUrl }}" class="btn dash-btn-soft" title="Visualizar"><i class="far fa-eye me-1"></i> Abrir</a>

                        <div class="ms-auto dropdown">
                            <button type="button" class="dash-round-btn dash-round-muted" data-bs-toggle="dropdown" aria-expanded="false" title="Mais ações">
                                <i class="fas fa-ellipsis-vertical size-icon-ac icon-refresh"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" title="Editar" href="{{ route('medical-records.edit', ['id' => $id]) }}"><i class="fas fa-pencil-alt me-2"></i> Editar</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><button type="button" class="dropdown-item text-danger" title="Apagar" onclick="deleteMedicalRecords(@js($id), @js($value->patient_name.' - '.$value->title), false, @js($value->isWithinRetention()))"><i class="far fa-trash-alt me-2"></i> Apagar</button></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-12 d-flex justify-content-center">
            {!! $records->links() !!}
        </div>
    </div>

@else
    @include('components.empty', [
        'icon' => 'far fa-file-alt',
        'message' => ($hasFilters ?? false) ? 'Nenhum prontuário encontrado para este filtro.' : 'Você ainda não cadastrou nenhum prontuário.',
    ])
@endif
