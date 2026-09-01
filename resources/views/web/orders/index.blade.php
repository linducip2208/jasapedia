@extends('layouts.app')

@section('title', 'Pesanan Saya')

@section('content')
<h1 class="text-xl font-bold">Pesanan Saya</h1>
<div class="mt-4 space-y-3">
    @forelse ($orders as $order)
    <a href="{{ route('web.orders.show', $order->id) }}" class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4 hover:shadow-sm">
        <div>
            <p class="text-sm font-semibold">{{ $order->service?->title ?? $order->code }}</p>
            <p class="text-xs text-slate-500">{{ $order->code }} · {{ $order->created_at->translatedFormat('d M Y H:i') }}</p>
        </div>
        <div class="text-right">
            <span class="inline-block rounded-full px-3 py-1 text-xs font-semibold
                @if(in_array($order->status, ['completed','settled','closed'])) bg-emerald-50 text-emerald-700
                @elseif(in_array($order->status, ['cancelled','expired','failed','refunded'])) bg-rose-50 text-rose-600
                @elseif($order->status === 'disputed') bg-amber-100 text-amber-800
                @else bg-indigo-50 text-indigo-700 @endif">
                {{ __('status.'.$order->status) }}
            </span>
            <p class="mt-1 text-sm font-bold">Rp{{ number_format($order->total, 0, ',', '.') }}</p>
        </div>
    </a>
    @empty
    <div class="rounded-xl border border-dashed border-slate-300 p-12 text-center">
        <p class="text-3xl">📦</p>
        <p class="mt-2 font-semibold">Belum ada pesanan</p>
        <a href="{{ route('web.explore') }}" class="mt-1 text-sm font-medium text-indigo-600 hover:underline">Mulai cari jasa →</a>
    </div>
    @endforelse
</div>
<div class="mt-6">{{ $orders->links() }}</div>
@endsection
