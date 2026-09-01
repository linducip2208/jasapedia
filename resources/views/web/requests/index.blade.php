@extends('layouts.app')

@section('title', 'Kebutuhan Saya | Jasapedia')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-xl font-extrabold text-slate-900">Kebutuhan Saya</h1>
        <p class="text-sm text-slate-500">Penyedia akan mengirim penawaran untuk kebutuhanmu.</p>
    </div>
    <a href="{{ route('web.requests.create') }}" class="rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-teal-700">Posting Kebutuhan</a>
</div>

<div class="mt-5 space-y-3">
    @forelse($requests as $rfq)
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <a href="{{ route('web.requests.show', $rfq->id) }}" class="min-w-0 flex-1">
                    <h2 class="truncate font-semibold text-slate-900 hover:text-teal-700">{{ $rfq->title }}</h2>
                    <p class="mt-1 line-clamp-1 text-sm text-slate-500">{{ $rfq->description }}</p>
                </a>
                <x-ui.status-badge :status="$rfq->status" type="project"/>
            </div>
            <div class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                <span class="font-bold text-slate-700">{{ $rfq->quotations_count }} penawaran masuk</span>
                <span>{{ $rfq->created_at->translatedFormat('d M Y') }}</span>
                @if($rfq->deadline)<span>Deadline {{ $rfq->deadline->translatedFormat('d M Y') }}</span>@endif
            </div>
        </article>
    @empty
        <x-ui.empty-state title="Belum ada kebutuhan" description="Ceritakan kebutuhanmu, penyedia terdekat akan mengirim penawaran." actionUrl="{{ route('web.requests.create') }}" actionLabel="Posting Kebutuhan"/>
    @endforelse
</div>
<x-ui.pagination :paginator="$requests"/>
@endsection
