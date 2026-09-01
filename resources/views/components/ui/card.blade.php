@props(['padding' => true])
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white '.($padding ? 'p-5' : '').' '.(isset($attributes['class']) ? '' : 'shadow-sm')]) }}>
    {{ $slot }}
</div>
