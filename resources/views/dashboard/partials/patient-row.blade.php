<div class="dash-row">
    @include('components.avatar-initials', ['name' => $name])
    <div class="dash-row-body">
        <a href="{{ $href }}" class="dash-row-name">{{ $name }}</a>
        <div class="dash-row-detail">{{ $detail }}</div>
    </div>
    <a href="{{ route('whatsapp.send', ['phone' => $phone]) }}" target="_blank" class="dash-round-btn dash-round-btn-success" title="{{ $whatsappTitle }}"><i class="fab fa-whatsapp"></i></a>
</div>
