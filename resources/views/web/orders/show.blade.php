@extends('layouts.app')

@section('title', 'Pesanan '.$order->code)

@section('content')
<div class="grid gap-6 lg:grid-cols-[1fr_320px]">
    <div class="space-y-6">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="font-bold">{{ $order->service?->title ?? $order->code }}</h1>
                    <p class="text-xs text-slate-500">{{ $order->code }}</p>
                </div>
                <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">{{ __('status.'.$order->status) }}</span>
            </div>

            {{-- Timeline --}}
            <ol class="mt-6 space-y-3 border-l-2 border-slate-200 pl-5">
                @foreach($order->history as $entry)
                <li class="relative">
                    <span class="absolute -left-[27px] h-4 w-4 rounded-full border-2 border-white @if($loop->last) bg-indigo-600 @else bg-slate-300 @endif"></span>
                    <p class="text-sm font-semibold">{{ __('status.'.$entry->to_status) }}</p>
                    <p class="text-xs text-slate-500">{{ $entry->created_at->translatedFormat('d M Y H:i') }} @if($entry->reason) · {{ $entry->reason }} @endif</p>
                </li>
                @endforeach
            </ol>
        </div>

        {{-- Items --}}
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="font-bold">Rincian</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach($order->items as $item)
                <li class="flex justify-between"><span>{{ $item->name }} @if($item->qty > 1) × {{ $item->qty }} @endif</span><span>Rp{{ number_format($item->amount, 0, ',', '.') }}</span></li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Action card --}}
    <aside class="h-fit space-y-3 rounded-xl border border-slate-200 bg-white p-5" x-data>
        <p class="text-xl font-bold">Rp{{ number_format($order->total, 0, ',', '.') }}</p>

        @if($order->status === 'pending_payment')
        <form method="POST" action="{{ route('web.orders.pay', $order->id) }}">
            @csrf
            <button class="w-full rounded-full bg-indigo-600 py-2.5 font-bold text-white hover:bg-indigo-700">Bayar Sekarang</button>
        </form>
        @endif

        @if($order->status === 'awaiting_customer_confirmation')
        <form method="POST" action="{{ route('web.orders.confirm', $order->id) }}">
            @csrf
            <button class="w-full rounded-full bg-emerald-600 py-2.5 font-bold text-white hover:bg-emerald-700">Konfirmasi Selesai</button>
        </form>
        @endif

        @if(in_array($order->status, ['arrived']))
        <form method="POST" action="{{ route('web.orders.checkin', $order->id) }}">
            @csrf
            <label class="block text-sm font-medium">Kode OTP teknisi</label>
            <input name="otp" maxlength="6" class="mt-1 w-full rounded-lg border-slate-300 text-center text-lg tracking-widest" required>
            <button class="mt-2 w-full rounded-full bg-indigo-600 py-2.5 font-bold text-white hover:bg-indigo-700">Konfirmasi Kehadiran</button>
        </form>
        @endif

        @if($order->canCancel && in_array($order->status, ['pending_payment','paid','searching_provider','offered','accepted']))
        <form method="POST" action="{{ route('web.orders.cancel', $order->id) }}" x-data="{ show: false }">
            @csrf
            <button type="button" @click="show = !show" class="w-full rounded-full border border-rose-300 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50">Batalkan Pesanan</button>
            <div x-show="show" x-cloak class="mt-2">
                <input name="reason" placeholder="Alasan pembatalan" class="w-full rounded-lg border-slate-300" required>
                <button class="mt-2 w-full rounded-full bg-rose-600 py-2 text-sm font-bold text-white hover:bg-rose-700">Ya, batalkan</button>
            </div>
        </form>
        @endif

        @if(in_array($order->status, ['completed','settled','closed']) && !$order->review)
        <form method="POST" action="{{ route('web.orders.review', $order->id) }}" class="space-y-2 border-t pt-3">
            @csrf
            <p class="text-sm font-semibold">Beri ulasan</p>
            <select name="overall" class="w-full rounded-lg border-slate-300">
                @foreach(range(5,1) as $s)<option value="{{ $s }}">{{ $s }} ★</option>@endforeach
            </select>
            <textarea name="comment" rows="2" placeholder="Bagaimana pelayanannya?" class="w-full rounded-lg border-slate-300"></textarea>
            <input type="hidden" name="dimension_ratings[quality]" value="5">
            <input type="hidden" name="dimension_ratings[communication]" value="5">
            <input type="hidden" name="dimension_ratings[value]" value="5">
            <button class="w-full rounded-full bg-amber-500 py-2 text-sm font-bold text-white hover:bg-amber-600">Kirim Ulasan</button>
        </form>
        @endif
    </aside>
</div>
@endsection
