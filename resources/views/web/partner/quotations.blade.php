@extends('layouts.partner')

@section('title', 'Penawaran Saya | Jasapedia')

@section('partner-content')
<h1 class="text-xl font-extrabold text-slate-900">Penawaran Saya</h1>

<div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-400">
                <tr><th class="px-4 py-3">Kode</th><th class="px-4 py-3">RFQ</th><th class="px-4 py-3">Ver</th><th class="px-4 py-3">Total</th><th class="px-4 py-3">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($quotations as $q)
                    <tr class="hover:bg-slate-50/60">
                        <td class="px-4 py-3 font-mono text-xs">{{ $q->code }}</td>
                        <td class="max-w-[220px] truncate px-4 py-3">{{ $q->rfq?->title ?? $q->rfq?->code ?? '-' }}</td>
                        <td class="px-4 py-3">v{{ $q->version }}</td>
                        <td class="px-4 py-3 font-bold"><x-ui.money :amount="$q->total"/></td>
                        <td class="px-4 py-3"><x-ui.status-badge :status="$q->status"/></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Belum ada penawaran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<x-ui.pagination :paginator="$quotations"/>
@endsection
