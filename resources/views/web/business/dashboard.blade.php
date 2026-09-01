@extends('layouts.app')

@section('title', 'Dashboard Business | Jasapedia')

@section('content')
<h1 class="text-xl font-extrabold text-slate-900">Jasapedia Business</h1>

@if($organizations->isEmpty())
    <div class="mt-5">
        <x-ui.empty-state title="Belum terhubung ke organisasi" description="Buat organisasi bisnis untuk mulai menggunakan pengadaan jasa korporat.">
            <form method="POST" action="{{ route('web.business.org.create') }}" class="mx-auto mt-4 flex max-w-md flex-col gap-2.5">
                @csrf
                <x-ui.input name="name" label="Nama perusahaan" required/>
                <div class="grid gap-2.5 sm:grid-cols-2">
                    <x-ui.input name="npwp" label="NPWP (opsional)"/>
                    <x-ui.input name="billing_email" label="Email billing (opsional)" type="email"/>
                </div>
                <x-ui.button type="submit">Buat Organisasi</x-ui.button>
            </form>
        </x-ui.empty-state>
    </div>
@else
    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-slate-400">Organisasi</p><p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $organizations->count() }}</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-slate-400">Menunggu Approval</p><p class="mt-1 text-2xl font-extrabold text-amber-600">{{ $pendingApprovals }}</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-slate-400">Request Aktif</p><p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $activeRequests }}</p></div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-slate-400">Dikonversi ke Order</p><p class="mt-1 text-2xl font-extrabold text-teal-700">{{ $convertedRequests }}</p></div>
    </div>

    <div class="mt-6 grid gap-5 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-bold text-slate-900">Service Requests</h2>
            <div class="mt-3 space-y-2.5">
                @forelse($requests as $req)
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-3.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-slate-800">{{ $req->title }}</p>
                            <p class="text-xs text-slate-400">{{ $req->code }} @if($req->po_reference)· PO {{ $req->po_reference }}@endif</p>
                        </div>
                        <x-ui.status-badge :status="$req->status"/>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">Belum ada request.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('web.business.request.create') }}" class="mt-5 space-y-3 border-t border-slate-100 pt-4">
                @csrf
                <p class="text-sm font-bold text-slate-800">Buat Service Request</p>
                <x-ui.select name="organization_id" label="Organisasi" required>
                    <option value="">Pilih organisasi...</option>
                    @foreach($organizations as $org)
                        <option value="{{ $org->id }}">{{ $org->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input name="title" label="Judul" required/>
                <x-ui.textarea name="description" label="Kebutuhan" :rows="3" required/>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-ui.input name="estimated_amount" label="Estimasi (Rp)" type="number" min="0"/>
                    <x-ui.input name="po_reference" label="PO reference (opsional)"/>
                </div>
                <x-ui.button type="submit" size="sm">Ajukan Request</x-ui.button>
            </form>
        </section>

        <section class="space-y-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-bold text-slate-900">RFQ Terbaru</h2>
                <div class="mt-3 space-y-2.5">
                    @forelse($rfqs as $rfq)
                        <a href="{{ route('web.requests.show', $rfq->id) }}" class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-3.5 hover:bg-slate-100">
                            <p class="min-w-0 truncate text-sm font-semibold">{{ $rfq->title }}</p>
                            <x-ui.status-badge :status="$rfq->status" type="project"/>
                        </a>
                    @empty
                        <p class="text-sm text-slate-400">Belum ada RFQ.</p>
                    @endforelse
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-bold text-slate-900">Order Terbaru</h2>
                <div class="mt-3 space-y-2.5">
                    @forelse($orders as $order)
                        <a href="{{ route('web.orders.show', $order->id) }}" class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-3.5 hover:bg-slate-100">
                            <p class="min-w-0 truncate text-sm font-semibold">{{ $order->service?->title ?? $order->code }}</p>
                            <x-ui.status-badge :status="$order->status"/>
                        </a>
                    @empty
                        <p class="text-sm text-slate-400">Belum ada order.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@endif
@endsection
