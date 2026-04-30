<x-layouts.app title="Perfil do cliente">
    <x-page-title title="{{ $encomenda->billing_name ?: 'Cliente B2C' }}" subtitle="Perfil e preferencias">
        <a href="{{ route('encomendas.index') }}" class="rounded bg-white/10 px-4 py-2 text-sm font-semibold text-slate-200">Voltar</a>
    </x-page-title>

    <div class="grid gap-6 lg:grid-cols-[1fr_1.4fr]">
        <section class="space-y-4">
            <div class="rounded border border-white/10 bg-[#151E2D] p-5">
                <h2 class="text-lg font-semibold text-white">Contacto</h2>
                <div class="mt-4 space-y-2 text-sm text-slate-300">
                    <p><span class="text-slate-500">Encomenda:</span> #{{ $encomenda->woo_id }}</p>
                    <p><span class="text-slate-500">Telefone:</span> {{ $encomenda->billing_phone ?: 'Sem telefone' }}</p>
                    <p><span class="text-slate-500">Email:</span> {{ $encomenda->billing_email ?: 'Sem email' }}</p>
                    <p><span class="text-slate-500">Tipo:</span> {{ $encomenda->source_type === 'subscription' ? 'Subscricao' : 'Encomenda' }}</p>
                    <p><span class="text-slate-500">Dia:</span> {{ $encomenda->dia_entrega ? ucfirst($encomenda->dia_entrega) : '-' }}</p>
                    <p><span class="text-slate-500">Ciclo:</span> {{ $encomenda->ciclo_entrega === 'quinzenal' ? '15 em 15 dias' : 'Semanal' }}</p>
                </div>
            </div>

            <div class="rounded border border-white/10 bg-[#151E2D] p-5">
                <h2 class="text-lg font-semibold text-white">Produtos</h2>
                <div class="mt-4 space-y-2 text-sm text-slate-300">
                    @forelse($encomenda->line_items ?? [] as $item)
                        <p>{{ $item['quantity'] ?? 0 }}x {{ $item['name'] ?? 'Produto' }}</p>
                    @empty
                        <p class="text-slate-500">Sem produtos.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded border border-white/10 bg-[#151E2D] p-5">
                <h2 class="text-lg font-semibold text-white">Entregas</h2>
                @php($entregas = $encomenda->entregasSubscricao())
                <div class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                    <div class="rounded bg-white/5 p-3">
                        <p class="text-slate-400">No ciclo</p>
                        <p class="mt-1 text-xl font-semibold text-white">{{ $entregas['total'] }}</p>
                    </div>
                    <div class="rounded bg-emerald-500/10 p-3">
                        <p class="text-emerald-200">Feitas</p>
                        <p class="mt-1 text-xl font-semibold text-white">{{ $entregas['feitas'] }}</p>
                    </div>
                    <div class="rounded bg-[#F59E0B]/10 p-3">
                        <p class="text-amber-200">Por realizar</p>
                        <p class="mt-1 text-xl font-semibold text-white">{{ $entregas['por_realizar'] }}</p>
                    </div>
                </div>
                @if($entregas['proxima'])
                    <p class="mt-3 text-sm text-slate-300">Proxima: {{ \Illuminate\Support\Carbon::parse($entregas['proxima'])->format('d/m/Y') }}</p>
                @endif
            </div>
        </section>

        <section class="space-y-6">
            <div class="rounded border border-white/10 bg-[#151E2D] p-5">
                <h2 class="text-lg font-semibold text-white">Preferencias do WordPress</h2>
                @if($encomenda->preferences_text)
                    <p class="mt-4 whitespace-pre-line text-sm text-slate-300">{{ $encomenda->preferences_text }}</p>
                @elseif($encomenda->excluded_products)
                    <p class="mt-4 text-sm text-slate-300">{{ implode(', ', $encomenda->excluded_products) }}</p>
                @else
                    <p class="mt-4 text-sm text-slate-500">Sem preferencias sincronizadas.</p>
                @endif
            </div>

            <form method="post" action="{{ route('encomendas.profile.update', $encomenda) }}" class="rounded border border-white/10 bg-[#151E2D] p-5">
                @csrf
                @method('put')
                <h2 class="text-lg font-semibold text-white">Perfil interno</h2>
                <label class="mt-4 block text-sm text-slate-300">Preferencias tratadas
                    <textarea name="profile_preferences" rows="6" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">{{ old('profile_preferences', $encomenda->profile_preferences) }}</textarea>
                </label>
                <label class="mt-4 block text-sm text-slate-300">Notas do cliente
                    <textarea name="customer_notes" rows="6" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">{{ old('customer_notes', $encomenda->customer_notes) }}</textarea>
                </label>
                <button class="mt-4 rounded bg-[#22C55E] px-4 py-2 font-semibold text-[#0A0F1A]">Guardar perfil</button>
            </form>
        </section>
    </div>
</x-layouts.app>
