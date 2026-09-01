@extends('layouts.app')

@section('title', 'Profil Saya | Jasapedia')

@section('content')
<h1 class="text-xl font-extrabold text-slate-900">Profil & Pengaturan</h1>

<div class="mt-5 grid gap-5 lg:grid-cols-2">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-bold text-slate-900">Informasi Akun</h2>
        <dl class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Nama</dt><dd class="font-semibold">{{ $user->name }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Email</dt><dd class="font-semibold">{{ $user->email }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Telepon</dt><dd class="font-semibold">{{ $user->phone ?? '-' }} @if($user->phone_verified_at)<x-ui.badge tone="green" size="sm">Terverifikasi</x-ui.badge>@endif</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Keamanan</dt><dd><a href="{{ route('password.confirm') }}" class="font-bold text-teal-700 hover:underline">Kata sandi & 2FA</a></dd></div>
        </dl>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="font-bold text-slate-900">Alamat Tersimpan</h2>
        <div class="mt-3 space-y-2.5">
            @forelse($addresses as $addr)
                <div class="flex items-start justify-between gap-3 rounded-xl bg-slate-50 p-3.5">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-800">{{ $addr->label }} @if($addr->is_default)<x-ui.badge tone="teal" size="sm">Utama</x-ui.badge>@endif</p>
                        <p class="mt-0.5 truncate text-xs text-slate-500">{{ $addr->address_line }} · {{ $addr->subdistrict?->name }}</p>
                    </div>
                    <form method="POST" action="{{ route('web.account.address.destroy', $addr->id) }}">
                        @csrf @method('DELETE')
                        <button class="text-xs font-bold text-rose-600 hover:underline" aria-label="Hapus alamat {{ $addr->label }}">Hapus</button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-slate-400">Belum ada alamat tersimpan.</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('web.account.address.store') }}" class="mt-4 space-y-3 border-t border-slate-100 pt-4">
            @csrf
            <p class="text-sm font-bold text-slate-800">Tambah Alamat</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <x-ui.input name="label" label="Label" placeholder="Rumah / Kantor" required/>
                <x-ui.input name="recipient_name" label="Nama penerima" :value="auth()->user()->name" required/>
            </div>
            <x-ui.input name="phone" label="No. telepon" required/>
            <x-ui.input name="subdistrict_id" label="ID kelurahan (dari lokasi)" type="number" min="1" required hint="Dipilih dari pohon lokasi Indonesia"/>
            <x-ui.textarea name="address_line" label="Alamat lengkap" :rows="2" required/>
            <x-ui.checkbox name="is_default" label="Jadikan alamat utama"/>
            <x-ui.button type="submit" size="sm">Simpan Alamat</x-ui.button>
        </form>
    </div>
</div>
@endsection
