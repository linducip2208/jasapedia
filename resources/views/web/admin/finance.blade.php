@extends('layouts.admin')

@section('title', 'Admin Keuangan | Jasapedia')

@section('admin-content')
@php $diff = $debits - $credits; @endphp
<h1 class="text-xl font-extrabold text-white">Keuangan & Ledger</h1>

<div class="mt-4 grid gap-3 sm:grid-cols-3">
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase text-slate-500">Total Debit</p><p class="mt-1 text-xl font-extrabold text-white">{{ (new \App\Support\Money\Money($debits))->format() }}</p></div>
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 ring-slate-800"><p class="text-[10px] font-black uppercase text-slate-500">Total Kredit</p><p class="mt-1 text-xl font-extrabold text-white">{{ (new \App\Support\Money\Money($credits))->format() }}</p></div>
    <div class="rounded-2xl bg-slate-900 p-4 ring-1 {{ $diff === 0 ? 'ring-emerald-700/60' : 'ring-rose-700/60' }}">
        <p class="text-[10px] font-black uppercase text-slate-500">Balanced?</p>
        <p class="mt-1 text-xl font-extrabold {{ $diff === 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $diff === 0 ? 'YES' : 'DIFF '.(new \App\Support\Money\Money(abs($diff)))->format() }}</p>
    </div>
</div>

<div class="mt-6 grid gap-5 xl:grid-cols-2">
    <section class="rounded-2xl ring-1 ring-slate-800">
        <h2 class="border-b border-slate-800 px-5 py-3.5 font-bold text-white">Withdrawal Requests</h2>
        <div class="divide-y divide-slate-800">
            @forelse($withdrawals as $w)
                <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-3.5">
                    <div>
                        <p class="font-bold text-white">{{ (new \App\Support\Money\Money((int) $w->amount))->format() }}</p>
                        <p class="text-xs text-slate-500">{{ $w->partner?->display_name }} · {{ $w->created_at->translatedFormat('d M Y') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.status-badge :status="$w->status"/>
                        @if(in_array($w->status, ['requested', 'under_review', 'approved', 'processing']))
                            <form method="POST" action="{{ route('web.admin.withdrawals.action', $w->id) }}" class="inline">
                                @csrf
                                <input type="hidden" name="action" value="{{ ['requested' => 'under_review', 'under_review' => 'approved', 'approved' => 'processing', 'processing' => 'completed'][$w->status] }}">
                                <button class="rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-teal-500">{{ ['requested' => 'Review', 'under_review' => 'Approve', 'approved' => 'Proses', 'processing' => 'Selesaikan'][$w->status] }}</button>
                            </form>
                            <form method="POST" action="{{ route('web.admin.withdrawals.action', $w->id) }}" class="inline">
                                @csrf
                                <input type="hidden" name="action" value="rejected">
                                <button class="rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-600">Tolak</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-500">Belum ada penarikan.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-2xl ring-1 ring-slate-800">
        <h2 class="border-b border-slate-800 px-5 py-3.5 font-bold text-white">Settlements</h2>
        <div class="divide-y divide-slate-800">
            @forelse($settlements as $s)
                <div class="flex items-center justify-between gap-3 px-5 py-3.5">
                    <div>
                        <p class="font-bold text-white">{{ (new \App\Support\Money\Money((int) $s->vendor_net))->format() }}</p>
                        <p class="text-xs text-slate-500">Order {{ $s->order?->code }} · komisi {{ (new \App\Support\Money\Money((int) $s->commission))->format() }}</p>
                    </div>
                    <x-ui.status-badge :status="$s->status"/>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-500">Belum ada settlement.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
