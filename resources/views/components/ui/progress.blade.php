@props(['value' => 0, 'max' => 100, 'tone' => 'teal'])
@php
$pct = $max > 0 ? min(100, max(0, $value / $max * 100)) : 0;
$tones = ['teal' => 'bg-teal-600', 'amber' => 'bg-amber-500', 'rose' => 'bg-rose-600'];
@endphp
<div class="h-2 w-full overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="{{ round($pct) }}" aria-valuemin="0" aria-valuemax="100">
    <div class="h-full rounded-full {{ $tones[$tone] ?? $tones['teal'] }} transition-all" style="width: {{ $pct }}%"></div>
</div>
