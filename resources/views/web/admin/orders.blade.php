@extends('layouts.admin')

@section('title', 'Admin Pesanan | Jasapedia')

@section('admin-content')
<h1 class="text-xl font-extrabold text-white">Pesanan</h1>

<div class="mt-4 overflow-x-auto rounded-2xl ring-1 ring-slate-800">
    <table class="w-full min-w-[720px] text-sm">
        <thead class="bg-slate-900 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr><th class="px-4 py-3">Kode</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Provider</th><th class="px-4 py-3">Jasa</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Total</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-800 bg-slate-900/40">
            @forelse($orders as $order)
                <tr class="hover:bg-slate-800/40">
                    <td class="px-4 py-3 font-mono text-xs text-slate-300">{{ $order->code }}</td>
                    <td class="px-4 py-3">{{ $order->user?->name }}</td>
                    <td class="px-4 py-3">{{ $order->partner?->display_name ?? '-' }}</td>
                    <td class="max-w-[200px] truncate px-4 py-3 text-slate-400">{{ $order->service?->title ?? '-' }}</td>
                    <td class="px-4 py-3"><x-ui.status-badge :status="$order->status"/></td>
                    <td class="px-4 py-3 font-bold text-white">{{ (new \App\Support\Money\Money((int) $order->total))->format() }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada pesanan.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<x-ui.pagination :paginator="$orders"/>
@endsection
