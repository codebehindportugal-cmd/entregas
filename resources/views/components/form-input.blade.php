@props([
    'label'    => null,
    'name'     => null,
    'type'     => 'text',
    'required' => false,
    'hint'     => null,
])

<div>
    @if($label)
        <label @if($name) for="{{ $name }}" @endif class="form-label">
            {{ $label }}@if($required)<span class="ml-0.5 text-red-500">*</span>@endif
        </label>
    @endif
    <input
        type="{{ $type }}"
        @if($name) name="{{ $name }}" id="{{ $name }}" @endif
        @if($required) required @endif
        {{ $attributes->merge(['class' => 'form-input']) }}
    >
    @if($hint)
        <p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>
    @endif
</div>
