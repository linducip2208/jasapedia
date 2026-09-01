@extends('layouts.app')

@section('title', 'Notifikasi | Jasapedia')

@section('content')
<h1 class="text-xl font-extrabold text-slate-900">Notifikasi</h1>
<div class="mt-4 divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white shadow-sm">
    @forelse($notifications as $n)
        <article class="flex gap-3 px-4 py-3">
            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $n->read_at ? 'bg-slate-100 text-slate-400' : 'bg-teal-100 text-teal-700' }}">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-slate-800">{{ $n->title }}</p>
                @if($n->body)<p class="mt-0.5 text-sm text-slate-500">{{ $n->body }}</p>@endif
                <p class="mt-1 text-xs text-slate-400">{{ \Carbon\Carbon::parse($n->created_at)->diffForHumans() }}</p>
            </div>
        </article>
    @empty
        <p class="p-10 text-center text-sm text-slate-400">Tidak ada notifikasi.</p>
    @endforelse
</div>
<x-ui.pagination :paginator="$notifications"/>
@endsection
