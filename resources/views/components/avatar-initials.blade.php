@php
    $parts = preg_split('/\s+/', trim($name));
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    $tone = crc32($name) % 6;
@endphp
<span class="dash-avatar dash-tone-{{ $tone }}">{{ $initials }}</span>
