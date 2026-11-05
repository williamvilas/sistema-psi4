@if($transcriptions->isNotEmpty())
    <div class="row">
        @foreach($transcriptions as $value)
            @php($link = route('session-transcriptions.show', ['uuid' => $value->uuid]))
            <div class="col-12 col-md-6 col-xl-4 mb-4">
                <div class="dash-item h-100">
                    <div class="dash-item-head">
                        @include('components.avatar-initials', ['name' => $value->patient->name])
                        <div class="min-w-0 flex-grow-1">
                            <a href="{{ $link }}" class="dash-item-name" title="{{ $value->patient->name }}">{{ $value->patient->name }}</a>
                            <div class="dash-item-sub text-truncate">{{ $value->occurred_on->translatedFormat('D, d/m/Y') }}</div>
                        </div>
                    </div>

                    <div class="mr-card-chips mt-3">
                        <span class="dash-chip dash-chip-muted"><i class="fas fa-lock"></i> Cifrada</span>
                        <span class="dash-chip dash-chip-info"><i class="far fa-clock"></i> Decidir até {{ $value->review_deadline->timezone(\App\Helpers\UserHelper::getTimezone())->format('d/m/Y') }}</span>
                    </div>

                    <div class="dash-item-actions mt-3">
                        <a href="{{ $link }}" class="btn dash-btn-soft"><i class="far fa-eye me-1"></i> Abrir rascunho</a>
                        <button type="button" class="btn btn-outline-danger js-reject-session-transcription"
                                data-uuid="{{ $value->uuid }}" data-patient="{{ $value->patient->name }}" data-date="{{ $value->occurred_on->format('d/m/Y') }}">
                            <i class="fas fa-trash me-1"></i> Recusar
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{ $transcriptions->links() }}
@else
    <p class="text-muted text-center py-5">Nenhum rascunho aguardando revisão.</p>
@endif
