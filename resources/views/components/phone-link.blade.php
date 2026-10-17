@props(['phone'])

@php
    $display = trim((string) $phone);
    $tel = preg_replace('/[^\d+]/', '', $display) ?? '';

    if (str_starts_with($tel, '00')) {
        $tel = '+'.substr($tel, 2);
    }
@endphp

@if ($display !== '' && $tel !== '')
    <a href="tel:{{ $tel }}">{{ $display }}</a>
@else
    {{ $display }}
@endif
