@extends('public-booking.layout')
@section('title', 'Agendamento confirmado — '.$profile['name'])
@section('content')
@php
  $date = \Carbon\Carbon::createFromFormat('Y-m-d', $summary['date'] ?? '1970-01-01');
  $type = \App\Enums\AgendaTypeEnum::tryFrom($summary['type'] ?? '') ?? \App\Enums\AgendaTypeEnum::ONLINE;
@endphp
<div class="form-side d-flex flex-column justify-content-center h-100 text-center">
  <div class="mx-auto mb-3" style="width:72px;height:72px;border-radius:50%;background:var(--brand-light);display:grid;place-items:center;">
    <i class="bi bi-check-lg" style="font-size:2.4rem;color:var(--brand);"></i>
  </div>
  <h2 class="title">Agendamento realizado com sucesso!</h2>
  <p class="subtitle">Você vai receber a confirmação no seu WhatsApp e, perto do horário, um lembrete.</p>

  <div class="text-start mb-4 p-3" style="background:var(--brand-light);border-radius:14px;font-size:.88rem;">
    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Nome</span><strong>{{ $summary['name'] ?? '—' }}</strong></div>
    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Serviço</span><strong>{{ $summary['service'] ?? '—' }}</strong></div>
    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Modalidade</span><strong>{{ $type->getText() }}</strong></div>
    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Data</span><strong>{{ $date->format('d/m/Y') }}</strong></div>
    <div class="d-flex justify-content-between mb-2"><span class="text-muted">Horário</span><strong>{{ $summary['time'] ?? '—' }}</strong></div>
    <div class="d-flex justify-content-between"><span class="text-muted">Valor</span><strong>{{ formatCurrency($summary['price'] ?? 0) }}</strong></div>
  </div>

  @if($type === \App\Enums\AgendaTypeEnum::ONLINE)
    <div class="text-start mb-4 p-3" style="background:var(--brand-light);border-radius:14px;font-size:.88rem;">
      <p class="mb-0"><i class="bi bi-camera-video me-1"></i> Sua sessão é online. O link da sala chega no seu WhatsApp {{ config('whatsapp.room_link_minutes') }} minutos antes do horário marcado.</p>
    </div>
  @else
    <div class="text-start mb-4 p-3" style="background:var(--brand-light);border-radius:14px;font-size:.88rem;">
      <p class="mb-0"><i class="bi bi-geo-alt me-1"></i> Sua sessão é presencial{{ !empty($summary['address']) ? ', no endereço: '.$summary['address'] : '' }}.</p>
      @if(!empty($summary['maps_url']))
        <a href="{{ $summary['maps_url'] }}" class="d-inline-block mt-2 fw-semibold" style="color:var(--brand);" target="_blank" rel="noopener noreferrer" aria-label="Ver rota até o consultório no Google Maps (abre em nova aba)">Ver rota no Google Maps <i class="bi bi-box-arrow-up-right ms-1"></i></a>
      @endif
    </div>
  @endif

  <a href="{{ route('public-booking.show', ['slug' => $slug]) }}" class="btn btn-brand w-100">Fazer novo agendamento</a>
</div>
@endsection
