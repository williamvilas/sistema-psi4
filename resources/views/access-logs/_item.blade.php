{{-- Um registro de acesso. $compact: painel lateral do prontuário (sem a linha do prontuário, horário abaixo da ação). --}}
@php($compact = $compact ?? false)
<div class="al-item {{ $compact ? 'al-item-compact' : '' }}" data-access-log="{{ $log->action->value }}">
    <div class="al-icon {{ $log->action->isDeletion() ? 'is-danger' : '' }}">
        <i class="fas {{ $log->action->icon() }}"></i>
    </div>
    <div class="al-body">
        <div class="al-action">{{ $log->action->label() }}</div>
        @if(! $compact && $log->action->isMedicalRecord())
            <div class="al-record text-truncate">
                @if($log->medical_record_title)
                    {{ $log->patient_name }} · {{ $log->medical_record_title }}
                    @if($log->medical_record_deleted_at)
                        <span class="dash-chip dash-chip-muted">Apagado</span>
                    @endif
                @else
                    <span class="text-muted">Prontuário removido definitivamente</span>
                @endif
            </div>
        @elseif(! $compact && $log->action->isScaleApplication())
            <div class="al-record text-truncate">
                @if($log->scale)
                    {{ $log->patient_name }} · {{ \App\Enums\ScaleEnum::from($log->scale)->label() }}
                    @if($log->scale_application_deleted_at)
                        <span class="dash-chip dash-chip-muted">Apagada</span>
                    @endif
                @else
                    <span class="text-muted">Escala removida definitivamente</span>
                @endif
            </div>
        @endif
        {{-- Resposta pelo link: quem agiu foi o paciente, embora o registro fique na conta da psicóloga. --}}
        <div class="al-meta">
            @if($log->action === \App\Enums\AccessLogActionEnum::SCALE_APPLICATION_ANSWERED)
                <i class="fas fa-paper-plane me-1"></i> {{ __('access-logs.patient_via_link') }}
            @elseif($log->user_name)
                <i class="fas fa-user me-1"></i> {{ $log->user_name }}
            @else
                <i class="fas fa-user me-1"></i> <span class="text-muted">{{ __('access-logs.unidentified_user') }}</span>
            @endif
        </div>
        @if($compact)
            <div class="al-meta" title="{{ formatDate($log->created_at, 'd/m/Y H:i:s', $timezone) }}">
                <i class="far fa-clock me-1"></i> {{ formatDate($log->created_at, 'd/m/Y H:i', $timezone) }}
            </div>
        @endif
        <div class="al-meta">
            <i class="fas fa-desktop me-1"></i> {{ \App\Helpers\UserAgentHelper::describe($log->user_agent) }}
            · IP {{ $log->ip ?? '-' }}
        </div>
    </div>
    @unless($compact)
        <div class="al-time" title="{{ formatDate($log->created_at, 'd/m/Y H:i:s', $timezone) }}">
            {{ formatDate($log->created_at, 'd/m/Y H:i', $timezone) }}
        </div>
    @endunless
</div>
