@extends('layouts.app')

@section('title', 'Chat | Jasapedia')

@section('content')
<div class="grid gap-4 lg:grid-cols-[340px_1fr]">
    {{-- Conversation list --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-4">
            <h1 class="font-extrabold text-slate-900">Chat</h1>
        </div>
        <div class="max-h-[60vh] divide-y divide-slate-100 overflow-y-auto lg:max-h-[70vh]">
            @forelse($conversations as $conv)
                @php
                    $other = $conv->participants->firstWhere('id', auth()->id()) ? $conv->participants->firstWhere(fn ($p) => $p->id !== auth()->id()) : null;
                    $last = $conv->messages->first();
                @endphp
                <a href="{{ route('web.chat.show', $conv->id) }}" class="flex items-center gap-3 p-4 transition hover:bg-slate-50 {{ request()->routeIs('web.chat.show') && request()->route('id') == $conv->id ? 'bg-teal-50/60' : '' }}">
                    <x-ui.avatar :name="$other?->name ?? 'Chat'" size="md"/>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-slate-800">{{ $other?->name ?? ($conv->title ?? 'Obrolan') }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $last?->body ?? ($last?->type === 'system_event' ? 'Pesan sistem' : 'Belum ada pesan') }}</p>
                    </div>
                    @if($conv->last_message_at)<span class="shrink-0 text-[10px] text-slate-400">{{ $conv->last_message_at->diffForHumans(short: true) }}</span>@endif
                </a>
            @empty
                <div class="p-8 text-center text-sm text-slate-400">Belum ada percakapan.<br/>Mulai chat dari halaman penyedia atau pesanan.</div>
            @endforelse
        </div>
    </div>

    {{-- Empty pane desktop --}}
    <div class="hidden items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white/50 lg:flex">
        <div class="text-center">
            <x-brand.mark class="mx-auto h-12 w-12 text-teal-600/30"/>
            <p class="mt-3 text-sm text-slate-400">Pilih percakapan untuk mulai chat</p>
        </div>
    </div>
</div>
@endsection
