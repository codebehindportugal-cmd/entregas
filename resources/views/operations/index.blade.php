<x-layouts.app title="Sistema">
    <x-page-title title="Sistema" subtitle="Estado operacional, backups, scans de seguranca e updates" />

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-base font-bold text-slate-900">Site online</h2>
                <form method="post" action="{{ route('operations.health') }}">
                    @csrf
                    <button class="rounded bg-[#3B82F6] px-3 py-2 text-xs font-semibold text-white">Verificar</button>
                </form>
            </div>
            @if($health)
                <p class="text-sm font-semibold {{ $health['ok'] ? 'text-emerald-700' : 'text-red-700' }}">
                    {{ $health['ok'] ? 'Online' : 'Com erro' }}
                    @if($health['status'])
                        · HTTP {{ $health['status'] }}
                    @endif
                </p>
                <p class="mt-1 text-xs text-slate-500">{{ $health['url'] ?? '' }}</p>
                <p class="mt-1 text-xs text-slate-500">Ultima verificacao: {{ $health['checked_at'] ?? '-' }}</p>
                @if(! empty($health['error']))
                    <p class="mt-2 rounded bg-red-50 p-2 text-xs text-red-700">{{ $health['error'] }}</p>
                @endif
            @else
                <p class="text-sm text-slate-500">Ainda sem resultado.</p>
            @endif
        </section>

        <section class="rounded border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-base font-bold text-slate-900">Scan de seguranca</h2>
                <form method="post" action="{{ route('operations.security') }}">
                    @csrf
                    <button class="rounded bg-[#3B82F6] px-3 py-2 text-xs font-semibold text-white">Correr scan</button>
                </form>
            </div>
            @php($summary = $security['summary'] ?? null)
            @if($summary)
                <p class="text-sm font-semibold {{ ($summary['total'] ?? 0) > 0 ? 'text-red-700' : 'text-emerald-700' }}">
                    {{ $summary['total'] ?? 0 }} vulnerabilidades
                    · {{ $summary['critical'] ?? 0 }} criticas
                    · {{ $summary['high'] ?? 0 }} altas
                </p>
                <p class="mt-1 text-xs text-slate-500">Ultimo scan: {{ $security['scanned_at'] ?? '-' }}</p>
                <div class="mt-3 space-y-2">
                    @forelse(($summary['items'] ?? []) as $item)
                        <div class="rounded border border-red-100 bg-red-50 p-2 text-xs text-red-900">
                            <p class="font-bold">[{{ $item['severity'] }}] {{ $item['package'] }}</p>
                            <p>{{ $item['title'] }}</p>
                            @if(! empty($item['url']))
                                <a class="text-blue-700 underline" href="{{ $item['url'] }}" target="_blank" rel="noopener">Abrir advisory</a>
                            @endif
                        </div>
                    @empty
                        <p class="rounded bg-emerald-50 p-2 text-xs text-emerald-700">Sem vulnerabilidades detetadas.</p>
                    @endforelse
                </div>
            @else
                <p class="text-sm text-slate-500">Ainda sem scan.</p>
            @endif
        </section>

        <section class="rounded border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-base font-bold text-slate-900">Updates</h2>
                <form method="post" action="{{ route('operations.updates') }}">
                    @csrf
                    <button class="rounded bg-[#3B82F6] px-3 py-2 text-xs font-semibold text-white">Verificar</button>
                </form>
            </div>
            @if($updates)
                <p class="text-xs text-slate-500">Ultima verificacao: {{ $updates['checked_at'] ?? '-' }}</p>
                <p class="mt-2 text-sm text-slate-700">Relatorio guardado em `storage/app/operations/updates-scan.json`.</p>
            @else
                <p class="text-sm text-slate-500">Ainda sem verificacao.</p>
            @endif
        </section>

        <section class="rounded border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-base font-bold text-slate-900">Backups</h2>
                <form method="post" action="{{ route('operations.backup') }}">
                    @csrf
                    <button class="rounded bg-[#22C55E] px-3 py-2 text-xs font-semibold text-[#0A0F1A]">Fazer backup</button>
                </form>
            </div>
            <div class="space-y-2">
                @forelse($backups as $backup)
                    <div class="rounded border border-slate-100 bg-slate-50 p-2 text-xs">
                        <p class="font-semibold text-slate-800">{{ $backup['name'] }}</p>
                        <p class="text-slate-500">Modificado: {{ $backup['modified_at'] }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Ainda sem backups.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
