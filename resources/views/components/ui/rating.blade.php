@props(['value' => 0, 'count' => null, 'size' => 'sm'])
@php
$sizes = ['xs' => 'h-3 w-3', 'sm' => 'h-4 w-4', 'md' => 'h-5 w-5'];
@endphp
<span class="inline-flex items-center gap-1" aria-label="Rating {{ number_format($value, 1) }} dari 5">
    @for($i = 1; $i <= 5; $i++)
        <svg class="{{ $sizes[$size] ?? $sizes['sm'] }} {{ $i <= round($value) ? 'text-amber-400' : 'text-slate-300' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M9.05 2.9c.3-.9 1.6-.9 1.9 0l1.3 4a1 1 0 0 0 .95.7h4.2c.97 0 1.37 1.24.6 1.8l-3.4 2.5a1 1 0 0 0-.36 1.1l1.3 4c.3.94-.77 1.7-1.54 1.14l-3.4-2.5a1 1 0 0 0-1.18 0l-3.4 2.5c-.76.57-1.83-.2-1.53-1.14l1.3-4a1 1 0 0 0-.37-1.1L2.02 9.4c-.77-.56-.37-1.8.6-1.8h4.2a1 1 0 0 0 .95-.68l1.28-4Z"/>
        </svg>
    @endfor
    @if($count !== null)<span class="text-xs text-slate-500">({{ number_format($count) }})</span>@endif
</span>
