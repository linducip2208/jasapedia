@props(['tone' => 'slate', 'size' => 'md'])
@php
$tones = ['slate' => 'bg-slate-100 text-slate-700', 'teal' => 'bg-teal-50 text-teal-700 ring-1 ring-teal-600/20', 'amber' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-500/20', 'rose' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-500/20', 'green' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20', 'indigo' => 'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-500/20'];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full font-semibold '.($size === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs').' '.($tones[$tone] ?? $tones['slate'])]) }}>{{ $slot }}</span>
