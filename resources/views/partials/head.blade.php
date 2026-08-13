<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'Panel' }} · SSK Sosyal Tesisleri</title>

{{--
    Tema, boyamadan önce belirlenir; aksi halde sayfa bir an açık modda yanıp söner.
    Kullanıcı bir tercih kaydetmediyse işletim sistemi ayarı izlenir.
--}}
<script>
    (function () {
        try {
            var stored = localStorage.getItem('theme');
            var dark = stored ? stored === 'dark'
                : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        } catch (e) {}
    })();
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
