<!DOCTYPE html>
<html lang="tr">
<head>
    @include('partials.head', ['title' => $title ?? 'Üye Paneli'])
</head>
<body class="min-h-screen bg-canvas" x-data="{ menuOpen: false }">
    <x-toast />

    @php
        $nav = [
            ['route' => 'customer.dashboard', 'label' => 'Panelim', 'pattern' => 'customer.dashboard'],
            ['route' => 'customer.reservations.index', 'label' => 'Başvurularım', 'pattern' => 'customer.reservations.*'],
            ['route' => 'customer.dues.index', 'label' => 'Aidatlarım', 'pattern' => 'customer.dues.*'],
            ['route' => 'customer.profile.edit', 'label' => 'Hesabım', 'pattern' => 'customer.profile.*'],
        ];
        $member = auth()->user();
    @endphp

    <header class="sticky top-0 z-30 border-b border-line bg-surface">
        <div class="mx-auto max-w-5xl px-4 sm:px-6">
            {{-- Üst satır --}}
            <div class="flex items-center justify-between py-3">
                <a href="{{ route('customer.dashboard') }}" class="flex items-center gap-2.5">
                    <div class="brand-mark-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.5 1.5 0 0 1 2.122 0l8.954 8.955M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" /></svg>
                    </div>
                    <div class="leading-tight">
                        <p class="text-sm font-semibold tracking-tight text-ink">SSK Sosyal Tesisleri</p>
                        <p class="hidden text-[11px] text-ink-muted sm:block">Üye paneli</p>
                    </div>
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

    <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        {{ $slot }}
    </main>

    <footer class="mx-auto max-w-5xl px-4 pb-10 sm:px-6">
        <p class="border-t border-line pt-6 text-center text-xs leading-relaxed text-ink-subtle">
            Tesislerden yararlanma koşulları, Dernek Yönetim Kurulunca belirlenen
            <span class="font-medium text-ink-muted">Kamp Konaklama Usul ve Esasları</span>'na tabidir.
        </p>
    </footer>
</body>
</html>
