@extends('layouts.app')
@section('title', 'Autorizar sessão')

@section('header')
    @include('components.css-dash')
@endsection

@section('content')
    <div class="dash-card p-4 mx-auto" style="max-width: 560px;">
        @if($authorization === null)
            <h4 class="mb-2">Pedido indisponível</h4>
            <p class="text-muted mb-0">Este pedido venceu ou não é da sua conta. Volte para a chamada e peça de novo.</p>
        @else
            <div id="videoRoomAuthorization" @class(['d-none' => $authorization['approved']])>
                <h4 class="mb-2">Autorizar esta sessão?</h4>
                <p class="mb-1"><strong>{{ $authorization['patient'] }}</strong></p>
                <p class="text-muted">{{ \Carbon\Carbon::createFromTimestamp($authorization['scheduled_on'])->timezone(\App\Helpers\UserHelper::getTimezone())->format('d/m/Y H:i') }}</p>
                <p class="text-muted">A chamada poderá guardar dinâmicas e transcrição cifradas até o prazo desta sala. Ela não consegue ler nada. Se não foi você quem pediu, feche esta página.</p>
                <button type="button" class="btn btn-primary" id="approveVideoRoomAuthorization"
                        data-id="{{ $authorization['id'] }}" data-public-key="{{ $authorization['server_public_key'] }}" data-info="{{ $authorization['info'] }}">
                    <i class="fas fa-lock-open me-1"></i> Autorizar
                </button>
            </div>
            <div id="videoRoomAuthorizationDone" @class(['d-none' => ! $authorization['approved']])>
                <h4 class="mb-2">Sessão autorizada</h4>
                <p class="text-muted mb-0">Pode voltar para a chamada e fechar esta aba.</p>
            </div>
        @endif
    </div>
@endsection
