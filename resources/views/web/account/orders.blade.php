@extends('layouts.app')

@section('title', 'Pesanan | Jasapedia')

@section('content')
<h1 class="text-xl font-extrabold text-slate-900">Pesanan</h1>
<div class="mt-4 space-y-3">
    @forelse($orders as $order)
        <x-ui.order-card :order="$order"/>
    @empty
        <x-ui.empty-state title="Belum ada pesanan" actionUrl="{{ route('web.explore') }}" actionLabel="Cari Jasa"/>
    @endforelse
</div>
<x-ui.pagination :paginator="$orders"/>
@endsection
