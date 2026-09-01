@extends('layouts.app')

@section('title', 'Akses Ditolak | Jasapedia')

@section('content')
<div class="flex flex-col items-center py-16 text-center">
    <x-brand.mark class="h-16 w-16 text-rose-600/40"/>
    <h1 class="mt-5 text-5xl font-black text-slate-900">403</h1>
    <p class="mt-2 font-bold text-slate-800">{{ $exception?->getMessage() ?: 'Kamu tidak punya akses ke halaman ini' }}</p>
    <p class="mt-1 max-w-sm text-sm text-slate-500">Hubungi admin jika kamu merasa ini keliru.</p>
    <a href="{{ route('web.home') }}" class="mt-5 rounded-xl bg-teal-600 px-6 py-3 text-sm font-bold text-white hover:bg-teal-700">Kembali ke Beranda</a>
</div>
@endsection
