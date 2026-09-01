@props(['tabs' => [], 'active' => null])
<div class="border-b border-slate-200" role="tablist">
    <nav class="-mb-px flex gap-6 overflow-x-auto">
        @foreach($tabs as $key => $tab)
            @php $isActive = $active === $key || ($active === null && $loop->first); @endphp
            <a href="{{ is_array($tab) ? ($tab['url'] ?? '#') : '#' }}"
                role="tab" aria-selected="{{ $isActive ? 'true' : 'false' }}"
                class="whitespace-nowrap border-b-2 px-1 pb-3 pt-1 text-sm font-semibold transition {{ $isActive ? 'border-teal-600 text-teal-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                {{ is_array($tab) ? $tab['label'] : $tab }}
            </a>
        @endforeach
    </nav>
</div>
