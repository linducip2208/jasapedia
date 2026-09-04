@extends('layouts.admin')

@section('title', 'Admin Dashboard | Jasapedia')

@section('admin-content')
<div class="d-flex flex-wrap align-items-baseline gap-2">
    <h1 class="h3 fw-bolder mb-0">Command Center</h1>
</div>
<p class="text-body-secondary small mb-4">Semua angka dihitung real-time dari database.</p>

<div class="row g-3">
    <div class="col-6 col-sm-4 col-xl-2">
        <div class="card stat-card h-100"><div class="card-body">
            <p class="stat-label text-body-secondary mb-1">GMV</p>
            <p class="stat-value text-brand mb-0">{{ (new \App\Support\Money\Money($gmv))->format() }}</p>
        </div></div>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <div class="card stat-card h-100"><div class="card-body">
            <p class="stat-label text-body-secondary mb-1">Total Order</p>
            <p class="stat-value mb-0">{{ number_format($orders) }}</p>
        </div></div>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <div class="card stat-card h-100"><div class="card-body">
            <p class="stat-label text-body-secondary mb-1">Order Selesai</p>
            <p class="stat-value mb-0">{{ number_format($completedOrders) }}</p>
        </div></div>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <div class="card stat-card h-100"><div class="card-body">
            <p class="stat-label text-body-secondary mb-1">Order Aktif</p>
            <p class="stat-value text-warning mb-0">{{ number_format($activeOrders) }}</p>
        </div></div>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <div class="card stat-card h-100"><div class="card-body">
            <p class="stat-label text-body-secondary mb-1">Cancel Rate</p>
            <p class="stat-value text-danger mb-0">{{ $cancelRate }}%</p>
        </div></div>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <div class="card stat-card h-100"><div class="card-body">
            <p class="stat-label text-body-secondary mb-1">Dispute Rate</p>
            <p class="stat-value text-danger mb-0">{{ $disputeRate }}%</p>
        </div></div>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <div class="card stat-card h-100"><div class="card-body">
            <p class="stat-label text-body-secondary mb-1">Provider Verified</p>
            <p class="stat-value mb-0">{{ number_format($activeProviders) }}</p>
        </div></div>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <div class="card stat-card h-100"><div class="card-body">
            <p class="stat-label text-body-secondary mb-1">Customers</p>
            <p class="stat-value mb-0">{{ number_format($customers) }}</p>
        </div></div>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <div class="card stat-card h-100"><div class="card-body">
            <p class="stat-label text-body-secondary mb-1">Komisi (ledger)</p>
            <p class="stat-value text-brand mb-0">{{ (new \App\Support\Money\Money($commission))->format() }}</p>
        </div></div>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <div class="card stat-card h-100"><div class="card-body">
            <p class="stat-label text-body-secondary mb-1">Settlement Pending</p>
            <p class="stat-value text-warning mb-0">{{ number_format($pendingSettlement) }}</p>
        </div></div>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <div class="card stat-card h-100"><div class="card-body">
            <p class="stat-label text-body-secondary mb-1">Withdrawal Pending</p>
            <p class="stat-value text-warning mb-0">{{ number_format($pendingWithdrawal) }}</p>
        </div></div>
    </div>
    <div class="col-6 col-sm-4 col-xl-2">
        <div class="card stat-card h-100"><div class="card-body">
            <p class="stat-label text-body-secondary mb-1">KYC Pending</p>
            <p class="stat-value mb-0">{{ number_format($kycPending) }}</p>
        </div></div>
    </div>
</div>

<h2 class="h6 fw-bold text-uppercase text-body-secondary mt-4">Operasi Lapangan</h2>
<div class="row g-3">
    <div class="col-6 col-sm-4 col-xl-2"><div class="card stat-card h-100"><div class="card-body"><p class="stat-label text-body-secondary mb-1">Mencari Provider</p><p class="stat-value mb-0">{{ number_format($searchingProvider) }}</p></div></div></div>
    <div class="col-6 col-sm-4 col-xl-2"><div class="card stat-card h-100"><div class="card-body"><p class="stat-label text-body-secondary mb-1">Menuju Lokasi</p><p class="stat-value mb-0">{{ number_format($onTheWay) }}</p></div></div></div>
    <div class="col-6 col-sm-4 col-xl-2"><div class="card stat-card h-100"><div class="card-body"><p class="stat-label text-body-secondary mb-1">Sedang Kerja</p><p class="stat-value mb-0">{{ number_format($working) }}</p></div></div></div>
    <div class="col-6 col-sm-4 col-xl-2"><div class="card stat-card h-100"><div class="card-body"><p class="stat-label text-body-secondary mb-1">Nunggu Konfirmasi</p><p class="stat-value mb-0">{{ number_format($awaitingConfirmation) }}</p></div></div></div>
    <div class="col-6 col-sm-4 col-xl-2"><div class="card stat-card h-100"><div class="card-body"><p class="stat-label text-body-secondary mb-1">Sengketa Terbuka</p><p class="stat-value text-danger mb-0">{{ number_format($disputesOpen) }}</p></div></div></div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-bold">Volume Order — 14 hari</div>
            <div class="card-body">
                @php $maxOrders = max(1, max(array_column($orderSeries, 'count') ?: [1])); @endphp
                <div class="d-flex align-items-end gap-1" style="height: 8rem;">
                    @forelse($orderSeries as $point)
                        <div class="flex-fill rounded-top" style="height: {{ max(4, $point['count'] / $maxOrders * 100) }}%; background: rgba(20, 184, 166, 0.7);" title="{{ $point['date'] }}: {{ $point['count'] }}"></div>
                    @empty
                        <p class="text-body-secondary small mb-0">Belum ada data order.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header fw-bold">GMV Harian — 14 hari</div>
            <div class="card-body">
                @php $maxGmv = max(1, max(array_column($gmvSeries, 'total') ?: [1])); @endphp
                <div class="d-flex align-items-end gap-1" style="height: 8rem;">
                    @forelse($gmvSeries as $point)
                        <div class="flex-fill rounded-top" style="height: {{ max(4, $point['total'] / $maxGmv * 100) }}%; background: rgba(251, 191, 36, 0.7);" title="{{ $point['date'] }}: {{ (new \App\Support\Money\Money($point['total']))->format() }}"></div>
                    @empty
                        <p class="text-body-secondary small mb-0">Belum ada transaksi.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
