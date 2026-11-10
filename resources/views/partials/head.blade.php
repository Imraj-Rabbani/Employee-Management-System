<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

{{-- The sidebar's saved width / closed state (resources/js/app.js), applied before the first paint so it does not jump. --}}
<script>
    try {
        const w = localStorage.getItem('ems.sidebar.width');
        if (w) document.documentElement.style.setProperty('--sidebar-w', w + 'px');
        if (localStorage.getItem('ems.sidebar.closed') === '1') document.documentElement.classList.add('sidebar-closed');
    } catch (e) {}
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
