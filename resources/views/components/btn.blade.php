@props([
    'variant' => 'primary',
    'size'    => 'md',
    'type'    => 'button',
    'href'    => null,
])

@php
$variants = [
    'primary'   => 'btn-primary',
    'secondary' => 'btn-secondary',
    'danger'    => 'inline-flex items-center justify-center gap-1.5 rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-red-700',
    'ghost'     => 'inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100',
];
$cls = $variants[$variant] ?? $variants['primary'];
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $cls]) }}>{{ $slot }}</button>
@endif
