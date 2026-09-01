@props(['label', 'name', 'checked' => false])
<label class="inline-flex cursor-pointer items-center gap-3">
    <input type="hidden" name="{{ $name }}" value="0"/>
    <input type="checkbox" name="{{ $name }}" value="1" @checked($checked) class="peer sr-only" role="switch"/>
    <span class="relative h-6 w-11 rounded-full bg-slate-300 transition peer-checked:bg-teal-600 peer-focus-visible:ring-2 peer-focus-visible:ring-teal-600/40">
        <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
    </span>
    @if($label)<span class="text-sm font-medium text-slate-700">{{ $label }}</span>@endif
</label>
