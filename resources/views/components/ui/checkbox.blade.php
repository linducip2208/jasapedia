@props(['label', 'name', 'checked' => false, 'error' => null])
<label class="flex w-full cursor-pointer items-start gap-2.5 text-sm text-slate-700">
    <input type="checkbox" name="{{ $name }}" value="1" @checked($checked)
        class="mt-0.5 h-4.5 w-4.5 rounded border-slate-300 text-teal-600 focus:ring-teal-600" style="height:18px;width:18px"/>
    <span>{{ $label }}</span>
    @error($name)<p class="text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
</label>
