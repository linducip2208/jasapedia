@props(['items' => [], 'activeIndex' => null])
<ol class="relative space-y-0 border-l-2 border-slate-100 pl-5">
    @foreach($items as $i => $item)
        <li class="relative pb-6 last:pb-0">
            <span class="absolute -left-[27px] top-0.5 flex h-4 w-4 items-center justify-center rounded-full ring-4 ring-white {{ ($activeIndex !== null && $i === $activeIndex) ? 'bg-teal-600' : ($activeIndex === null || $i < $activeIndex ? 'bg-teal-500' : 'bg-slate-300') }}"></span>
            <p class="text-sm font-semibold text-slate-800">{{ is_array($item) ? $item['label'] : $item }}</p>
            @if(is_array($item) && isset($item['time']))<p class="text-xs text-slate-400">{{ $item['time'] }}</p>@endif
            @if(is_array($item) && isset($item['note']))<p class="mt-0.5 text-xs text-slate-500">{{ $item['note'] }}</p>@endif
        </li>
    @endforeach
</ol>
