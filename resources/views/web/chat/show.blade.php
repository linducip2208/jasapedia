@extends('layouts.app')

@section('title', 'Chat '.($other?->name ?? '').' | Jasapedia')

@section('content')
<div class="mx-auto flex h-[calc(100vh-11rem)] max-w-3xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    {{-- Header --}}
    <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3">
        <a href="{{ route('web.chat.index') }}" class="rounded-full p-1.5 text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Kembali">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
        </a>
        <x-ui.avatar :name="$other?->name ?? 'Chat'" size="sm"/>
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-bold text-slate-900">{{ $other?->name ?? ($conversation->title ?? 'Obrolan') }}</p>
            <p class="text-[11px] text-slate-400">Chat dilindungi — jangan bagikan kontak/kode OTP di luar Jasapedia</p>
        </div>
    </div>

    {{-- Messages --}}
    <div id="chat-messages" class="flex-1 space-y-2 overflow-y-auto bg-slate-50/60 px-4 py-4">
        @forelse($messages as $msg)
            @php $mine = $msg->sender_id === auth()->id(); @endphp
            <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                @if($msg->type === 'system_event')
                    <p class="mx-auto rounded-full bg-slate-200/70 px-3 py-1 text-[11px] font-semibold text-slate-500">{{ $msg->body }}</p>
                @else
                    <div class="max-w-[75%] rounded-2xl px-3.5 py-2.5 text-sm {{ $mine ? 'rounded-br-md bg-teal-600 text-white' : 'rounded-bl-md bg-white text-slate-800 shadow-sm' }}">
                        <p class="whitespace-pre-line">{{ $msg->body }}</p>
                        @if($msg->attachments->isNotEmpty())
                            <div class="mt-2 space-y-1.5">
                                @foreach($msg->attachments as $att)
                                    @if(str_starts_with($att->mime_type ?? '', 'image/'))
                                        <img src="{{ app(\App\Domain\Catalog\MediaService::class)->url($att->file_path) }}" alt="Lampiran" loading="lazy" class="max-h-48 rounded-xl object-cover"/>
                                    @else
                                        <a href="{{ app(\App\Domain\Catalog\MediaService::class)->url($att->file_path) }}" target="_blank" rel="noopener" class="flex items-center gap-2 rounded-lg bg-black/10 px-2.5 py-1.5 text-xs font-semibold">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                            {{ $att->file_name ?? 'Dokumen' }}
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                        <p class="mt-1 text-right text-[10px] {{ $mine ? 'text-teal-100/80' : 'text-slate-400' }}">{{ $msg->created_at->format('H:i') }}</p>
                    </div>
                @endif
            </div>
        @empty
            <p class="py-10 text-center text-sm text-slate-400">Mulai percakapan dengan sapaan singkat.</p>
        @endforelse
    </div>

    {{-- Composer --}}
    <form method="POST" action="{{ route('web.chat.send', $conversation->id) }}" class="flex items-center gap-2 border-t border-slate-100 p-3">
        @csrf
        <input type="hidden" name="client_message_id" value="{{ uniqid('cm_', true) }}">
        <input type="text" name="body" placeholder="Tulis pesan..." required maxlength="5000" autocomplete="off"
            class="h-11 w-full rounded-full border border-slate-200 bg-slate-50 px-4 text-sm focus:border-teal-600 focus:bg-white focus:outline-none focus:ring-2 focus:ring-teal-600/20"
            aria-label="Pesan"/>
        <button type="submit" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-teal-600 text-white hover:bg-teal-700" aria-label="Kirim pesan">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
        </button>
    </form>
</div>

<script>
    // Auto-scroll + poll fallback (Reverb-ready: replace poll with broadcast listener)
    (function () {
        const box = document.getElementById('chat-messages');
        if (box) box.scrollTop = box.scrollHeight;
        const lastId = {{ $messages->max('id') ?? 0 }};
        setInterval(async () => {
            try {
                const res = await fetch('{{ route('web.chat.poll', $conversation->id) }}?after_id=' + lastId, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                if (data.messages?.length) location.reload();
            } catch (e) { /* offline: ignore */ }
        }, 8000);
    })();
</script>
@endsection
