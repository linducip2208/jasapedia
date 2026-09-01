@props(['label' => null, 'options' => [], 'error' => null])
<div class="w-full">
    @if($label)<label for="{{ $attributes->get('id') }}" class="mb-1.5 block text-sm font-semibold text-slate-700">{{ $label }}</label>@endif
    <select {{ $attributes->merge(['class' => 'h-11 w-full rounded-xl border bg-white px-3 text-sm text-slate-900 transition focus:outline-none focus:ring-2 focus:ring-teal-600/30 '.($error ? 'border-rose-400' : 'border-slate-300 focus:border-teal-600')]) }}>
        @foreach($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
        {{ $slot }}
    </select>
    @error($error ?? '')<p class="mt-1 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
</div>
