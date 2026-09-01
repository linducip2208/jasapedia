@props(['items' => []])
<nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 text-sm text-slate-500">
    @foreach($items as $i => $item)
        @if($i > 0)<span aria-hidden="true" class="text-slate-300">/</span>@endif
        @if(isset($item['url']))
            <a href="{{ $item['url'] }}" class="hover:text-teal-700">{{ $item['label'] }}</a>
        @else
            <span class="font-medium text-slate-700" aria-current="page">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
