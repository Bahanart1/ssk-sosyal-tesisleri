@props(['status'])

@php
    $map = [
        'pending' => ['badge-amber', 'İnceleniyor'],
        'approved' => ['badge-teal', 'Yer Tahsis Edildi'],
        'paid' => ['badge-green', 'Ödendi'],
        'rejected' => ['badge-red', 'Reddedildi'],
        'cancelled' => ['badge-gray', 'İptal Edildi'],

        // Ödeme durumları
        'verified' => ['badge-green', 'Doğrulandı'],
        'failed' => ['badge-red', 'Başarısız'],
        'refunded' => ['badge-gray', 'İade Edildi'],
        'success' => ['badge-green', 'Onaylandı'],
    ];
    [$class, $label] = $map[$status] ?? ['badge-gray', $status];
@endphp

<span {{ $attributes->merge(['class' => $class]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80" aria-hidden="true"></span>
    {{ $label }}
</span>
