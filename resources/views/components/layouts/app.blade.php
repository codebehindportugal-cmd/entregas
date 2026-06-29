<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Horta da Maria' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f0fdf4] text-[#1a2e05] antialiased">
@auth
    @php
        $icons = [
            'home' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
            'route' => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7',
            'check' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
            'bag' => 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
            'list' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 12h6m-6 4h6',
            'leaf' => 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 004 0 2 2 0 012-2h1.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            'chart' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
            'grid' => 'M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
            'cart' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
            'receipt' => 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z',
            'file' => 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2zm0 0V9m5-6v6h6',
            'users' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z',
            'building' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
            'cog' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
        ];

        $navGroups = auth()->user()->isAdmin()
            ? [
                ['label' => 'Inicio', 'items' => [
                    ['label' => 'Painel', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => $icons['home']],
                ]],
                ['label' => 'Entregas', 'items' => [
                    ['label' => 'Rotas', 'route' => 'entregas.index', 'active' => 'entregas.index', 'icon' => $icons['route']],
                    ['label' => 'Preparacao', 'route' => 'preparacao.index', 'active' => 'preparacao.*', 'icon' => $icons['bag']],
                    ['label' => 'Verificacao', 'route' => 'entregas.verificacao', 'active' => 'entregas.verificacao', 'icon' => $icons['check']],
                ]],
                ['label' => 'Compras e faturas', 'items' => [
                    ['label' => 'Entradas', 'route' => 'despesas.index', 'active' => 'despesas.*', 'icon' => $icons['receipt']],
                    ['label' => 'Faturas OCR', 'route' => 'invoices.index', 'active' => 'invoices.*', 'icon' => $icons['file']],
                    ['label' => 'Compras', 'route' => 'compras.index', 'active' => 'compras.*', 'icon' => $icons['cart']],
                    ['label' => 'Produtos', 'route' => 'produtos.index', 'active' => 'produtos.*', 'icon' => $icons['grid']],
                ]],
                ['label' => 'Cabazes', 'items' => [
                    ['label' => 'Listas semanais', 'route' => 'lista-cabazes.index', 'active' => 'lista-cabazes.*', 'icon' => $icons['list']],
                    ['label' => 'Sazonalidade', 'route' => 'sazonalidade.index', 'active' => 'sazonalidade.*', 'icon' => $icons['leaf']],
                    ['label' => 'Margens', 'route' => 'comparacao-cabazes.index', 'active' => 'comparacao-cabazes.*', 'icon' => $icons['chart']],
                ]],
                ['label' => 'Clientes', 'items' => [
                    ['label' => 'B2C', 'route' => 'encomendas.index', 'active' => 'encomendas.*', 'icon' => $icons['users']],
                    ['label' => 'Empresas', 'route' => 'corporates.index', 'active' => 'corporates.*', 'icon' => $icons['building']],
                    ['label' => 'Equipa', 'route' => 'equipa.index', 'active' => 'equipa.*', 'icon' => $icons['users']],
                ]],
                ['label' => 'Administracao', 'items' => [
                    ['label' => 'Sistema', 'route' => 'operations.index', 'active' => 'operations.*', 'icon' => $icons['cog']],
                ]],
            ]
            : [
                ['label' => null, 'items' => [
                    ['label' => 'Rota de hoje', 'route' => 'minhas-entregas.index', 'active' => 'minhas-entregas.*', 'icon' => $icons['route']],
                ]],
            ];

        $navItems = collect($navGroups)->flatMap(fn ($group) => $group['items'])->values()->all();
        $activeNavItem = collect($navItems)->first(fn ($item) => request()->routeIs($item['active'])) ?? $navItems[0];
    @endphp

    <aside class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col bg-[#14532d] lg:flex">
        <a href="{{ auth()->user()->isAdmin() ? route('dashboard') : route('minhas-entregas.index') }}"
           class="flex h-16 shrink-0 items-center gap-3 border-b border-[#166534] px-5 transition-colors hover:bg-[#166534]">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#f0fdf4]/10 ring-1 ring-[#f0fdf4]/20">
                <img src="{{ asset('images/horta-da-maria-logo.png') }}" alt="Horta da Maria" class="h-7 w-7 object-contain">
            </span>
            <div>
                <p class="text-sm font-bold leading-tight" style="font-family: Poppins, sans-serif; color: #f0fdf4">Horta da Maria</p>
                <p class="text-[10px] leading-tight" style="color: #86efac">Gestao agricola</p>
            </div>
        </a>

        <nav class="min-h-0 flex-1 space-y-5 overflow-y-auto px-3 py-4">
            @foreach($navGroups as $group)
                <div>
                    @if($group['label'])
                        <p class="mb-1 px-2 text-[9px] font-bold uppercase tracking-widest" style="color: #4ade80">{{ $group['label'] }}</p>
                    @endif
                    <div class="space-y-0.5">
                        @foreach($group['items'] as $item)
                            @php($isActive = request()->routeIs($item['active']))
                            <a href="{{ route($item['route']) }}"
                               class="group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-semibold transition-all {{ $isActive ? 'bg-[#f0fdf4]/15 shadow-sm' : 'hover:bg-[#166534]' }}">
                                <svg class="h-4.5 w-4.5 shrink-0 transition-colors" style="width:18px;height:18px;color:{{ $isActive ? '#4ade80' : '#86efac' }}" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}"/>
                                </svg>
                                <span style="color: {{ $isActive ? '#f0fdf4' : '#bbf7d0' }}; font-family: Nunito, sans-serif">{{ $item['label'] }}</span>
                                @if($isActive)
                                    <span class="ml-auto h-1.5 w-1.5 rounded-full" style="background:#4ade80"></span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        <div class="shrink-0 border-t border-[#166534] p-3">
            <div class="flex items-center gap-2.5 rounded-lg px-2 py-2">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold" style="background:#166534;color:#4ade80;font-family:Poppins,sans-serif">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold leading-tight" style="color:#f0fdf4">{{ auth()->user()->name }}</p>
                    <p class="truncate text-[10px] capitalize" style="color:#86efac">{{ auth()->user()->role }}</p>
                </div>
            </div>
            <form method="post" action="{{ route('logout') }}" class="mt-1">
                @csrf
                <button type="submit" class="w-full rounded-lg py-1.5 text-xs font-semibold transition-colors hover:bg-[#166534]" style="color:#86efac">
                    Sair da sessao
                </button>
            </form>
        </div>
    </aside>

    <div class="topbar-desktop hidden lg:fixed lg:left-64 lg:right-0 lg:top-0 lg:z-30 lg:flex lg:h-14 lg:items-center lg:justify-between lg:border-b lg:border-slate-100 lg:bg-white lg:px-8" style="box-shadow:0 1px 0 #e2e8f0">
        <p class="text-sm text-slate-400" style="font-family:Nunito,sans-serif">{{ now()->translatedFormat('l, d \d\e F') }}</p>
        <div class="flex items-center gap-3">
            <span class="text-sm font-semibold text-slate-700">{{ auth()->user()->name }}</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold" style="background:#15803d;color:#f0fdf4;font-family:Poppins,sans-serif">
                {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
            </div>
        </div>
    </div>

    <header class="mobile-app-header sticky top-0 z-30 border-b border-[#14532d]/10 bg-white shadow-sm lg:hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
            <a href="{{ auth()->user()->isAdmin() ? route('dashboard') : route('minhas-entregas.index') }}" class="flex items-center gap-2.5">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg" style="background:#14532d">
                    <img src="{{ asset('images/horta-da-maria-logo.png') }}" alt="Horta da Maria" class="h-6 w-6 object-contain">
                </span>
                <span class="text-sm font-bold text-[#14532d]" style="font-family:Poppins,sans-serif">Horta da Maria</span>
            </a>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50" type="submit">Sair</button>
            </form>
            <details class="mobile-menu w-full">
                <summary class="mobile-menu-summary">
                    <span>Menu</span>
                    <strong>{{ $activeNavItem['label'] }}</strong>
                </summary>
                <nav class="mobile-menu-panel">
                    @foreach($navItems as $item)
                        <a class="{{ request()->routeIs($item['active']) ? 'is-active' : '' }}" href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
                    @endforeach
                </nav>
            </details>
        </div>
    </header>
@endauth

<main class="min-h-screen px-4 py-6 lg:ml-64 lg:px-8 lg:pt-20 lg:pb-10">
    <div class="mx-auto max-w-7xl">
        @if(session('status'))
            <div class="mb-6 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm">
                <svg class="h-4 w-4 shrink-0 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700 shadow-sm">
                <svg class="h-4 w-4 shrink-0 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ $errors->first() }}
            </div>
        @endif

        {{ $slot }}
    </div>
</main>
</body>
</html>
