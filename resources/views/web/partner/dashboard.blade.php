@extends('layouts.partner')

@section('title', 'Partner Center | Jasapedia')

@section('partner-content')
<h1 class="text-xl font-extrabold text-slate-900">Ringkasan Bisnis</h1>

@if($partner->verification_state !== 'verified')
    <div class="mt-4">
        <x-ui.alert tone="warning" title="Verifikasi belum selesai">
            Status: {{ $partner->verification_state }}. Lengkapi KYC untuk mulai menerima order.
            <a href="{{ route('web.partner.kyc.submit') }}" class="font-bold underline" onclick="document.getElementById('kyc-form').submit(); return false;">Ajukan sekarang</a>
        </x-ui.alert>
        <form id="kyc-form" method="POST" action="{{ route('web.partner.kyc.submit') }}" class="hidden">@csrf</form>
    </div>
@endif

<div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-5">
    <a href="{{ route('web.partner.orders') }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition"><p class="text-xs font-bold uppercase text-slate-400">Penawaran Tertunda</p><p class="mt-1 text-2xl font-extrabold text-amber-600">{{ $pendingOffers }}</p></a>
    <a href="{{ route('web.partner.orders') }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition"><p class="text-xs font-bold uppercase text-slate-400">Job Hari Ini</p><p class="mt-1 text-2xl font-extrabold text-slate-900">{{ $todayJobs }}</p></a>
    <a href="{{ route('web.partner.orders') }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition"><p class="text-xs font-bold uppercase text-slate-400">Sedang Jalan</p><p class="mt-1 text-2xl font-extrabold text-teal-700">{{ $inProgress }}</p></a>
    <a href="{{ route('web.partner.finance') }}" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:-translate-y-0.5 hover:shadow-md transition"><p class="text-xs font-bold uppercase text-slate-400">Pendapatan Bulan Ini</p><p class="mt-1 text-2xl font-extrabold text-slate-900">{{ (new \App\Support\Money\Money((int) $monthEarnings))->format() }}</p></a>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-slate-400">Rating</p><p class="mt-1 text-2xl font-extrabold text-amber-500">{{ number_format($rating, 1) }}</p></div>
</div>

<div class="mt-8 grid gap-5 lg:grid-cols-2">
    <section>
        <h2 class="font-bold text-slate-800">Order Baru</h2>
        <div class="mt-3 space-y-3">
            @forelse($newOrders as $order)
                <x-ui.order-card :order="$order"/>
            @empty
                <p class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-400">Belum ada order baru.</p>
            @endforelse
        </div>
    </section>
    <section>
        <h2 class="font-bold text-slate-800">Job Aktif</h2>
        <div class="mt-3 space-y-3">
            @forelse($activeJobs as $order)
                <x-ui.order-card :order="$order"/>
            @empty
                <p class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-400">Tidak ada job berjalan.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
