@extends('layouts.admin')

@section('title', 'Admin Dashboard | Jasapedia')

@section('admin-content')
<h1 class="text-xl font-extrabold text-white">Command Center</h1>
<p class="text-sm text-slate-400">Semua angka dihitung real-time dari database.</p>

{{-- Financial metrics --}}
<div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-6">
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">GMV</p><p class="mt-1 text-xl font-extrabold text-teal-400">{{ (new \App\Support\Money\Money($gmv))->format() }}</p></div>
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Total Order</p><p class="mt-1 text-xl font-extrabold text-white">{{ number_format($orders) }}</p></div>
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Order Selesai</p><p class="mt-1 text-xl font-extrabold text-white">{{ number_format($completedOrders) }}</p></div>
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Order Aktif</p><p class="mt-1 text-xl font-extrabold text-amber-400">{{ number_format($activeOrders) }}</p></div>
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Cancel Rate</p><p class="mt-1 text-xl font-extrabold text-rose-400">{{ $cancelRate }}%</p></div>
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Dispute Rate</p><p class="mt-1 text-xl font-extrabold text-rose-400">{{ $disputeRate }}%</p></div>
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Provider Verified</p><p class="mt-1 text-xl font-extrabold text-white">{{ number_format($activeProviders) }}</p></div>
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Customers</p><p class="mt-1 text-xl font-extrabold text-white">{{ number_format($customers) }}</p></div>
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Komisi (ledger)</p><p class="mt-1 text-xl font-extrabold text-teal-400">{{ (new \App\Support\Money\Money($commission))->format() }}</p></div>
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Settlement Pending</p><p class="mt-1 text-xl font-extrabold text-amber-400">{{ number_format($pendingSettlement) }}</p></div>
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Withdrawal Pending</p><p class="mt-1 text-xl font-extrabold text-amber-400">{{ number_format($pendingWithdrawal) }}</p></div>
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">KYC Pending</p><p class="mt-1 text-xl font-extrabold text-white">{{ number_format($kycPending) }}</p></div>
</div>

{{-- Operations --}}
<div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Mencari Provider</p><p class="mt-1 text-lg font-extrabold text-white">{{ number_format($searchingProvider) }}</p></div>
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Menuju Lokasi</p><p class="mt-1 text-lg font-extrabold text-white">{{ number_format($onTheWay) }}</p></div>
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Sedang Kerja</p><p class="mt-1 text-lg font-extrabold text-white">{{ number_format($working) }}</p></div>
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Nunggu Konfirmasi</p><p class="mt-1 text-lg font-extrabold text-white">{{ number_format($awaitingConfirmation) }}</p></div>
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Sengketa Terbuka</p><p class="mt-1 text-lg font-extrabold text-rose-400">{{ number_format($disputesOpen) }}</p></div>
</div>

{{-- Charts: real data, pure CSS bars --}}
<div class="mt-6 grid gap-4 lg:grid-cols-2">
    <div class="rounded-2xl bg-slate-900 p-5 ring-1 ring-slate-800">
        <h2 class="text-sm font-bold text-white">Volume Order — 14 hari</h2>
        @php $maxOrders = max(1, max(array_column($orderSeries, 'count') ?: [1])); @endphp
        <div class="mt-4 flex h-32 items-end gap-1.5">
            @forelse($orderSeries as $point)
                <div class="flex-1 rounded-t bg-teal-500/70" style="height: {{ max(4, $point['count'] / $maxOrders * 100) }}%" title="{{ $point['date'] }}: {{ $point['count'] }}"></div>
            @empty
                <p class="text-sm text-slate-500">Belum ada data order.</p>
            @endforelse
        </div>
    </div>
    <div class="rounded-2xl bg-slate-900 p-5 ring-1 ring-slate-800">
        <h2 class="text-sm font-bold text-white">GMV Harian — 14 hari</h2>
        @php $maxGmv = max(1, max(array_column($gmvSeries, 'total') ?: [1])); @endphp
        <div class="mt-4 flex h-32 items-end gap-1.5">
            @forelse($gmvSeries as $point)
                <div class="flex-1 rounded-t bg-amber-400/70" style="height: {{ max(4, $point['total'] / $maxGmv * 100) }}%" title="{{ $point['date'] }}: {{ (new \App\Support\Money\Money($point['total']))->format() }}"></div>
            @empty
                <p class="text-sm text-slate-500">Belum ada transaksi.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
