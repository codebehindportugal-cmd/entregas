<x-layouts.app title="Perfil do cliente">
    <x-page-title title="{{ $encomenda->billing_name ?: 'Cliente B2C' }}" subtitle="Perfil e preferencias">
        <div class="flex flex-wrap gap-2">
            @if($encomenda->podeConcluirNoWordPress())
                <form method="post" action="{{ route('encomendas.complete', $encomenda) }}" onsubmit="return confirm('Fechar esta encomenda no WordPress?');">
                    @csrf
                    <button class="rounded bg-[#22C55E] px-4 py-2 text-sm font-semibold text-[#0A0F1A]">Fechar encomenda</button>
                </form>
            @endif
            <a href="{{ route('encomendas.index') }}" class="rounded bg-white/10 px-4 py-2 text-sm font-semibold text-slate-200">Voltar</a>
        </div>
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

            @foreach($encomenda->preparacaoItems->where('tipo', 'b2c')->sortByDesc('data_preparacao') as $preparacao)
                <div class="rounded border border-white/10 bg-[#151E2D] p-5">
                    <h2 class="text-lg font-semibold text-white">Preparacao {{ $preparacao->data_preparacao->format('d/m/Y') }}</h2>
                    <form method="post" action="{{ route('preparacao.produtos.update', $preparacao) }}" class="mt-4 space-y-2">
                        @csrf
                        @method('put')
                        @forelse($encomenda->line_items ?? [] as $index => $produto)
                            @php($produtoKey = (string) $index)
                            <label class="flex items-start gap-2 rounded border border-white/10 bg-[#0A0F1A] p-3 text-sm text-slate-200">
                                <input name="produtos_picados[]" type="checkbox" value="{{ $produtoKey }}" @checked(in_array($produtoKey, $preparacao->produtos_picados ?? [], true)) class="mt-1 rounded border-white/10 bg-[#0A0F1A]">
                                <span>{{ $produto['quantity'] ?? 0 }}x {{ $produto['name'] ?? 'Produto' }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-slate-500">Sem produtos para picar.</p>
                        @endforelse
                        <button class="mt-2 rounded bg-[#22C55E] px-4 py-2 text-sm font-semibold text-[#0A0F1A]">Guardar produtos no cabaz</button>
                    </form>
                </div>
            @endforeach

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

            <div class="rounded border border-white/10 bg-[#151E2D] p-5">
                <h2 class="text-lg font-semibold text-white">Adiar entrega</h2>
                <form method="post" action="{{ route('encomendas.postpone', $encomenda) }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto]">
                    @csrf
                    @method('put')
                    <input name="postponed_until" type="date" value="{{ optional($encomenda->postponed_until)->toDateString() }}" class="rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-sm text-white">
                    <button class="rounded bg-[#F59E0B]/20 px-4 py-2 text-sm font-semibold text-amber-200 hover:bg-[#F59E0B]/30">Guardar adiamento</button>
                </form>
                @if($encomenda->postponed_until)
                    <form method="post" action="{{ route('encomendas.postpone.clear', $encomenda) }}" class="mt-3">
                        @csrf
                        @method('delete')
                        <button class="rounded bg-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-white/15">Remover adiamento</button>
                    </form>
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
                <h2 class="text-lg font-semibold text-white">Editar perfil</h2>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="block text-sm text-slate-300">Nome
                        <input name="billing_name" value="{{ old('billing_name', $encomenda->billing_name) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    </label>
                    <label class="block text-sm text-slate-300">Telefone
                        <input name="billing_phone" value="{{ old('billing_phone', $encomenda->billing_phone) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    </label>
                    <label class="block text-sm text-slate-300 md:col-span-2">Email
                        <input name="billing_email" type="email" value="{{ old('billing_email', $encomenda->billing_email) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    </label>
                    <label class="block text-sm text-slate-300">Tipo
                        <select name="source_type" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                            <option value="order" @selected(old('source_type', $encomenda->source_type) === 'order')>Encomenda</option>
                            <option value="subscription" @selected(old('source_type', $encomenda->source_type) === 'subscription')>Subscricao</option>
                        </select>
                    </label>
                    <label class="block text-sm text-slate-300">Dia de entrega
                        <select name="dia_entrega" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                            <option value="">Sem dia definido</option>
                            <option value="segunda" @selected(old('dia_entrega', $encomenda->dia_entrega) === 'segunda')>Segunda</option>
                            <option value="quarta" @selected(old('dia_entrega', $encomenda->dia_entrega) === 'quarta')>Quarta</option>
                            <option value="sabado" @selected(old('dia_entrega', $encomenda->dia_entrega) === 'sabado')>Sabado</option>
                        </select>
                    </label>
                    <label class="block text-sm text-slate-300">Ciclo
                        <select name="ciclo_entrega" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                            <option value="semanal" @selected(old('ciclo_entrega', $encomenda->ciclo_entrega) === 'semanal')>Semanal</option>
                            <option value="quinzenal" @selected(old('ciclo_entrega', $encomenda->ciclo_entrega) === 'quinzenal')>15 em 15 dias</option>
                        </select>
                    </label>
                    <label class="block text-sm text-slate-300">Entrega agendada
                        <input name="scheduled_delivery_at" type="date" value="{{ old('scheduled_delivery_at', optional($encomenda->scheduled_delivery_at)->toDateString()) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    </label>
                    <label class="block text-sm text-slate-300">Primeira entrega
                        <input name="first_delivery_at" type="date" value="{{ old('first_delivery_at', optional($encomenda->first_delivery_at)->toDateString()) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    </label>
                    <label class="block text-sm text-slate-300">Proximo pagamento
                        <input name="next_payment_at" type="date" value="{{ old('next_payment_at', optional($encomenda->next_payment_at)->toDateString()) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    </label>
                    <label class="block text-sm text-slate-300">Fim da subscricao
                        <input name="subscription_ends_at" type="date" value="{{ old('subscription_ends_at', optional($encomenda->subscription_ends_at)->toDateString()) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    </label>
                </div>

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
