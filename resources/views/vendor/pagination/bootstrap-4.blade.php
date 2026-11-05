@if($meta['pagination']['total'] > 0)
    <nav aria-label="Page navigation example">
        <ul class="pagination justify-content-center">

            {{-- Cálculo do intervalo de páginas --}}
            @php
                $url = request()->fullUrl();

                if (!request()->query('page')) {
                    $separator = strpos($url, '?') === false ? '?' : '&';
                    $url .= $separator . 'page=' . 1;
                }

                $start = max(1, $meta['pagination']['current_page'] - 2);
                $end = min($meta['pagination']['total_pages'], $meta['pagination']['current_page'] + 2);

                // Ajuste o intervalo para garantir que sempre mostre até 5 páginas, quando possível
                if ($meta['pagination']['current_page'] <= 3) {
                    $end = min(5, $meta['pagination']['total_pages']);
                } elseif ($meta['pagination']['current_page'] > $meta['pagination']['total_pages'] - 3) {
                    $start = max(1, $meta['pagination']['total_pages'] - 4);
                }
            @endphp

            {{-- Links das páginas com limite de 5 --}}
            @for ($page = $start; $page <= $end; $page++)
                <li class="page-item {{ $page == $meta['pagination']['current_page'] ? 'active' : '' }}">
                    @php($url = preg_replace('/(page=)\d+/', '${1}' . $page, $url))
                    <a class="page-link cursor-pointer" onclick="listStore('{{ $url }}')">{{ $page }}</a>
                </li>
            @endfor

        </ul>
    </nav>
@endif
