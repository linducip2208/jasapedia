@props(['name' => 'q', 'placeholder' => 'Cari jasa, teknisi, freelancer, programmer...'])
<form {{ $attributes->merge(['action' => route('web.explore'), 'method' => 'GET', 'role' => 'search']) }} class="flex flex-1 items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-4 py-2 transition focus-within:border-teal-600 focus-within:bg-white focus-within:ring-2 focus-within:ring-teal-600/20">
    <svg class="h-4.5 w-4.5 shrink-0 text-slate-400" style="height:18px;width:18px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
    <input type="search" name="{{ $name }}" value="{{ request($name) }}" placeholder="{{ $placeholder }}" class="w-full bg-transparent text-sm text-slate-900 placeholder-slate-400 outline-none" aria-label="Pencarian"/>
    @if(isset($action))<input type="hidden" name="category" value="{{ $action }}"/>@endif
    <button type="submit" class="rounded-full bg-teal-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-teal-700" aria-label="Cari">Cari</button>
</form>
