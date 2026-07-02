<x-layouts.app title="Nova encomenda B2C">
    <x-page-title title="Nova encomenda B2C" subtitle="Criar encomenda manual no WooCommerce">
        <a href="{{ route('encomendas.index') }}" class="rounded bg-white/10 px-4 py-2 text-sm font-semibold text-slate-200">Voltar</a>
    </x-page-title>

    <form method="post" action="{{ route('encomendas.store') }}" class="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
        @csrf

        <section class="space-y-6">
            <div class="rounded border border-white/10 bg-[#151E2D] p-5">
                <h2 class="text-lg font-semibold text-white">Cliente</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="block text-sm text-slate-300">Nome
                        <input name="billing_name" required value="{{ old('billing_name') }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    </label>
                    <label class="block text-sm text-slate-300">Telefone
                        <input name="billing_phone" required value="{{ old('billing_phone') }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    </label>
                    <label class="block text-sm text-slate-300 md:col-span-2">Email
                        <input name="billing_email" type="email" value="{{ old('billing_email') }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    </label>
                    <label class="block text-sm text-slate-300">Idioma
                        <select name="customer_language" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                            <option value="pt" @selected(old('customer_language', 'pt') === 'pt')>Portugues</option>
                            <option value="en" @selected(old('customer_language') === 'en')>English</option>
                        </select>
                    </label>
                    <label class="block text-sm text-slate-300">Dia de entrega
                        <select name="dia_entrega" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                            <option value="">Sem dia definido</option>
                            <option value="segunda" @selected(old('dia_entrega') === 'segunda')>Segunda</option>
                            <option value="quarta" @selected(old('dia_entrega') === 'quarta')>Quarta</option>
                            <option value="sabado" @selected(old('dia_entrega') === 'sabado')>Sabado</option>
                        </select>
                    </label>
                    <label class="block text-sm text-slate-300 md:col-span-2">Data de entrega
                        <input name="scheduled_delivery_at" type="date" value="{{ old('scheduled_delivery_at') }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    </label>
                </div>
            </div>

            <div class="rounded border border-white/10 bg-[#151E2D] p-5">
                <h2 class="text-lg font-semibold text-white">Moradas</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="block text-sm text-slate-300 md:col-span-2">Morada faturacao
                        <input name="billing_address_1" value="{{ old('billing_address_1') }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    </label>
                    <label class="block text-sm text-slate-300">Cidade faturacao
                        <input name="billing_city" value="{{ old('billing_city') }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    </label>
                    <label class="block text-sm text-slate-300">Codigo postal faturacao
                        <input name="billing_postcode" value="{{ old('billing_postcode') }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    </label>
                    <label class="block text-sm text-slate-300 md:col-span-2">Morada entrega
                        <input name="shipping_address_1" value="{{ old('shipping_address_1') }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    </label>
                    <label class="block text-sm text-slate-300">Cidade entrega
                        <input name="shipping_city" value="{{ old('shipping_city') }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    </label>
                    <label class="block text-sm text-slate-300">Codigo postal entrega
                        <input name="shipping_postcode" value="{{ old('shipping_postcode') }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    </label>
                </div>
                <label class="mt-4 block text-sm text-slate-300">Notas
                    <textarea name="customer_notes" rows="4" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">{{ old('customer_notes') }}</textarea>
                </label>
            </div>
        </section>

        <section class="space-y-6">
            <div class="rounded border border-white/10 bg-[#151E2D] p-5">
                <h2 class="text-lg font-semibold text-white">Produtos</h2>
                <div class="mt-4 space-y-3">
                    @for($linha = 0; $linha < 8; $linha++)
                        <div class="grid gap-3 md:grid-cols-[1fr_8rem]">
                            <label class="block text-sm text-slate-300">Produto
                                <select name="products[{{ $linha }}][woo_product_id]" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                                    <option value="">Escolher produto</option>
                                    @foreach($wooProducts as $produtoWoo)
                                        <option value="{{ $produtoWoo->id }}" @selected(old("products.{$linha}.woo_product_id") == $produtoWoo->id)>
                                            {{ $produtoWoo->name }}{{ $produtoWoo->precoVenda() !== null ? ' - '.number_format($produtoWoo->precoVenda(), 2, ',', ' ').' EUR' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-sm text-slate-300">Qtd.
                                <input name="products[{{ $linha }}][quantity]" type="number" min="1" max="999" value="{{ old("products.{$linha}.quantity", 1) }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                            </label>
                        </div>
                    @endfor
                </div>
                @if($wooProducts->isEmpty())
                    <p class="mt-3 rounded bg-[#F59E0B]/15 px-3 py-2 text-sm text-amber-200">Sincroniza produtos primeiro.</p>
                @endif
            </div>

            <div class="rounded border border-white/10 bg-[#151E2D] p-5">
                <h2 class="text-lg font-semibold text-white">Cupoes</h2>
                @if($couponLoadError)
                    <p class="mt-3 rounded bg-red-500/10 px-3 py-2 text-sm text-red-200">Nao foi possivel carregar os cupoes do WooCommerce.</p>
                @elseif(count($wooCoupons) === 0)
                    <p class="mt-3 rounded bg-white/5 px-3 py-2 text-sm text-slate-400">Sem cupoes registados no WooCommerce.</p>
                @else
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach($wooCoupons as $coupon)
                            <label class="flex items-start gap-2 rounded border border-white/10 bg-[#0A0F1A] p-3 text-sm text-slate-200">
                                <input name="coupon_codes[]" type="checkbox" value="{{ $coupon['code'] }}" @checked(in_array($coupon['code'], old('coupon_codes', []), true)) class="mt-1 rounded border-white/10 bg-[#0A0F1A]">
                                <span>
                                    <span class="font-semibold text-white">{{ $coupon['code'] }}</span>
                                    @if(filled($coupon['amount'] ?? null))
                                        <span class="text-slate-400">({{ $coupon['amount'] }}{{ ($coupon['discount_type'] ?? '') === 'percent' ? '%' : ' EUR' }})</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="rounded border border-white/10 bg-[#151E2D] p-5">
                <button class="rounded bg-[#22C55E] px-5 py-3 text-sm font-semibold text-[#0A0F1A]">Criar no WooCommerce</button>
            </div>
        </section>
    </form>
</x-layouts.app>
