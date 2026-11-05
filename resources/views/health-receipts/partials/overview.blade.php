@if($overview['missing_professional_document'])
    <div class="hr-alert hr-alert-danger" role="alert">
        <i class="fas fa-id-card"></i>
        <div>
            <strong>Falta o seu CPF.</strong> Ele vai no recibo e precisa ser o mesmo que você usa no Carnê-Leão.
            <a href="{{ route('profile.edit') }}" class="fw-semibold">Informar no perfil</a>
        </div>
    </div>
@endif

@if($overview['refunded']->isNotEmpty())
    <div class="hr-alert hr-alert-danger" role="alert">
        <i class="fas fa-undo"></i>
        <div>
            <strong>Pagamentos estornados depois de ir para o arquivo.</strong>
            Se o recibo já foi emitido, cancele no Carnê-Leão. O cancelamento só é aceito em até 10 dias da emissão.
            <ul>
                @foreach($overview['refunded'] as $receipt)
                    <li>{{ $receipt->patient?->name }} · {{ formatCurrency($receipt->amount) }} · pago em {{ $receipt->paid_on->format('d/m/Y') }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

@if($overview['overdue_count'] > 0)
    <div class="hr-alert hr-alert-warning" role="alert">
        <i class="fas fa-clock"></i>
        <div>
            <strong>{{ $overview['overdue_count'] }} {{ $overview['overdue_count'] === 1 ? 'recibo com o prazo de emissão retroativa encerrado' : 'recibos com o prazo de emissão retroativa encerrado' }}.</strong>
            O prazo vai até o último dia de fevereiro do ano seguinte ao pagamento. Converse com seu contador sobre como regularizar.
        </div>
    </div>
@endif

@php($pending = $overview['pending'])
<p class="mb-3">
    @if($pending['count'] > 0)
        <span class="hr-pending-total">{{ $pending['count'] }} {{ $pending['count'] === 1 ? 'recibo a emitir' : 'recibos a emitir' }}</span>
        · {{ formatCurrency($pending['total']) }}
    @else
        <span class="text-muted">Nenhum recibo a emitir no momento.</span>
    @endif
</p>
