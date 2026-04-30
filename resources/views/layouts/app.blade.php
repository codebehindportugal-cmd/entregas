<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Entregas' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#0A0F1A] text-slate-100 antialiased">
    <div class="min-h-screen">
        @auth
            <header class="border-b border-white/10 bg-[#151E2D]">
                <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-4">
                    <a href="{{ auth()->user()->isAdmin() ? route('dashboard') : route('minhas-entregas.index') }}" class="text-lg font-semibold text-white">Entregas</a>
                    <nav class="flex flex-wrap items-center gap-2 text-sm text-slate-300">
                        @if(auth()->user()->isAdmin())
                            <a class="rounded px-3 py-2 hover:bg-white/10" href="{{ route('dashboard') }}">Dashboard</a>
                            <a class="rounded px-3 py-2 hover:bg-white/10" href="{{ route('entregas.index') }}">Entregas</a>
                            <a class="rounded px-3 py-2 hover:bg-white/10" href="{{ route('corporates.index') }}">Empresas</a>
                            <a class="rounded px-3 py-2 hover:bg-white/10" href="{{ route('equipa.index') }}">Equipa</a>
                        @else
                            <a class="rounded px-3 py-2 hover:bg-white/10" href="{{ route('minhas-entregas.index') }}">Minhas entregas</a>
                        @endif
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button class="rounded bg-white/10 px-3 py-2 hover:bg-white/15" type="submit">Sair</button>
                        </form>
                    </nav>
                </div>
            </header>
        @endauth

        <main class="mx-auto max-w-7xl px-4 py-8">
            @if(session('status'))
                <div class="mb-6 rounded border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
            @endif

            @if($errors->any())
                <div class="mb-6 rounded border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                    {{ $errors->first() }}
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</body>
</html>
