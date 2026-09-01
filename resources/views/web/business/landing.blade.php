@extends('layouts.app')

@section('title', 'Jasapedia Business — Pengadaan Jasa Perusahaan | Jasapedia')
@section('meta_description', 'Platform pengadaan jasa untuk perusahaan: approval berjenjang, RFQ terstruktur, PO reference, dan vendor terverifikasi.')

@section('content')
<section class="overflow-hidden rounded-3xl bg-slate-900 px-6 py-14 text-white sm:px-12">
    <div class="mx-auto max-w-2xl text-center">
        <x-ui.badge tone="teal" class="mb-4">Untuk Perusahaan</x-ui.badge>
        <h1 class="text-3xl font-extrabold sm:text-4xl">Jasapedia Business</h1>
        <p class="mx-auto mt-3 max-w-xl text-slate-300">Pengadaan jasa yang rapi: karyawan ajukan, manajer approve, finance kontrol budget. Semua tercatat dengan PO reference.</p>
        <div class="mt-6 flex flex-wrap justify-center gap-3">
            <a href="{{ route('web.business.dashboard') }}" class="rounded-xl bg-teal-500 px-6 py-3 font-bold text-white hover:bg-teal-400">Buka Dashboard Business</a>
            <a href="{{ route('web.page', 'jasapedia-business') }}" class="rounded-xl border border-slate-600 px-6 py-3 font-bold hover:border-teal-400">Pelajari Cara Kerja</a>
        </div>
    </div>
</section>

<section class="mt-6 grid gap-3 sm:grid-cols-3">
    @php
        $features = [
            ['Approval Berjenjang', 'Employee → Manager → Finance, sesuai threshold yang ditentukan perusahaan.'],
            ['RFQ Terstruktur', 'Bandingkan vendor pada harga, scope, dan timeline dalam satu tempat.'],
            ['Kontrol Budget', 'Departemen & cost center terpisah, laporan pengeluaran transparan.'],
        ];
    @endphp
    @foreach($features as [$title, $desc])
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-bold text-slate-900">{{ $title }}</h2>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-500">{{ $desc }}</p>
        </div>
    @endforeach
</section>
@endsection
