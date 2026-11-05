@extends('public-booking.layout')
@section('title', $profile['name'].' — Agendamento')
@section('head')
<style>
  body{background:#dddddd;}
</style>
@endsection
@section('content')
<div class="form-side">

  <h2 class="title">Agende sua consulta</h2>
  <p class="subtitle">Sessões de {{ $profile['minutes'] }} minutos · Atendimento {{ ['online' => 'online', 'in_person' => 'presencial', 'both' => 'online e presencial'][$profile['mode']] }}</p>

  <div class="wizard-progress" id="progresso"></div>
  <div class="resumo" id="resumo"></div>

  <form id="form-public-booking" data-slug="{{ $slug }}" novalidate>
    <input type="hidden" name="date" id="date">
    <input type="hidden" name="time" id="time">
    <input type="hidden" name="type" id="type">
    
    {{-- Honeypot: campo invisível, bots costumam preenchê-lo --}}
    <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;" aria-hidden="true">

    {{-- Serviço: com apenas um, já vem selecionado e o passo é omitido --}}
    @if(count($profile['services']) > 1)
      <input type="hidden" name="service_id" id="service_id">
      <div class="step" data-step="service">
        <div class="step-title">Qual serviço você procura?</div>
        <div class="opt-list">
          @foreach($profile['services'] as $service)
            <button type="button" class="opt-btn" data-target="#service_id" data-value="{{ $service['id'] }}" data-label="{{ $service['name'] }}">
              <i class="bi bi-heart-pulse"></i>
              <span><strong>{{ $service['name'] }}</strong><small>{{ formatCurrency($service['price']) }}</small></span>
            </button>
          @endforeach
        </div>
      </div>
    @else
      <input type="hidden" name="service_id" id="service_id" value="{{ $profile['services'][0]['id'] ?? '' }}">
    @endif

    <div class="step" data-step="datetime">
      <div class="step-title">Escolha o dia e o horário</div>
      <div class="dias-grid" id="dias"></div>
      <div id="diasVazio" class="hint d-none">Não há horários disponíveis no momento. Tente novamente mais tarde.</div>

      {{-- Aparece sempre que há atendimento online e presencial nos próximos dias; a modalidade ausente no dia fica desabilitada --}}
      <div class="modalidade-wrap d-none" id="modalidadeWrap">
        <div class="section-label">Como prefere ser atendido(a)?</div>
        <div class="modalidade-grid">
          <button type="button" class="hora-btn modalidade-btn" data-type="online" aria-pressed="false"><i class="bi bi-camera-video"></i>Online</button>
          <button type="button" class="hora-btn modalidade-btn" data-type="in_person" aria-pressed="false"><i class="bi bi-geo-alt"></i>Presencial</button>
        </div>
        <div class="modalidade-hint d-none" id="modalidadeHint"></div>
      </div>

      <div class="horarios-wrap d-none" id="horariosWrap">
        <div class="section-label">Horários disponíveis<span class="modalidade-tag" id="horariosModalidade"></span></div>
        <div class="hora-grid" id="horarios"></div>
      </div>
    </div>

    <div class="step" data-step="name">
      <label for="name" class="step-title d-block">Qual é o nome completo de quem será atendido?</label>
      <input type="text" class="form-control" id="name" name="name" required placeholder="Nome completo" maxlength="255" autocomplete="name">
      <div class="invalid-feedback">Informe o nome.</div>
    </div>

    <div class="step" data-step="phone">
      <label for="phone" class="step-title d-block">Qual é o seu WhatsApp?</label>
      <input type="tel" class="form-control whatsapp" id="phone" name="phone" required placeholder="(11) 90000-0000" autocomplete="tel">
      <div class="invalid-feedback">Informe um telefone válido.</div>
    </div>

    <div class="step" data-step="document">
      <label for="document" class="step-title d-block">E por último, o CPF de quem será atendido</label>
      <input type="tel" class="form-control cpf" id="document" name="document" inputmode="numeric" autocomplete="off" placeholder="___.___.___-__">
      <div class="invalid-feedback">Informe um CPF válido.</div>

      <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" id="no_document" name="no_document" value="1" data-no-document>
        <label class="form-check-label" for="no_document">Não tenho CPF</label>
      </div>
      <p class="text-muted mt-2 mb-0 d-none" id="no-document-hint" style="font-size:.85rem;">Sem problema. Para entrar em uma sessão online, use o seu telefone.</p>

      <div id="payer-question" class="mt-3">
        <p class="mb-1">Este CPF é o de quem vai pagar as sessões?</p>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="has_payer" id="has_payer_no" value="0">
          <label class="form-check-label" for="has_payer_no">Sim</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="has_payer" id="has_payer_yes" value="1">
          <label class="form-check-label" for="has_payer_yes">Não</label>
        </div>
        <div class="invalid-feedback" id="has-payer-error">Escolha uma das opções.</div>

        <div id="payer-fields" class="mt-2 d-none">
          <label for="payer_name" class="form-label mb-1">Nome do responsável financeiro</label>
          <input type="text" class="form-control mb-2" id="payer_name" name="payer_name" maxlength="255" autocomplete="off" placeholder="Nome completo">
          <div class="invalid-feedback">Informe o nome do responsável.</div>

          <label for="payer_document" class="form-label mb-1">CPF do responsável financeiro</label>
          <input type="tel" class="form-control cpf" id="payer_document" name="payer_document" inputmode="numeric" autocomplete="off" placeholder="___.___.___-__">
          <div class="invalid-feedback">Informe um CPF válido e diferente do CPF de quem será atendido.</div>
          <small class="text-muted">Ele vai como pagador no recibo.</small>
        </div>
      </div>

      <p class="text-muted mt-3 mb-0" style="font-size:.78rem;">
        Ao confirmar, você concorda que os dados informados, inclusive os CPFs, sejam usados apenas para o agendamento, o contato sobre a consulta, o recibo e a entrada na sala de vídeo, incluindo a confirmação e o lembrete do horário pelo WhatsApp. Para parar de receber, é só responder SAIR.
      </p>
    </div>

    <div class="wizard-nav">
      <button type="button" class="btn-back" id="btnVoltar" hidden>
        <i class="bi bi-arrow-left me-1"></i>Voltar
      </button>
      <button type="button" class="btn btn-brand ms-auto d-none" id="btnProximo">
        Continuar <i class="bi bi-arrow-right ms-1"></i>
      </button>
      <button type="submit" class="btn btn-brand ms-auto d-none" id="btnConfirmar">
        <i class="bi bi-check2-circle me-1"></i> Confirmar agendamento
      </button>
    </div>
  </form>

</div>
@endsection

@section('footer')
<script src="{{ mix('js/public-booking.js') }}"></script>
@endsection
