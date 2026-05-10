<div class="page-title mb-6 flex flex-wrap items-center justify-between gap-3">
    <div class="min-w-0">
        <h1 class="text-2xl font-semibold text-slate-950">{{ $title }}</h1>
        @isset($subtitle)
            <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
        @endisset
    </div>
    @if(isset($slot) && trim((string) $slot) !== '')
        <div class="page-title-actions flex flex-wrap items-center justify-end gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
