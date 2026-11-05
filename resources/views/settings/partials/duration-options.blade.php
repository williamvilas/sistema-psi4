@php
    // Duração em minutos → valor H:i esperado pelo backend; um valor salvo fora da lista é mantido como opção.
    // Opções em botões (radios) em vez de <select>: todas visíveis, sem depender do popup nativo do navegador.
    $zeroLabel ??= null;
    $toTime =fn (int $minutes) => sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    $toLabel = function (int $minutes) use ($zeroLabel) {
        if ($minutes === 0) {
            return $zeroLabel ?? '0 min';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return trim(($hours ? $hours.' h ' : '').($rest ? $rest.' min' : ''));
    };

    [$h, $m] = array_map('intval', explode(':', $value) + [0, 0]);
    $current = $h * 60 + $m;
    $options = collect($options)->push($current)->unique()->sort()->values();
@endphp
<div class="cfg-choices" role="radiogroup" aria-labelledby="{{ $id }}Label">
    @foreach ($options as $minutes)
        <input type="radio" class="btn-check" id="{{ $id }}{{ $minutes }}" name="{{ $name }}" value="{{ $toTime($minutes) }}" autocomplete="off" @checked($minutes === $current)>
        <label class="cfg-choice" for="{{ $id }}{{ $minutes }}">{{ $toLabel($minutes) }}</label>
    @endforeach
</div>
