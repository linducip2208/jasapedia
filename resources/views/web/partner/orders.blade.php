@extends('layouts.partner')

@section('title', 'Pesanan Partner | Jasapedia')

@section('partner-content')
<h1 class="text-xl font-extrabold text-slate-900">Pesanan Masuk</h1>

<div class="mt-4 flex gap-2 overflow-x-auto pb-1">
    @php $stati = ['', 'pending_payment', 'paid', 'searching_provider', 'accepted', 'assigned', 'on_the_way', 'working', 'awaiting_customer_confirmation', 'completed', 'settled', 'cancelled']; @endphp
    @foreach($stati as $s)
        <a href="{{ $s ? route('web.partner.orders', ['status' => $s]) : route('web.partner.orders') }}"
            class="whitespace-nowrap rounded-full px-3.5 py-1.5 text-xs font-bold {{ request('status') === $s || ($s === '' && ! request('status')) ? 'bg-teal-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200' }}">
            {{ $s ? __('status.'.$s) : 'Semua' }}
        </a>
    @endforeach
</div>

<div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-400">
                <tr><th class="px-4 py-3">Kode</th><th class="px-4 py-3">Jasa</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Total</th><th class="px-4 py-3">Aksi</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($orders as $order)
                    <tr class="hover:bg-slate-50/60">
                        <td class="px-4 py-3 font-mono text-xs">{{ $order->code }}</td>
                        <td class="max-w-[220px] truncate px-4 py-3">{{ $order->service?->title ?? '-' }}</td>
                        <td class="px-4 py-3"><x-ui.status-badge :status="$order->status"/></td>
                        <td class="px-4 py-3 font-bold"><x-ui.money :amount="$order->total"/></td>
                        <td class="px-4 py-3">
                            @php
                                $next = match ($order->status) {
                                    'paid', 'searching_provider' => ['accept', 'Terima', 'teal'],
                                    'assigned' => ['arrive', 'Menuju Lokasi', 'indigo'],
                                    'on_the_way', 'arrived' => ['start', 'Mulai Kerja', 'teal'],
                                    'checked_in', 'working' => ['submit', 'Selesaikan', 'amber'],
                                    default => null,
                                };
                            @endphp
                            @if($next)
                                <form method="POST" action="{{ route('web.partner.orders.action', $order->id) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="action" value="{{ $next[0] }}">
                                    <button class="rounded-lg bg-{{ $next[2] }}-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-{{ $next[2] }}-700">{{ $next[1] }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Tidak ada pesanan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<x-ui.pagination :paginator="$orders"/>
@endsection
