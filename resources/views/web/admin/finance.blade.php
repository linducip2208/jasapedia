@extends('layouts.admin')

@section('title', 'Admin Keuangan | Jasapedia')

@section('admin-content')
@php $diff = $debits - $credits; @endphp
<h1 class="h3 fw-bolder">Keuangan & Ledger</h1>

<div class="row g-3 mt-1">
    <div class="col-sm-4">
        <div class="card stat-card h-100"><div class="card-body">
            <p class="stat-label text-body-secondary mb-1">Total Debit</p>
            <p class="stat-value mb-0">{{ (new \App\Support\Money\Money($debits))->format() }}</p>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card h-100"><div class="card-body">
            <p class="stat-label text-body-secondary mb-1">Total Kredit</p>
            <p class="stat-value mb-0">{{ (new \App\Support\Money\Money($credits))->format() }}</p>
        </div></div>
    </div>
    <div class="col-sm-4">
        <div class="card stat-card h-100 border {{ $diff === 0 ? 'border-success' : 'border-danger' }}"><div class="card-body">
            <p class="stat-label text-body-secondary mb-1">Balanced?</p>
            <p class="stat-value mb-0 {{ $diff === 0 ? 'text-success' : 'text-danger' }}">{{ $diff === 0 ? 'YES' : 'DIFF '.(new \App\Support\Money\Money(abs($diff)))->format() }}</p>
        </div></div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-xl-6">
        <section class="card h-100">
            <h2 class="card-header fw-bold h6 mb-0">Withdrawal Requests</h2>
            <ul class="list-group list-group-flush">
                @forelse($withdrawals as $w)
                    <li class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div>
                            <p class="fw-bold mb-0">{{ (new \App\Support\Money\Money((int) $w->amount))->format() }}</p>
                            <p class="small text-body-secondary mb-0">{{ $w->partner?->display_name }} · {{ $w->created_at->translatedFormat('d M Y') }}</p>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <x-admin.status-badge :status="$w->status"/>
                            @if(in_array($w->status, ['requested', 'under_review', 'approved', 'processing']))
                                <form method="POST" action="{{ route('web.admin.withdrawals.action', $w->id) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="action" value="{{ ['requested' => 'under_review', 'under_review' => 'approved', 'approved' => 'processing', 'processing' => 'completed'][$w->status] }}">
                                    <button class="btn btn-sm btn-brand">{{ ['requested' => 'Review', 'under_review' => 'Approve', 'approved' => 'Proses', 'processing' => 'Selesaikan'][$w->status] }}</button>
                                </form>
                                <form method="POST" action="{{ route('web.admin.withdrawals.action', $w->id) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="action" value="rejected">
                                    <button class="btn btn-sm btn-outline-secondary">Tolak</button>
                                </form>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="list-group-item text-center text-body-secondary py-5">Belum ada penarikan.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <div class="col-xl-6">
        <section class="card h-100">
            <h2 class="card-header fw-bold h6 mb-0">Settlements</h2>
            <ul class="list-group list-group-flush">
                @forelse($settlements as $s)
                    <li class="list-group-item d-flex align-items-center justify-content-between gap-3">
                        <div>
                            <p class="fw-bold mb-0">{{ (new \App\Support\Money\Money((int) $s->vendor_net))->format() }}</p>
                            <p class="small text-body-secondary mb-0">Order {{ $s->order?->code }} · komisi {{ (new \App\Support\Money\Money((int) $s->commission))->format() }}</p>
                        </div>
                        <x-admin.status-badge :status="$s->status"/>
                    </li>
                @empty
                    <li class="list-group-item text-center text-body-secondary py-5">Belum ada settlement.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
@endsection
