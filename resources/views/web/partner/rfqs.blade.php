@extends('layouts.partner')

@section('title', 'Kebutuhan Terbuka | Jasapedia')

@section('partner-content')
<h1 class="text-xl font-extrabold text-slate-900">Kebutuhan Pelanggan</h1>
<p class="text-sm text-slate-500">Kirim penawaran untuk kebutuhan yang cocok dengan keahlianmu.</p>

<div class="mt-5 space-y-3">
    @forelse($rfqs as $rfq)
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase text-slate-400">{{ $rfq->code }} · {{ $rfq->created_at->diffForHumans() }}</p>
                    <h2 class="mt-0.5 font-bold text-slate-900">{{ $rfq->title }}</h2>
                    <p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ $rfq->description }}</p>
                </div>
                @if($rfq->deadline)<x-ui.badge tone="amber">Deadline {{ $rfq->deadline->translatedFormat('d M Y') }}</x-ui.badge>@endif
            </div>

            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-bold text-teal-700">Kirim Penawaran</summary>
                <form method="POST" action="{{ route('web.partner.rfqs.quote', $rfq->id) }}" class="mt-3 space-y-2.5 rounded-xl bg-slate-50 p-4">
                    @csrf
                    <input type="hidden" name="rfq_id" value="{{ $rfq->id }}">
                    <p class="text-xs font-bold uppercase text-slate-400">Item pekerjaan</p>
                    <div class="grid gap-2 sm:grid-cols-[1fr_80px_120px]">
                        <input name="line_items[0][name]" placeholder="Nama item" required class="h-10 rounded-lg border border-slate-300 px-3 text-sm"/>
                        <input name="line_items[0][qty]" type="number" min="1" value="1" required class="h-10 rounded-lg border border-slate-300 px-3 text-sm" aria-label="Jumlah"/>
                        <input name="line_items[0][unit_price]" type="number" min="0" placeholder="Harga satuan" required class="h-10 rounded-lg border border-slate-300 px-3 text-sm" aria-label="Harga"/>
                    </div>
                    <textarea name="terms" rows="2" placeholder="Syarat & catatan (opsional)" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                    <x-ui.button type="submit" size="sm">Kirim Penawaran</x-ui.button>
                </form>
            </details>
        </article>
    @empty
        <x-ui.empty-state title="Belum ada kebutuhan terbuka" description="Cek lagi nanti — kebutuhan baru masuk setiap hari."/>
    @endforelse
</div>
<x-ui.pagination :paginator="$rfqs"/>
@endsection
