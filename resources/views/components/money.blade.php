@props(['value' => 0, 'zero' => null])

@php
    $amount = (float) $value;
    $decimals = fmod($amount, 1) === 0.0 ? 0 : 2;
@endphp

<span {{ $attributes }}>@if ($zero !== null && $amount == 0){{ $zero }}@else₺{{ number_format($amount, $decimals, ',', '.') }}@endif</span>
