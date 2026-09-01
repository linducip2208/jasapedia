@extends('layouts.partner')

@section('title', 'Ulasan | Jasapedia')

@section('partner-content')
<h1 class="text-xl font-extrabold text-slate-900">Ulasan Pelanggan</h1>

<div class="mt-4 space-y-3">
    @forelse($reviews as $review)
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <x-ui.avatar :name="$review->author->name" size="sm"/>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold text-slate-800">{{ $review->author->name }}</p>
                    <div class="flex items-center gap-2">
                        <x-ui.rating :value="$review->overall" size="xs"/>
                        <span class="text-xs text-slate-400">{{ $review->created_at->translatedFormat('d M Y') }}</span>
                    </div>
                </div>
            </div>
            @if($review->comment)<p class="mt-2.5 text-sm text-slate-600">{{ $review->comment }}</p>@endif

            @if($review->partner_response)
                <div class="mt-3 rounded-xl bg-slate-50 p-3.5 text-sm">
                    <p class="text-xs font-bold text-teal-700">Tanggapan kamu</p>
                    <p class="mt-1 text-slate-600">{{ $review->partner_response }}</p>
                </div>
            @else
                <details class="mt-3">
                    <summary class="cursor-pointer text-sm font-bold text-teal-700">Tanggapi ulasan</summary>
                    <form method="POST" action="{{ route('web.partner.reviews.respond', $review->id) }}" class="mt-2.5 space-y-2.5">
                        @csrf
                        <textarea name="response" rows="2" required placeholder="Balas ulasan dengan profesional..." class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea>
                        <x-ui.button type="submit" size="sm">Kirim Tanggapan</x-ui.button>
                    </form>
                </details>
            @endif
        </article>
    @empty
        <x-ui.empty-state title="Belum ada ulasan" description="Selesaikan pesanan untuk menerima ulasan pertama."/>
    @endforelse
</div>
<x-ui.pagination :paginator="$reviews"/>
@endsection
