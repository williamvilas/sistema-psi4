<button type="button" class="btn {{ $css ?? 'btn-primary' }} position-relative mr-2 w-100" data-bs-toggle="modal" data-bs-target="#filterModal">
    <i class="fa-solid fa-filter me-2"></i>
    Filtrar
    <span class="position-absolute top-0 start-100 btn btn-space mb-0 btn-warning translate-middle badge rounded-pill span-filter d-none">
        <span class="total-filter"></span>
        <span class="visually-hidden">filtros</span>
    </span>
</button>
