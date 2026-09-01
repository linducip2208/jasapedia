@props(['label', 'name', 'value', 'checked' => false])
<label class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-700">
    <input type="radio" name="{{ $name }}" value="{{ $value }}" @checked($checked)
        class="h-4 w-4 border-slate-300 text-teal-600 focus:ring-teal-600"/>
    <span>{{ $label }}</span>
</label>
