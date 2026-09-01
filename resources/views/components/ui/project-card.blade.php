@props(['project'])
<article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md">
    <div class="flex items-start justify-between gap-3">
        <a href="{{ route('web.projects.show', ['id' => $project->id]) }}" class="min-w-0 flex-1">
            <h3 class="truncate font-semibold text-slate-900 hover:text-teal-700">{{ $project->title }}</h3>
            <p class="mt-1 line-clamp-1 text-sm text-slate-500">{{ $project->description }}</p>
        </a>
        <x-ui.status-badge :status="$project->status" type="project"/>
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
        @if($project->budget_min || $project->budget_max)
            <span class="font-semibold text-slate-700">Rp{{ number_format($project->budget_min ?? 0) }}–{{ number_format($project->budget_max ?? 0) }}</span>
        @endif
        <span>{{ $project->proposals_count ?? $project->proposals->count() ?? 0 }} proposal</span>
        @if($project->deadline)<span>Deadline: {{ \Carbon\Carbon::parse($project->deadline)->translatedFormat('d M Y') }}</span>@endif
    </div>
</article>
