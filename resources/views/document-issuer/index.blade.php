@extends('layouts.app')
@section('title', 'Emissor')
@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            @include('components.icon-document-issuer', ['css' => 'me-2']) Emissor
        </h3>
    </div>

    <div class="row">
        <div class="col-12 mb-4 col-sm-auto">
            <a href="{{ route('patients.create') }}" class="btn btn-primary w-100">
                <i class="fa-solid fa-plus me-2"></i> Emitir Documento
            </a>
        </div>

        <div class="col-12 mb-4 col-sm-auto">
            @include('components.filter')
        </div>

        <div class="col-12 mb-4 col-sm-auto">
            <a class="btn btn-primary w-100" onclick="paginateDocumentIssuer()">
                <i class="fas fa-sync me-3"></i> Atualizar
            </a>
        </div>
    </div>

    <div class="row">
        <div class="list-paginate"></div>
    </div>

    <!-- Modal Filter -->
    <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="filterModalLabel">Filtrar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="form-search" autocomplete="off">
                        <div class="row">
                            <div class="col-lg-12 col-sm-12">
                                <div class="form-group">
                                    <label>Pesquisar por Nome, CPF ou Whatsapp: </label>
                                    <input class="form-control" name="q" type="text">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="clearFiltersPatients()">Limpar</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="paginateDocumentIssuer()">Buscar</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@section('footer')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            paginateDocumentIssuer();
        });
        document.getElementById('form-search').addEventListener('submit', function (e) {
            e.preventDefault();
            paginateDocumentIssuer();
        });
    </script>
@endsection

