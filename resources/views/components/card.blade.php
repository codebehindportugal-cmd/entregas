@props([
    'title'    => null,
    'subtitle' => null,
    'padding'  => 'p-5',
])

<div {{ $attributes->merge(['class' => 'card '.$padding]) }}>
    @if($title)
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-[#14532d]">{{ $title }}</h2>
                @if($subtitle)
                    <p class="mt-0.5 text-xs text-[#4b7a5a]">{{ $subtitle }}</p>
                @endif
            </div>
            @if(isset($action))
                <div class="shrink-0">{{ $action }}</div>
            @endif
        </div>
        <div class="border-t border-[#d1fae5] pt-4">
            {{ $slot }}
        </div>
    @else
        {{ $slot }}
    @endif
</div>
