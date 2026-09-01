@props(['label' => null, 'error' => null, 'hint' => null])
<div class="w-full">
    @if($label)<label for="{{ $attributes->get('id') }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $label }}</label>@endif
    <input {{ $attributes->merge(['class' => 'h-11 w-full rounded-xl border bg-white px-3.5 text-sm text-slate-900 placeholder-slate-400 transition focus:outline-none focus:ring-2 focus:ring-teal-600/30 '.($error ? 'border-rose-400' : 'border-slate-300 focus:border-teal-600')]) }}/>
    @if($hint)<p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>@endif
    @error($error ?? '')<p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
</div>
