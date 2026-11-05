@extends('layouts.app')
@section('title', 'Cadastrar Documento')

@section('header')
    <link href="{{ mix('css/edit-text.css') }}" rel="stylesheet">
@endsection

@section('content')

    <div class="page-header">
        <h3 class="fw-bold mb-3 title-page-header">
            @include('components.icon-documents', ['css' => 'me-2']) Cadastrar Documento
        </h3>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-md-5">
            <div class="card">
                <div class="card-body">

                    <form id="form-store-patients">
                        <div class="row">
                            <div class="col-md-12 col-12">
                                <div class="form-group">
                                    <label for="name">Nome <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="name" class="form-control" required/>
                                </div>
                            </div>

                            <div class="col-md-12 col-12">
                                <div class="form-group">
                                    @include('components.edit-text')
                                </div>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>

@endsection

@section('footer')
    <script src="{{ mix('js/edit-text.js') }}" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initOnlyDatePickers();
        });
    </script>
@endsection

