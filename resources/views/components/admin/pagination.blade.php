@props(['paginator'])
@if($paginator->hasPages())
    <nav aria-label="Pagination">
        <ul class="pagination pagination-sm justify-content-center mt-3">
            <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya">&laquo;</a>
            </li>
            @foreach($paginator->linkCollection() as $link)
                @if($link['url'] === null)
                    <li class="page-item disabled"><span class="page-link">{!! $link['label'] !!}</span></li>
                @else
                    <li class="page-item {{ $link['active'] ? 'active' : '' }}" {{ $link['active'] ? 'aria-current="page"' : '' }}>
                        <a class="page-link" href="{{ $link['url'] }}">{!! $link['label'] !!}</a>
                    </li>
                @endif
            @endforeach
            <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya">&raquo;</a>
            </li>
        </ul>
    </nav>
@endif
