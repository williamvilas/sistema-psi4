<div class="photo-side">
  <img
    src="{{ $profile['photo'] }}"
    alt="{{ $profile['name'] }}"
    onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 200 200%22><rect width=%22200%22 height=%22200%22 fill=%22%23ffffff22%22/><circle cx=%22100%22 cy=%2285%22 r=%2240%22 fill=%22%23ffffff88%22/><path d=%22M30 200c0-40 30-70 70-70s70 30 70 70z%22 fill=%22%23ffffff88%22/></svg>';">

  <h1>{{ $profile['name'] }}</h1>
  <p class="crp">
    Psicólogo(a){{ $profile['crp'] ? ' · '.$profile['crp'] : '' }}
    @if($profile['clinic_name'])<br>{{ $profile['clinic_name'] }}@endif
  </p>

  @if(!empty($profile['schedule']))
  <div class="info">
      <div class="mt-3"><i class="bi bi-clock"></i>{!! implode('<br>', array_map('e', $profile['schedule'])) !!}</div>
  </div>
  @endif

  @if(!empty($profile['in_person']) && !empty($profile['address']))
  <div class="info">
      <div class="mt-3">
        <span class="info-label"><i class="bi bi-geo-alt"></i>Atendimento presencial</span>
        @if(!empty($profile['maps_url']))
          <a href="{{ $profile['maps_url'] }}" class="maps-link" target="_blank" rel="noopener noreferrer" aria-label="Ver rota até o consultório no Google Maps (abre em nova aba)">
            {!! implode('<br>', array_map('e', $profile['address'])) !!}
            <span class="maps-cta">Ver rota no Google Maps <i class="bi bi-box-arrow-up-right"></i></span>
          </a>
        @else
          {!! implode('<br>', array_map('e', $profile['address'])) !!}
        @endif
      </div>
  </div>
  @endif
</div>
