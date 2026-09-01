@props(['paginator'])
<nav role="navigation" aria-label="Pagination" class="mt-8 flex items-center justify-center gap-1">
    @if($paginator->onFirstPage())
        <span class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-300">&lsaquo;</span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:border-teal-600 hover:text-teal-700" aria-label="Sebelumnya">&lsaquo;</a>
    @endif
    @foreach($paginator->toArray()['links'] ?? [] as $link)
        @continue($link['label'] === '...')
        @if($link['active'])
            <span aria-current="page" class="flex h-9 min-w-9 items-center justify-center rounded-lg bg-teal-600 px-2 text-sm font-bold text-white">{{ $link['label'] }}</span>
        @else
            <a href="{{ $link['url'] }}" class="flex h-9 min-w-9 items-center justify-center rounded-lg px-2 text-sm text-slate-600 hover:bg-slate-100">{{ $link['label'] }}</a>
        @endif
    @endforeach
    @if($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:border-teal-600 hover:text-teal-700" aria-label="Berikutnya">&rsaquo;</a>
    @else
        <span class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-300">&rsaquo;</span>
    @endif
</nav>
