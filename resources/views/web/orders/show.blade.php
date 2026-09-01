@extends('layouts.app')

@section('title', 'Pesanan '.$order->code.' | Jasapedia')

@section('content')
<x-ui.breadcrumb :items="[['label' => 'Beranda', 'url' => route('web.home')], ['label' => 'Pesanan', 'url' => route('web.orders')], ['label' => $order->code]]"/>

<div class="mt-4 grid gap-6 lg:grid-cols-[1fr_340px]">
    <div class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $order->code }}</p>
                    <h1 class="mt-0.5 text-lg font-extrabold text-slate-900">{{ $order->service?->title ?? 'Pesanan' }}</h1>
                    <p class="mt-1 text-sm text-slate-500">Dibuat {{ $order->created_at->translatedFormat('d M Y, H:i') }}</p>
                </div>
                <x-ui.status-badge :status="$order->status"/>
            </div>

            @if($order->scheduled_at)
                <div class="mt-4 flex items-center gap-2.5 rounded-xl bg-teal-50 px-4 py-3 text-sm font-semibold text-teal-800">
                    <svg class="h-4.5 w-4.5" style="height:18px;width:18px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    Jadwal: {{ $order->scheduled_at->translatedFormat('l, d F Y — H:i') }}
                </div>
            @endif

            {{-- Field service timeline --}}
            <h2 class="mt-6 text-sm font-bold uppercase tracking-wide text-slate-400">Status Pekerjaan</h2>
            <div class="mt-3">
                <x-ui.timeline :items="$order->history->map(fn ($h) => [
                    'label' => __('status.'.$h->to_status),
                    'time' => $h->created_at->translatedFormat('d M Y, H:i'),
                    'note' => $h->reason,
                ])->all()"/>
            </div>
        </div>

        {{-- Items --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-bold text-slate-900">Rincian Biaya</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach($order->items as $item)
                    <li class="flex justify-between gap-4"><span class="text-slate-600">{{ $item->name }} @if($item->qty > 1)× {{ $item->qty }}@endif</span><span class="font-semibold text-slate-800">{{ (new \App\Support\Money\Money((int) $item->amount))->format() }}</span></li>
                @endforeach
            </ul>
            <div class="mt-3 flex justify-between border-t border-slate-100 pt-3 text-base">
                <span class="font-bold text-slate-900">Total</span>
                <x-ui.money :amount="$order->total" class="font-extrabold text-teal-700"/>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <aside class="h-fit space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:sticky lg:top-24">
        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Total Pembayaran</p>
        <x-ui.money :amount="$order->total" class="text-2xl font-extrabold text-slate-900"/>

        @if($order->status === 'pending_payment')
            <form method="POST" action="{{ route('web.orders.pay', $order->id) }}">
                @csrf
                <x-ui.button type="submit" full>Bayar Sekarang</x-ui.button>
            </form>
            <p class="text-center text-xs text-slate-400">Dana ditahan aman, penyedia menerima setelah pekerjaan selesai.</p>
        @endif

        @if($order->status === 'awaiting_customer_confirmation')
            <div class="rounded-xl bg-amber-50 p-3.5 text-sm text-amber-800">Pekerjaan selesai! Konfirmasi untuk melepas dana ke penyedia.</div>
            <form method="POST" action="{{ route('web.orders.confirm', $order->id) }}">
                @csrf
                <x-ui.button type="submit" full>Konfirmasi Selesai</x-ui.button>
            </form>
        @endif

        @if(in_array($order->status, ['arrived', 'checked_in']))
            <form method="POST" action="{{ route('web.orders.checkin', $order->id) }}" class="space-y-2">
                @csrf
                <label class="block text-sm font-semibold text-slate-700">Kode OTP teknisi</label>
                <input name="otp" maxlength="6" inputmode="numeric" pattern="[0-9]*" class="h-12 w-full rounded-xl border border-slate-300 text-center text-xl font-bold tracking-[0.5em]" required autocomplete="one-time-code" aria-label="Kode OTP"/>
                <x-ui.button type="submit" full>Konfirmasi Kehadiran</x-ui.button>
            </form>
        @endif

        @if(in_array($order->status, ['completed', 'settled', 'closed']) && ! $order->review)
            <form method="POST" action="{{ route('web.orders.review', $order->id) }}" class="space-y-2.5 border-t border-slate-100 pt-4">
                @csrf
                <p class="text-sm font-bold text-slate-900">Beri Ulasan</p>
                <select name="overall" class="h-11 w-full rounded-xl border border-slate-300 px-3 text-sm" aria-label="Rating keseluruhan">
                    @foreach(range(5, 1) as $s)
                        <option value="{{ $s }}">{{ $s }} bintang</option>
                    @endforeach
                </select>
                <x-ui.textarea name="comment" placeholder="Bagaimana pelayanannya?" :rows="3"/>
                <input type="hidden" name="dimension_ratings[quality]" value="5">
                <input type="hidden" name="dimension_ratings[communication]" value="5">
                <input type="hidden" name="dimension_ratings[value]" value="5">
                <x-ui.button type="submit" variant="secondary" full>Kirim Ulasan</x-ui.button>
            </form>
        @endif

        @if(in_array($order->status, ['pending_payment', 'paid', 'searching_provider', 'offered', 'accepted']))
            <x-ui.confirmation-dialog title="Batalkan pesanan?" description="Dana akan dikembalikan sesuai kebijakan refund." action="{{ route('web.orders.cancel', $order->id) }}" confirmLabel="Ya, batalkan" cancelLabel="Kembali" danger>
                <input type="hidden" name="reason" value="Dibatalkan oleh customer"/>
                <span class="block w-full rounded-xl border border-rose-300 py-2.5 text-center text-sm font-semibold text-rose-600 hover:bg-rose-50">Batalkan Pesanan</span>
            </x-ui.confirmation-dialog>
        @endif
    </aside>
</div>
@endsection
