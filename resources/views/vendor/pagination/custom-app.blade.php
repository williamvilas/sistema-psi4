@php
    $current = $pagination['current_page'];
    $total = $pagination['total_pages'];

    $start = max(1, $current - 2);
    $end = min($total, $start + 4);

    // Ajuste o início se estiver no final
    if ($end - $start < 4) {
        $start = max(1, $end - 4);
    }
@endphp

@if ($total > 1)
    @php
        $query = request()->except('page'); // remove o "page"
        $base = request()->url(); // retorna a URL sem query string
    @endphp

    <nav>
        <div class="d-flex justify-content-center">
            <ul class="pagination">
                {{-- Botão anterior --}}
                @if ($current > 1)
                    <li class="page-item">
                        @php($queryString = http_build_query(array_merge($query, ['page' =>  $current - 1])))
                        <a class="page-link" href="{{ $base . '?' . $queryString }}">&laquo;</a>
                    </li>
                @else
                    <li class="page-item disabled"><span class="page-link">&laquo;</span></li>
                @endif

                {{-- Páginas --}}
                @for ($i = $start; $i <= $end; $i++)
                    @if ($i == $current)
                        <li class="page-item active"><span class="page-link">{{ $i }}</span></li>
                    @else
                        @php($queryString = http_build_query(array_merge($query, ['page' =>  $i])))
                        <li class="page-item"><a class="page-link" href="{{ $base . '?' . $queryString }}">{{ $i }}</a></li>
                    @endif
                @endfor

                {{-- Botão próximo --}}
                @if ($current < $total)
                    <li class="page-item">
                        @php($queryString = http_build_query(array_merge($query, ['page' => $current + 1])))
                        <a class="page-link" href="{{ $base . '?' . $queryString }}">&raquo;</a>
                    </li>
                @else
                    <li class="page-item disabled"><span class="page-link">&raquo;</span></li>
                @endif

            </ul>
        </div>
    </nav>
@endif
