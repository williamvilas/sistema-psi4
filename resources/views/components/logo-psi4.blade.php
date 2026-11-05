@props([
    'accent' => '#6fcfc0',
    'ink' => '#1b323c',
    'text' => '#e6e6e6',
    'size' => 36,
    'icon' => false,
])

<span {{ $attributes->merge(['class' => 'inline-flex items-center']) }} role="img" aria-label="PSI4" style="gap: {{ $size * 0.28 }}px; font-family: 'Sora', sans-serif;">
    @if($icon)
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 100 100" role="img" aria-label="Símbolo PSI4: letra psi dentro de um balão de fala">
        <rect x="6" y="6" width="88" height="74" rx="24" fill="{{ $accent }}"></rect>
        <path d="M22 68 L18 94 L46 78 Z" fill="{{ $accent }}"></path>
        <g fill="none" stroke="{{ $ink }}" stroke-width="7.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M34 24 V36 C34 50 40 56 50 56 C60 56 66 50 66 36 V24"></path>
            <path d="M50 18 V70"></path>
        </g>
    </svg>
    @endif
    <span style="font-family: 'Sora', sans-serif; font-size: {{ $size * 0.95 }}px; font-weight: 700; line-height: 1; letter-spacing: -0.045em; color: {{ $text }};">psi<span style="color: {{ $accent }};">4</span></span>
</span>
