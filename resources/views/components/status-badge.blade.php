@props(['status'])

@php
    $map = [
        'pending' => ['badge-amber', 'Onay Bekliyor'],
        'approved' => ['badge-teal', 'Onaylandı'],
        'rejected' => ['badge-red', 'Reddedildi'],
        'paid' => ['badge-green', 'Ödendi'],
        'cancelled' => ['badge-gray', 'İptal Edildi'],
    ];
    [$class, $label] = $map[$status] ?? ['badge-gray', $status];
@endphp

<span {{ $attributes->merge(['class' => $class]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80" aria-hidden="true"></span>
    {{ $label }}
</span>
