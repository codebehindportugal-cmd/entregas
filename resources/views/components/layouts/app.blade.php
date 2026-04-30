<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Entregas' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#07110D] text-slate-100 antialiased">
    <div class="min-h-screen bg-[radial-gradient(circle_at_top_left,rgba(34,197,94,0.14),transparent_28rem),linear-gradient(180deg,#07110D_0%,#0A0F1A_48%,#0A0F1A_100%)]">
        @auth
            @php
                $navItems = auth()->user()->isAdmin()
                    ? [
                        ['label' => 'Painel', 'route' => 'dashboard', 'active' => 'dashboard'],
                        ['label' => 'Rotas', 'route' => 'entregas.index', 'active' => 'entregas.index'],
                        ['label' => 'Estado das entregas', 'route' => 'entregas.verificacao', 'active' => 'entregas.verificacao'],
                        ['label' => 'Preparar cabazes', 'route' => 'preparacao.index', 'active' => 'preparacao.*'],
                        ['label' => 'Compras', 'route' => 'compras.index', 'active' => 'compras.*'],
                        ['label' => 'Clientes B2C', 'route' => 'encomendas.index', 'active' => 'encomendas.*'],
                        ['label' => 'Empresas', 'route' => 'corporates.index', 'active' => 'corporates.*'],
                        ['label' => 'Equipa', 'route' => 'equipa.index', 'active' => 'equipa.*'],
                    ]
                    : [
                        ['label' => 'Rota de hoje', 'route' => 'minhas-entregas.index', 'active' => 'minhas-entregas.*'],
                    ];
            @endphp

            <aside class="fixed inset-y-0 left-0 z-40 hidden w-72 border-r border-white/10 bg-[#08120F]/90 p-4 shadow-2xl shadow-black/30 backdrop-blur-xl lg:block">
                <a href="{{ auth()->user()->isAdmin() ? route('dashboard') : route('minhas-entregas.index') }}" class="flex items-center gap-3 rounded border border-white/10 bg-white/[0.03] p-3">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded bg-white p-2 shadow-sm">
                        <img src="{{ asset('images/horta-da-maria-logo.png') }}" alt="Horta da Maria" class="h-full w-full object-contain">
                    </span>
                    <span>
                        <span class="block text-base font-semibold text-white">Horta da Maria</span>
                        <span class="block text-xs text-emerald-200/80">Operacoes de entrega</span>
                    </span>
                </a>

                <nav class="mt-6 space-y-1">
                    @foreach($navItems as $item)
                        <a href="{{ route($item['route']) }}" class="group flex items-center justify-between rounded px-3 py-2.5 text-sm font-medium {{ request()->routeIs($item['active']) ? 'bg-[#22C55E] text-[#07110D] shadow-lg shadow-emerald-950/20' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
                            <span>{{ $item['label'] }}</span>
                            @if(request()->routeIs($item['active']))
                                <span class="h-2 w-2 rounded-full bg-[#07110D]"></span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                <div class="absolute bottom-4 left-4 right-4 rounded border border-white/10 bg-white/[0.03] p-3">
                    <p class="text-sm font-semibold text-white">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-slate-400">{{ auth()->user()->role }}</p>
                    <form method="post" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button class="w-full rounded border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200 hover:bg-white/10" type="submit">Sair</button>
                    </form>
                </div>
            </aside>

            <header class="sticky top-0 z-30 border-b border-white/10 bg-[#08120F]/95 shadow-lg shadow-black/20 backdrop-blur lg:hidden">
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <a href="{{ auth()->user()->isAdmin() ? route('dashboard') : route('minhas-entregas.index') }}" class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded bg-white p-1.5">
                            <img src="{{ asset('images/horta-da-maria-logo.png') }}" alt="Horta da Maria" class="h-full w-full object-contain">
                        </span>
                        <span class="text-sm font-semibold text-white">Horta da Maria</span>
                    </a>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded border border-white/10 bg-white/5 px-3 py-2 text-sm hover:bg-white/10" type="submit">Sair</button>
                    </form>
                    <nav class="flex w-full gap-1 overflow-x-auto pb-1 text-sm text-slate-300">
                        @foreach($navItems as $item)
                            <a class="shrink-0 rounded px-3 py-2 {{ request()->routeIs($item['active']) ? 'bg-[#22C55E] text-[#07110D]' : 'bg-white/5' }}" href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
                        @endforeach
                    </nav>
                </div>
            </header>
        @endauth

        <main class="px-4 py-6 lg:ml-72 lg:px-8 lg:py-8">
            <div class="mx-auto max-w-7xl">
                @if(session('status'))
                    <div class="mb-6 rounded border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100 shadow-sm shadow-emerald-950/20">{{ session('status') }}</div>
                @endif

                @if($errors->any())
                    <div class="mb-6 rounded border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm text-red-200 shadow-sm shadow-red-950/20">
                        {{ $errors->first() }}
                    </div>
                @endif

                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
