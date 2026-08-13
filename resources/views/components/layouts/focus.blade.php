<!DOCTYPE html>
<html lang="tr">
<head>
    @include('partials.head', ['title' => $title ?? 'Ödeme'])
</head>
<body class="flex min-h-screen items-center justify-center bg-canvas px-4 py-10">
    <div class="w-full max-w-md">
        <div class="mb-6 flex items-center justify-center gap-2.5">
            <div class="brand-mark-sm">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.5 1.5 0 0 1 2.122 0l8.954 8.955M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" /></svg>
            </div>
            <p class="text-base font-semibold tracking-tight text-ink">SSK Sosyal Tesisleri</p>
        </div>

        {{ $slot }}
    </div>
</body>
</html>
