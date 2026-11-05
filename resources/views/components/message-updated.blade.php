{{-- Toast de sucesso após redirect com ?m=<chave>; só exibe chaves de messages.redirect. --}}
@php($messageKey = request()->query('m'))
@if(is_string($messageKey) && Lang::has('messages.redirect.' . $messageKey))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            toastSuccess(@js(__('messages.redirect.' . $messageKey)));
            clearQueryParams('m');
        });
    </script>
@endif
