@extends('layouts.app')

@section('title', 'Pesanan Saya | Jasapedia')

@section('content')
<h1 class="text-xl font-extrabold text-slate-900">Pesanan Saya</h1>

<div class="mt-4 flex gap-2 overflow-x-auto pb-1">
    @php $tabs = ['all' => 'Semua', 'active' => 'Berjalan', 'done' => 'Selesai', 'cancelled' => 'Dibatalkan']; @endphp
    @foreach($tabs as $key => $label)
        <a href="{{ route('web.orders', $key === 'all' ? [] : ['status' => $key]) }}"
            class="whitespace-nowrap rounded-full px-4 py-2 text-sm font-bold transition {{ $tab === $key ? 'bg-teal-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:ring-teal-600' }}">{{ $label }}</a>
    @endforeach
</div>

<div class="mt-4 space-y-3">
    @forelse($orders as $order)
        <x-ui.order-card :order="$order"/>
    @empty
        <x-ui.empty-state title="Belum ada pesanan" description="Pesan jasa pertamamu dan lacak statusnya di sini." actionUrl="{{ route('web.explore') }}" actionLabel="Cari Jasa"/>
    @endforelse
</div>

<x-ui.pagination :paginator="$orders"/>
@endsection
