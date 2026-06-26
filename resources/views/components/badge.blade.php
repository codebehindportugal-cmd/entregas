@props(['variant' => 'green', 'size' => 'sm'])

@php
$colours = [
    'green'  => 'bg-emerald-100 text-emerald-800',
    'blue'   => 'bg-blue-100 text-blue-800',
    'amber'  => 'bg-amber-100 text-amber-800',
    'red'    => 'bg-red-100 text-red-800',
    'slate'  => 'bg-slate-100 text-slate-700',
    'purple' => 'bg-violet-100 text-violet-800',
];
$sizes = [
    'xs' => 'px-2 py-0.5 text-[10px]',
    'sm' => 'px-2.5 py-0.5 text-xs',
    'md' => 'px-3 py-1 text-sm',
];
$cls = ($colours[$variant] ?? $colours['green']).' '.($sizes[$size] ?? $sizes['sm']);
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full font-bold '.$cls]) }}>
    {{ $slot }}
</span>
