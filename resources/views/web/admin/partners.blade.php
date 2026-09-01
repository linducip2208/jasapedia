@extends('layouts.admin')

@section('title', 'Admin Penyedia | Jasapedia')

@section('admin-content')
<h1 class="text-xl font-extrabold text-white">Penyedia & Verifikasi</h1>

<div class="mt-4 overflow-x-auto rounded-2xl ring-1 ring-slate-800">
    <table class="w-full min-w-[720px] text-sm">
        <thead class="bg-slate-900 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Tipe</th><th class="px-4 py-3">Kota</th><th class="px-4 py-3">Rating</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Aksi</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-800 bg-slate-900/40">
            @forelse($partners as $partner)
                <tr class="hover:bg-slate-800/40">
                    <td class="px-4 py-3">
                        <p class="font-bold text-white">{{ $partner->display_name }}</p>
                        <p class="text-xs text-slate-500">{{ $partner->user?->email }}</p>
                    </td>
                    <td class="px-4 py-3 text-slate-400">{{ $partner->type }}</td>
                    <td class="px-4 py-3 text-slate-400">{{ $partner->city ?? '-' }}</td>
                    <td class="px-4 py-3 text-amber-400">{{ number_format($partner->rating_avg, 1) }}</td>
                    <td class="px-4 py-3"><x-ui.badge tone="{{ $partner->verification_state === 'verified' ? 'green' : ($partner->verification_state === 'suspended' ? 'rose' : 'amber') }}">{{ $partner->verification_state }}</x-ui.badge></td>
                    <td class="px-4 py-3">
                        <form method="POST" action="{{ route('web.admin.partners.verify', $partner->id) }}" class="flex items-center gap-1.5">
                            @csrf
                            <input type="hidden" name="state" value="{{ $partner->verification_state === 'verified' ? 'suspended' : 'verified' }}">
                            <button class="rounded-lg px-3 py-1.5 text-xs font-bold {{ $partner->verification_state === 'verified' ? 'bg-rose-600/90 text-white hover:bg-rose-600' : 'bg-teal-600 text-white hover:bg-teal-500' }}">
                                {{ $partner->verification_state === 'verified' ? 'Suspend' : 'Verifikasi' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-slate-500">Belum ada penyedia.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<x-ui.pagination :paginator="$partners"/>
@endsection
