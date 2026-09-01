@props(['label' => null, 'error' => null, 'rows' => 4])
<div class="w-full">
    @if($label)<label for="{{ $attributes->get('id') }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $label }}</label>@endif
    <textarea {{ $attributes->merge(['rows' => $rows, 'class' => 'w-full rounded-xl border bg-white px-3.5 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:outline-none focus:ring-2 focus:ring-teal-600/30 '.($error ? 'border-rose-400' : 'border-slate-300 focus:border-teal-600')]) }}>{{ $slot }}</textarea>
    @error($error ?? '')<p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
</div>
