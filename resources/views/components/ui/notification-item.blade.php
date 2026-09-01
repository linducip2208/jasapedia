@props(['notification'])
<article class="flex gap-3 rounded-xl px-4 py-3 transition hover:bg-slate-50 {{ ($notification->read_at ?? null) ? '' : 'bg-teal-50/60' }}">
    <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ ($notification->read_at ?? null) ? 'bg-slate-100 text-slate-400' : 'bg-teal-100 text-teal-700' }}">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
    </span>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-slate-800">{{ $notification->data['title'] ?? 'Notifikasi' }}</p>
        @if(isset($notification->data['body']))<p class="mt-0.5 text-sm text-slate-500">{{ $notification->data['body'] }}</p>@endif
        <p class="mt-1 text-xs text-slate-400">{{ isset($notification->created_at) ? $notification->created_at->diffForHumans() : '' }}</p>
    </div>
    @if(isset($notification->data['url']))
        <a href="{{ $notification->data['url'] }}" class="self-center text-xs font-bold text-teal-700 hover:underline">Lihat</a>
    @endif
</article>
