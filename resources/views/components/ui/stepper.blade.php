@props(['steps' => [], 'activeIndex' => 0])
<ol class="flex flex-wrap items-center gap-y-2 text-sm">
    @foreach($steps as $i => $step)
        @php $done = $i < $activeIndex; $current = $i === $activeIndex; @endphp
        <li class="flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold {{ $done ? 'bg-teal-600 text-white' : ($current ? 'bg-teal-100 text-teal-700 ring-2 ring-teal-600' : 'bg-slate-100 text-slate-400') }}"
                aria-current="{{ $current ? 'step' : 'false' }}">{{ $done ? '✓' : $i + 1 }}</span>
            <span class="{{ $current ? 'font-semibold text-slate-900' : ($done ? 'text-slate-600' : 'text-slate-400') }}">{{ $step }}</span>
            @if(! $loop->last)<span class="mx-2 h-px w-6 bg-slate-200 sm:w-10" aria-hidden="true"></span>@endif
        </li>
    @endforeach
</ol>
