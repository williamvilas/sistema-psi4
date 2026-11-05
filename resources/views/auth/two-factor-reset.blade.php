@extends('layouts.guest')
@section('title', 'Desativar 2 fatores')
@section('content')
    <div class="main-content container-fluid">
        <div class="splash-container">
            <div class="card card-border-color card-border-color-primary">
                <div class="card-header text-center">
                    <div class="mb-3 d-flex justify-content-center" aria-label="{{ config('app.name') }}">
                        <x-logo-psi4 :size="48" text="#1b323c" accent="#2fa595" />
                    </div>
                    <h2 class="login-title">Desativar autenticação de 2 fatores</h2>
                    <p class="splash-description">
                        Ao confirmar, a verificação por código será removida da sua conta. Depois, você poderá entrar apenas com e-mail e senha e ativar o 2FA novamente nas configurações.
                    </p>
                </div>
                <div class="card-body">
                    <form id="form-two-factor-reset" data-action="{{ $action }}">
                        <div class="form-group row login-submit">
                            <div class="col-12">
                                <button class="btn btn-primary btn-lg w-100" type="submit">Confirmar e desativar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
