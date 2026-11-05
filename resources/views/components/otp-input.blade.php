{{-- Campo de código de 6 dígitos (ativação do 2FA e verificação no login). Comportamento em resources/js/otp-input.js. --}}
@props(['id', 'label' => 'Código de 6 dígitos'])

@once
    <style>
        .tf-otp { display: flex; gap: .5rem; }
        .tf-otp input {
            flex: 1 1 0; min-width: 0; max-width: 52px; height: 60px; padding: 0;
            text-align: center; font-size: 1.6rem; font-weight: 700; color: var(--dash-ink, #1e2a3e);
            border: 2px solid #adb4bd; border-radius: 14px; background: #fff; transition: border-color .15s, box-shadow .15s;
        }
        .tf-otp input:focus { outline: 0; border-color: var(--dash-primary, #1f7a80); box-shadow: 0 0 0 .2rem var(--dash-primary-soft, #e3f1f2); }
        .tf-otp.is-invalid input { border-color: #d64545; }
        .tf-otp-sep { align-self: center; color: #b8c9cc; font-weight: 700; }
        .tf-otp-error { color: #d64545; font-weight: 600; margin-top: .75rem; }
        .tf-otp.is-shake { animation: tf-shake .35s; }
        @keyframes tf-shake { 25% { transform: translateX(-6px); } 75% { transform: translateX(6px); } }
        @media (max-width: 575.98px) {
            .tf-otp { gap: .35rem; }
            .tf-otp input { height: 52px; font-size: 1.35rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            .tf-otp.is-shake { animation: none; }
        }
    </style>
@endonce

<div {{ $attributes->merge(['class' => 'tf-otp']) }} id="{{ $id }}" role="group" aria-label="{{ $label }}">
    @for($i = 1; $i <= 6; $i++)
        @if($i === 1)
            <input type="text" inputmode="numeric" maxlength="6" pattern="[0-9]*"
                   autocomplete="one-time-code" aria-label="Dígito {{ $i }}">
        @else
            <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*"
                   autocomplete="off" data-lpignore="true" data-1p-ignore data-bwignore aria-label="Dígito {{ $i }}">
        @endif
        @if($i === 3)<span class="tf-otp-sep" aria-hidden="true">–</span>@endif
    @endfor
</div>
<div class="tf-otp-error d-none" id="{{ $id }}-error" role="alert"></div>
