@extends('layouts.app')
@section('title', 'Documento Não Encontrado')

@section('header')
    <style>
        .container-custom h1 {
            font-size: 4.5rem;
            font-weight: 700;
            color: #dc2626;
            margin-bottom: 1rem;
        }

        .container-custom h2 {
            font-size: 1.9rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .container-custom p {
            color: #4b5563;
            margin-bottom: 1.6rem;
            font-size: 1.4rem;
        }

        /* Overrides do Bootstrap */
        .container-custom .text-red-600 {
            color: #dc2626 !important;
        }
    </style>
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            @include('components.icon-documents', ['css' => 'me-2']) Documento
        </h3>
    </div>

    <div class="row justify-content-center">
        <div class="col-12">
            <div class="container-custom text-center">
                <h1 class="text-red-600">404</h1>
                <h2>Documento não encontrado</h2>
                <p class="mb-sm-0">
                    O documento que você está tentando acessar não está disponível.
                </p>
                <p>
                    Ele pode ter sido removido ou você pode não ter permissão para visualizá-lo.
                </p>
            </div>
        </div>
    </div>

@endsection

@section('footer')
    <script>

    </script>
@endsection

