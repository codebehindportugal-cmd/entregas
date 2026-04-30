<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold text-white">{{ $title }}</h1>
        @isset($subtitle)
            <p class="mt-1 text-sm text-emerald-100/70">{{ $subtitle }}</p>
        @endisset
    </div>
    {{ $slot ?? '' }}
</div>
