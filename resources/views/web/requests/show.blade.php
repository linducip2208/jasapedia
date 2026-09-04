@extends('layouts.app')

@section('title', $rfq->title.' | Jasapedia')

@section('content')
<x-ui.breadcrumb :items="[['label' => 'Beranda', 'url' => route('web.home')], ['label' => 'Kebutuhan', 'url' => route('web.requests.index')], ['label' => $rfq->code]]"/>

<div class="mt-4 grid gap-6 lg:grid-cols-[1fr_300px]">
    <div class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $rfq->code }}</p>
                    <h1 class="mt-1 text-lg font-extrabold text-slate-900">{{ $rfq->title }}</h1>
                </div>
                <x-ui.status-badge :status="$rfq->status" type="project"/>
            </div>
            <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ $rfq->description }}</p>
            @if($rfq->deadline)<p class="mt-3 text-sm text-slate-500">Butuh penawaran sebelum <strong>{{ $rfq->deadline->translatedFormat('d F Y') }}</strong></p>@endif
        </div>

        <h2 class="text-lg font-extrabold text-slate-900">Penawaran Masuk ({{ $rfq->quotations->count() }})</h2>
        <div class="space-y-3">
            @forelse($rfq->quotations as $quotation)
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$quotation->partner->display_name" :src="$quotation->partner->avatar_path ? app(\App\Domain\Catalog\MediaService::class)->url($quotation->partner->avatar_path) : null" :verified="$quotation->partner->isVerified()"/>
                            <div>
                                <a href="{{ route('web.provider.show', $quotation->partner->slug) }}" class="font-bold text-slate-900 hover:text-teal-700">{{ $quotation->partner->display_name }}</a>
                                <x-ui.rating :value="$quotation->partner->rating_avg" :count="$quotation->partner->rating_count" size="xs"/>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-[11px] text-slate-400">Total penawaran</p>
                            <x-ui.money :amount="$quotation->total" class="text-lg font-extrabold text-teal-700"/>
                        </div>
                    </div>

                    <ul class="mt-4 space-y-1.5 rounded-xl bg-slate-50 p-3.5 text-sm">
                        @foreach($quotation->line_items as $item)
                            <li class="flex justify-between gap-3"><span class="text-slate-600">{{ $item['name'] }} × {{ $item['qty'] }}</span><span class="font-semibold text-slate-800">{{ (new \App\Support\Money\Money((int) ($item['qty'] * $item['unit_price'])))->format() }}</span></li>
                        @endforeach
                    </ul>

                    @if($quotation->terms)<p class="mt-3 text-sm text-slate-500"><strong class="text-slate-700">Syarat:</strong> {{ $quotation->terms }}</p>@endif
                    @if($quotation->valid_until)<p class="mt-1 text-xs text-slate-400">Berlaku sampai {{ $quotation->valid_until->translatedFormat('d F Y') }}</p>@endif

                    @if($rfq->status === 'open' && $quotation->status === 'sent')
                        <div class="mt-4 flex flex-wrap gap-2.5">
                            <form method="POST" action="{{ route('web.requests.quotations.accept', [$rfq->id, $quotation->id]) }}">
                                @csrf
                                <x-ui.button type="submit" size="sm">Terima Penawaran</x-ui.button>
                            </form>
                            <a href="{{ route('web.chat.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-600 hover:text-teal-700">Chat</a>
                        </div>
                    @elseif($quotation->status === 'approved')
                        <div class="mt-4 flex flex-wrap items-center gap-2.5">
                            @if($quotation->order_id)
                                <a href="{{ route('web.orders.show', $quotation->order_id) }}" class="rounded-xl bg-teal-50 px-4 py-2 text-sm font-bold text-teal-700 ring-1 ring-teal-200 hover:bg-teal-100">Lihat Pesanan {{ $quotation->order?->code }}</a>
                            @else
                                <form method="POST" action="{{ route('web.requests.quotations.order', [$rfq->id, $quotation->id]) }}">
                                    @csrf
                                    <x-ui.button type="submit" size="sm">Pesan Sekarang</x-ui.button>
                                </form>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <x-ui.empty-state title="Belum ada penawaran" description="Penyedia sedang melihat kebutuhanmu. Penawaran biasanya masuk dalam beberapa jam."/>
            @endforelse
        </div>
    </div>

    <aside class="h-fit rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:sticky lg:top-24">
        <h2 class="font-bold text-slate-900">Ringkasan</h2>
        <dl class="mt-3 space-y-2.5 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd><x-ui.status-badge :status="$rfq->status" type="project"/></dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Penawaran</dt><dd class="font-semibold">{{ $rfq->quotations_count }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Dibuat</dt><dd class="font-semibold">{{ $rfq->created_at->translatedFormat('d M Y') }}</dd></div>
        </dl>
        @if($rfq->status === 'open')
            <form method="POST" action="{{ route('web.requests.close', $rfq->id) }}" class="mt-4">
                @csrf
                <x-ui.button type="submit" variant="outline" size="sm" full>Tutup Kebutuhan</x-ui.button>
            </form>
        @endif
    </aside>
</div>
@endsection
