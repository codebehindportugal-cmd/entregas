@props(['notas' => null, 'compacto' => false])
@if(filled($notas))
    <div {{ $attributes->merge(['class' => 'rounded border border-amber-400/40 bg-amber-500/10 text-amber-100 '.($compacto ? 'mt-2 px-2 py-1 text-xs' : 'px-4 py-3 text-sm')]) }}>
        <p class="font-semibold text-amber-200">{{ $compacto ? 'Notas do cliente' : '⚠ Notas do cliente' }}</p>
        <p class="whitespace-pre-line">{{ $notas }}</p>
    </div>
@endif
