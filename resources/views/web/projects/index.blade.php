@extends('layouts.app')

@section('title', 'Proyek Freelancer | Jasapedia')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-extrabold text-slate-900">{{ $mine ? 'Proyek Saya' : 'Cari Proyek Freelancer' }}</h1>
        <p class="text-sm text-slate-500">{{ $mine ? 'Kelola proyek, proposal, dan milestone.' : 'Ribuan pekerjaan digital & kreatif menantimu.' }}</p>
    </div>
    <div class="flex gap-2">
        <div class="flex rounded-xl border border-slate-200 bg-white p-1 text-sm font-semibold">
            <a href="{{ route('web.projects.index') }}" class="rounded-lg px-3.5 py-1.5 {{ ! $mine ? 'bg-teal-600 text-white' : 'text-slate-600' }}">Semua</a>
            <a href="{{ route('web.projects.index', ['mine' => 1]) }}" class="rounded-lg px-3.5 py-1.5 {{ $mine ? 'bg-teal-600 text-white' : 'text-slate-600' }}">Saya</a>
        </div>
        <a href="{{ route('web.projects.create') }}" class="rounded-xl bg-teal-600 px-4 py-2 text-sm font-bold text-white hover:bg-teal-700">Buat Proyek</a>
    </div>
</div>

<div class="mt-5 space-y-3">
    @forelse($projects as $project)
        <x-ui.project-card :project="$project"/>
    @empty
        <x-ui.empty-state
            title="{{ $mine ? 'Belum ada proyek' : 'Belum ada proyek terbuka' }}"
            description="{{ $mine ? 'Publikasikan proyek pertamamu dan terima proposal dari freelancer.' : 'Cek lagi nanti atau buat proyek untuk menemukan freelancer terbaik.' }}"
            actionUrl="{{ route('web.projects.create') }}" actionLabel="Buat Proyek"/>
    @endforelse
</div>
<x-ui.pagination :paginator="$projects"/>
@endsection
