@extends('layouts.app')

@section('title', 'Favorit Saya | Jasapedia')

@section('content')
<h1 class="text-xl font-extrabold text-slate-900">Favorit Saya</h1>

<section class="mt-5">
    <h2 class="font-bold text-slate-800">Jasa</h2>
    @if($services->isNotEmpty())
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($services as $service)
                <x-ui.service-card :service="$service"/>
            @endforeach
        </div>
        <x-ui.pagination :paginator="$services"/>
    @else
        <p class="mt-2 text-sm text-slate-400">Belum ada jasa favorit. Tekan ikon hati di jasa yang menarik.</p>
    @endif
</section>

<section class="mt-8">
    <h2 class="font-bold text-slate-800">Penyedia</h2>
    @if($providers->isNotEmpty())
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach($providers as $provider)
                <x-ui.provider-card :provider="$provider"/>
            @endforeach
        </div>
    @else
        <p class="mt-2 text-sm text-slate-400">Belum ada penyedia favorit.</p>
    @endif
</section>
@endsection
