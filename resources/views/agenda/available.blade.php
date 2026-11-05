@if(empty($times))
    <div class="alert alert-warning mb-0 mx-2">Nenhum horário disponível para esta data. Verifique os dias e horários configurados na sua agenda.</div>
@else
    {{-- data-types: modalidades aceitas no horário; agenda.js esconde os que não servem para a modalidade escolhida --}}
    <div class="row g-3 p-10px">
        @foreach(array_keys($times) as $i => $timer)
            <div class="col-lg-3 col-sm-6 col-12 available-timer" data-types="{{ implode(' ', $times[$timer]) }}">
                <input type="radio" class="btn-check" name="timer[]" value="{{ $timer }}" id="timer_{{ $i }}" autocomplete="off">
                <label class="card b-timer mb-0 p-2 text-center shadow-sm" for="timer_{{ $i }}" style="cursor:pointer;">
                    <h5 class="m-0">{{ $timer }}</h5>
                </label>
            </div>
        @endforeach
    </div>
    <div class="alert alert-warning mb-0 mx-2 d-none available-timer-empty">Nenhum horário disponível nesta modalidade para esta data.</div>
@endif
