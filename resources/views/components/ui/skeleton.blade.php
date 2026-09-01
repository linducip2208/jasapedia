@props(['lines' => 3])
<div class="animate-pulse space-y-3" aria-hidden="true">
    @for($i = 0; $i < $lines; $i++)
        <div class="h-4 rounded bg-slate-200" style="width: {{ [90, 70, 80, 55][$i % 4] }}%"></div>
    @endfor
</div>
