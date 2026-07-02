@props([
    'wooProducts',
    'initialLines' => [],
])

@php
    $linhas = old('products', $initialLines);

    if (count($linhas) === 0) {
        $linhas = [['woo_product_id' => null, 'quantity' => 1]];
    }
@endphp

<div data-product-lines>
    <div class="space-y-3" data-product-lines-list>
        @foreach($linhas as $linha => $itemOriginal)
            <div class="grid gap-3 md:grid-cols-[1fr_8rem_auto]" data-product-line>
                <label class="block text-sm text-slate-300">Produto
                    <select name="products[{{ $linha }}][woo_product_id]" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                        <option value="">{{ filled($itemOriginal['name'] ?? null) ? (($itemOriginal['quantity'] ?? 1).'x '.$itemOriginal['name']) : 'Escolher produto' }}</option>
                        @foreach($wooProducts as $produtoWoo)
                            <option value="{{ $produtoWoo->id }}" @selected((string) ($itemOriginal['woo_product_id'] ?? '') === (string) $produtoWoo->id)>
                                {{ $produtoWoo->name }}{{ $produtoWoo->precoVenda() !== null ? ' - '.number_format($produtoWoo->precoVenda(), 2, ',', ' ').' EUR' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm text-slate-300">Qtd.
                    <input name="products[{{ $linha }}][quantity]" type="number" min="1" max="999" value="{{ $itemOriginal['quantity'] ?? 1 }}" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                </label>
                <button type="button" data-remove-product-line class="self-end rounded bg-red-500/15 px-3 py-2 text-sm font-semibold text-red-200 hover:bg-red-500/25">Remover</button>
            </div>
        @endforeach
    </div>

    <button type="button" data-add-product-line class="mt-3 rounded bg-white/10 px-4 py-2 text-sm font-semibold text-slate-200 hover:bg-white/15">Adicionar produto</button>

    <template data-product-line-template>
        <div class="grid gap-3 md:grid-cols-[1fr_8rem_auto]" data-product-line>
            <label class="block text-sm text-slate-300">Produto
                <select name="products[__INDEX__][woo_product_id]" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
                    <option value="">Escolher produto</option>
                    @foreach($wooProducts as $produtoWoo)
                        <option value="{{ $produtoWoo->id }}">{{ $produtoWoo->name }}{{ $produtoWoo->precoVenda() !== null ? ' - '.number_format($produtoWoo->precoVenda(), 2, ',', ' ').' EUR' : '' }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm text-slate-300">Qtd.
                <input name="products[__INDEX__][quantity]" type="number" min="1" max="999" value="1" class="mt-1 w-full rounded border border-white/10 bg-[#0A0F1A] px-3 py-2 text-white">
            </label>
            <button type="button" data-remove-product-line class="self-end rounded bg-red-500/15 px-3 py-2 text-sm font-semibold text-red-200 hover:bg-red-500/25">Remover</button>
        </div>
    </template>
</div>

@once
    <script>
        document.addEventListener('click', function (event) {
            const addButton = event.target.closest('[data-add-product-line]');
            const removeButton = event.target.closest('[data-remove-product-line]');

            if (addButton) {
                const wrapper = addButton.closest('[data-product-lines]');
                const list = wrapper.querySelector('[data-product-lines-list]');
                const template = wrapper.querySelector('[data-product-line-template]');
                const index = Date.now().toString() + list.querySelectorAll('[data-product-line]').length;
                const html = template.innerHTML.replaceAll('__INDEX__', index);

                list.insertAdjacentHTML('beforeend', html);
            }

            if (removeButton) {
                const wrapper = removeButton.closest('[data-product-lines]');
                const list = wrapper.querySelector('[data-product-lines-list]');

                if (list.querySelectorAll('[data-product-line]').length > 1) {
                    removeButton.closest('[data-product-line]').remove();
                }
            }
        });
    </script>
@endonce
