{{-- Limite do plano: avisa antes de ela começar a escrever --}}
<div class="card">
    <div class="card-body text-center py-5">
        <i class="fas fa-hourglass-end fa-2x mb-3 text-warning" aria-hidden="true"></i>
        <h2 class="h5">Seu plano chegou ao limite de prontuários deste mês</h2>
        <p class="text-muted mb-4">{{ __('plans.limit_reached.total_medical_records') }}</p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="{{ route('plans.index') }}" class="btn btn-primary">Fazer upgrade</a>
            <a href="{{ $backUrl ?? route('medical-records.index') }}" class="btn btn-outline-secondary">{{ $backLabel ?? 'Ver meus prontuários' }}</a>
        </div>
    </div>
</div>
