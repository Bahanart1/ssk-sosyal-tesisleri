<!DOCTYPE html>
<html lang="tr">
<head>
    @include('partials.head', ['title' => $title ?? 'Üye Paneli'])
</head>
<body class="min-h-screen bg-canvas" x-data="{ menuOpen: false }">
    <x-toast />

    @php
        $nav = [
            ['route' => 'customer.dashboard', 'label' => 'Ana Sayfa', 'pattern' => 'customer.dashboard'],
            ['route' => 'customer.reservations.index', 'label' => 'Rezervasyonlarım', 'pattern' => 'customer.reservations.*'],
            ['route' => 'customer.dues.index', 'label' => 'Aidatlarım', 'pattern' => 'customer.dues.*'],
            ['route' => 'customer.profile.edit', 'label' => 'Hesabım', 'pattern' => 'customer.profile.*'],
        ];
        $member = auth()->user();
    @endphp

    <header class="sticky top-0 z-30 border-b border-line bg-surface">
        <div class="mx-auto max-w-6xl px-4 sm:px-6">
            {{-- Üst satır --}}
            <div class="flex items-center justify-between py-3">
                <a href="{{ route('customer.dashboard') }}" class="flex items-center gap-3" aria-label="Ana sayfa">
                    <x-brand-logo class="h-9 w-auto text-ink" />
                </a>

                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-medium text-ink">{{ $member->name }}</p>
                        <p class="text-xs text-ink-muted">
                            {{ $member->customerGroup?->name ?? 'Grup atanmadı' }}
                            @if ($member->membership_no) · {{ $member->membership_no }} @endif
                        </p>
                    </div>
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-accent-100 text-sm font-semibold text-accent-700 dark:bg-accent-900/40 dark:text-accent-200">
                        {{ mb_substr($member->name, 0, 1) }}
                    </div>

                    @include('partials.theme-toggle')

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-surface-sunken hover:text-ink" title="Çıkış Yap" aria-label="Çıkış Yap">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" /></svg>
                        </button>
                    </form>
                </div>
            </div>

            {{-- Gezinme --}}
            <nav class="-mb-px flex gap-1 overflow-x-auto" aria-label="Üye paneli">
                @foreach ($nav as $item)
                    @php $active = request()->routeIs($item['pattern']); @endphp
                    <a href="{{ route($item['route']) }}"
                       class="whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium transition-colors
                              {{ $active
                                    ? 'border-accent-600 text-accent-700 dark:border-accent-400 dark:text-accent-300'
                                    : 'border-transparent text-ink-muted hover:border-line hover:text-ink' }}"
                       @if ($active) aria-current="page" @endif>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-6xl px-4 pb-10 sm:px-6">
        <p class="border-t border-line pt-6 text-center text-xs leading-relaxed text-ink-subtle">
            Tesislerden yararlanma koşulları, Dernek Yönetim Kurulunca belirlenen
            <span class="font-medium text-ink-muted">Kamp Konaklama Usul ve Esasları</span>'na tabidir.
        </p>
    </footer>
</body>
</html>
