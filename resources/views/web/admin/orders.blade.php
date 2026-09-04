@extends('layouts.admin')

@section('title', 'Admin Pesanan | Jasapedia')

@section('admin-content')
<h1 class="h3 fw-bolder">Pesanan</h1>

<div class="card mt-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr><th scope="col">Kode</th><th scope="col">Customer</th><th scope="col">Provider</th><th scope="col">Jasa</th><th scope="col">Status</th><th scope="col" class="text-end">Total</th></tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td class="font-monospace small">{{ $order->code }}</td>
                        <td>{{ $order->user?->name }}</td>
                        <td>{{ $order->partner?->display_name ?? '-' }}</td>
                        <td class="text-body-secondary" style="max-width: 220px;"><span class="d-inline-block text-truncate" style="max-width: 220px;">{{ $order->service?->title ?? '-' }}</span></td>
                        <td><x-admin.status-badge :status="$order->status"/></td>
                        <td class="text-end fw-bold">{{ (new \App\Support\Money\Money((int) $order->total))->format() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-5">Belum ada pesanan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<x-admin.pagination :paginator="$orders"/>
@endsection
