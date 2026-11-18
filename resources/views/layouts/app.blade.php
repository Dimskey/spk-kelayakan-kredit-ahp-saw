<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'SPK Kredit B2B (AHP-SAW)'))</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas text-ink antialiased selection:bg-zinc-900 selection:text-white">
    <div class="min-h-screen flex flex-col">
        <header class="sticky top-0 z-30 bg-surface border-b border-hairline">
            <div class="max-w-[1480px] mx-auto px-4 sm:px-6 h-12 flex items-center justify-between gap-4">
                <div class="flex items-center gap-4 min-w-0">
                    <a href="{{ route('spk.dashboard') }}" class="flex items-center gap-2.5 text-sm font-semibold tracking-tight text-ink whitespace-nowrap">
                        <span class="inline-block w-2.5 h-2.5 bg-zinc-950 rounded-xs"></span>
                        <span>SPK KREDIT B2B</span>
                    </a>
                    <span class="text-zinc-300 select-none hidden sm:inline">/</span>
                    <nav class="flex items-center gap-1 text-xs font-medium overflow-x-auto">
                        <a href="{{ route('spk.dashboard') }}"
                           class="px-2.5 py-1.5 rounded-sm whitespace-nowrap transition-colors {{ request()->routeIs('spk.dashboard') ? 'bg-zinc-950 text-white' : 'text-muted hover:text-ink hover:bg-subtle' }}">
                            <span class="font-mono-tabular text-[11px] opacity-75 mr-1">01</span>Portfolio &amp; Drilldown Matrix
                        </a>
                        <a href="{{ route('spk.ahp.inspector') }}"
                           class="px-2.5 py-1.5 rounded-sm whitespace-nowrap transition-colors {{ request()->routeIs('spk.ahp.*') ? 'bg-zinc-950 text-white' : 'text-muted hover:text-ink hover:bg-subtle' }}">
                            <span class="font-mono-tabular text-[11px] opacity-75 mr-1">02</span>AHP Calculation Inspector
                        </a>
                    </nav>
                </div>

                <div class="flex items-center gap-2.5 shrink-0">
                    @yield('header_status')
                    @yield('nav_actions')
                </div>
            </div>
        </header>

        <main class="flex-1 max-w-[1480px] w-full mx-auto px-4 sm:px-6 py-5">
            @yield('content')
        </main>
    </div>
</body>
</html>
