@extends('layouts.partner')

@section('title', 'Proyek Terbuka | Jasapedia')

@section('partner-content')
<h1 class="text-xl font-extrabold text-slate-900">Proyek Terbuka</h1>

<div class="mt-5 space-y-3">
    @forelse($projects as $project)
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase text-slate-400">{{ $project->category?->name }}</p>
                    <h2 class="mt-0.5 font-bold text-slate-900">{{ $project->title }}</h2>
                    <p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ $project->description }}</p>
                </div>
                <div class="text-right">
                    @if($project->budget_min || $project->budget_max)
                        <p class="font-extrabold text-teal-700">{{ (new \App\Support\Money\Money((int) ($project->budget_min ?? $project->budget_max)))->format() }}{{ $project->budget_max && $project->budget_min ? ' – '.(new \App\Support\Money\Money((int) $project->budget_max))->format() : '' }}</p>
                    @endif
                    <p class="text-xs text-slate-400">{{ $project->proposals_count }} proposal</p>
                </div>
            </div>
            <details class="mt-3">
                <summary class="cursor-pointer text-sm font-bold text-teal-700">Kirim Proposal</summary>
                <form method="POST" action="{{ route('web.partner.projects.proposal') }}" class="mt-3 space-y-2.5 rounded-xl bg-slate-50 p-4">
                    @csrf
                    <input type="hidden" name="project_id" value="{{ $project->id }}">
                    <textarea name="cover_letter" rows="3" placeholder="Kenapa kamu yang paling cocok?" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <input name="price" type="number" min="1000" placeholder="Harga (Rp)" required class="h-10 rounded-lg border border-slate-300 px-3 text-sm" aria-label="Harga"/>
                        <input name="timeline_days" type="number" min="1" placeholder="Durasi (hari)" required class="h-10 rounded-lg border border-slate-300 px-3 text-sm" aria-label="Durasi"/>
                    </div>
                    <x-ui.button type="submit" size="sm">Kirim Proposal</x-ui.button>
                </form>
            </details>
        </article>
    @empty
        <x-ui.empty-state title="Belum ada proyek terbuka" description="Kembali lagi nanti untuk proyek baru."/>
    @endforelse
</div>
<x-ui.pagination :paginator="$projects"/>

@if($myProposals->isNotEmpty())
    <section class="mt-8">
        <h2 class="font-bold text-slate-800">Proposal Saya</h2>
        <div class="mt-3 space-y-2.5">
            @foreach($myProposals as $proposal)
                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-3.5">
                    <p class="min-w-0 truncate text-sm font-semibold">{{ $proposal->project?->title }}</p>
                    <div class="flex items-center gap-2.5">
                        <x-ui.money :amount="$proposal->price" class="text-sm font-bold"/>
                        <x-ui.status-badge :status="$proposal->status" type="project"/>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
@endsection
