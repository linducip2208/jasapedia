@extends('layouts.partner')

@section('title', 'Keuangan | Jasapedia')

@section('partner-content')
<h1 class="text-xl font-extrabold text-slate-900">Keuangan Partner</h1>

<div class="mt-5 grid gap-3 sm:grid-cols-3">
    <div class="rounded-2xl bg-gradient-to-br from-teal-600 to-teal-800 p-5 text-white shadow-sm">
        <p class="text-xs font-bold uppercase text-teal-100">Saldo Tersedia</p>
        <p class="mt-1.5 text-2xl font-extrabold">{{ (new \App\Support\Money\Money($available))->format() }}</p>
        <p class="mt-1 text-xs text-teal-100">Bisa ditarik kapan saja</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-bold uppercase text-slate-400">Menunggu Settlement</p>
        <p class="mt-1.5 text-2xl font-extrabold text-slate-900">{{ (new \App\Support\Money\Money($pending))->format() }}</p>
        <p class="mt-1 text-xs text-slate-400">Masuk setelah pesanan selesai</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-bold uppercase text-slate-400">Rekening Tujuan</p>
        <p class="mt-1.5 text-2xl font-extrabold text-slate-900">{{ $payouts->count() }}</p>
        @if($payouts->isEmpty())<p class="mt-1 text-xs text-amber-600">Belum diatur — wajib sebelum penarikan</p>@endif
    </div>
</div>

<div class="mt-6 grid gap-5 lg:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-bold text-slate-900">Tarik Dana</h2>
        <form method="POST" action="{{ route('web.partner.finance.withdraw') }}" class="mt-3 space-y-3">
            @csrf
            <x-ui.input name="amount" label="Jumlah (Rp)" type="number" min="50000" :value="max(50000, $available)" hint="Minimal Rp50.000" required/>
            <x-ui.select name="payout_destination_id" label="Rekening tujuan" required>
                <option value="">Pilih rekening...</option>
                @foreach($payouts as $p)
                    <option value="{{ $p->id }}">{{ strtoupper($p->type) }} {{ $p->account_number }} a.n. {{ $p->account_name }}{{ $p->verified_at ? '' : ' (belum terverifikasi)' }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.button type="submit" full>Ajukan Penarikan</x-ui.button>
        </form>

        <form method="POST" action="{{ route('web.partner.finance.payout') }}" class="mt-5 space-y-3 border-t border-slate-100 pt-4">
            @csrf
            <p class="text-sm font-bold text-slate-800">Tambah Rekening Tujuan</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.select name="type" label="Jenis">
                    <option value="bank">Bank</option>
                    <option value="ewallet">E-wallet</option>
                </x-ui.select>
                <x-ui.input name="bank_code" label="Kode bank (opsional)" placeholder="BCA / BRI"/>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.input name="account_number" label="No. rekening" required/>
                <x-ui.input name="account_name" label="Nama pemilik" required/>
            </div>
            <x-ui.button type="submit" variant="outline" size="sm">Simpan Rekening</x-ui.button>
        </form>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-bold text-slate-900">Riwayat Penarikan</h2>
        <div class="mt-3 space-y-2.5">
            @forelse($withdrawals as $w)
                <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-3.5">
                    <div>
                        <p class="text-sm font-bold"><x-ui.money :amount="$w->amount"/></p>
                        <p class="text-xs text-slate-400">{{ $w->created_at->translatedFormat('d M Y, H:i') }}</p>
                    </div>
                    <x-ui.status-badge :status="$w->status"/>
                </div>
            @empty
                <p class="text-sm text-slate-400">Belum ada penarikan.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
