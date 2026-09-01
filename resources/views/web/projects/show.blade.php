@extends('layouts.app')

@section('title', $project->title.' | Jasapedia')

@section('content')
<x-ui.breadcrumb :items="[['label' => 'Beranda', 'url' => route('web.home')], ['label' => 'Proyek', 'url' => route('web.projects.index')], ['label' => $project->code]]"/>

<div class="mt-4 grid gap-6 lg:grid-cols-[1fr_320px]">
    <div class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $project->code }} · {{ $project->category->name ?? '' }}</p>
                    <h1 class="mt-1 text-lg font-extrabold text-slate-900">{{ $project->title }}</h1>
                </div>
                <x-ui.status-badge :status="$project->status" type="project"/>
            </div>
            <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-slate-600">{{ $project->description }}</p>

            @if($project->skills)
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($project->skills as $skill)
                        <x-ui.chip>{{ $skill }}</x-ui.chip>
                    @endforeach
                </div>
            @endif

            @if($project->deadline)
                <p class="mt-4 text-sm text-slate-500">Deadline: <strong class="text-slate-700">{{ $project->deadline->translatedFormat('d F Y') }}</strong></p>
            @endif
        </div>

        {{-- Proposals comparison (owner) --}}
        @if($isOwner)
            <h2 class="text-lg font-extrabold text-slate-900">Proposal ({{ $project->proposals_count }})</h2>
            <div class="space-y-3">
                @forelse($project->proposals as $proposal)
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <x-ui.avatar :name="$proposal->partner->display_name" :src="$proposal->partner->avatar_path ? app(\App\Domain\Catalog\MediaService::class)->url($proposal->partner->avatar_path) : null" :verified="$proposal->partner->isVerified()"/>
                                <div>
                                    <a href="{{ route('web.provider.show', $proposal->partner->slug) }}" class="font-bold text-slate-900 hover:text-teal-700">{{ $proposal->partner->display_name }}</a>
                                    <div class="flex items-center gap-2 text-xs text-slate-500">
                                        <x-ui.rating :value="$proposal->partner->rating_avg" size="xs"/>
                                        <span>· {{ $proposal->partner->completed_jobs ?? 0 }} proyek</span>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-[11px] text-slate-400">Penawaran</p>
                                <x-ui.money :amount="$proposal->price" class="text-lg font-extrabold text-teal-700"/>
                                <p class="text-xs text-slate-500">{{ $proposal->timeline_days }} hari</p>
                            </div>
                        </div>

                        <p class="mt-3 line-clamp-3 text-sm text-slate-600">{{ $proposal->cover_letter }}</p>
                        <div class="mt-2"><x-ui.status-badge :status="$proposal->status" type="project"/></div>

                        @if(in_array($proposal->status, ['submitted', 'shortlisted']) && in_array($project->status, ['receiving_proposals', 'shortlisting']))
                            <div class="mt-4 flex flex-wrap gap-2.5">
                                @if($proposal->status === 'submitted')
                                    <form method="POST" action="{{ route('web.projects.proposal.decide', [$project->id, $proposal->id]) }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="shortlisted">
                                        <x-ui.button type="submit" size="sm" variant="outline">Shortlist</x-ui.button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('web.projects.proposal.decide', [$project->id, $proposal->id]) }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="accepted">
                                    <x-ui.button type="submit" size="sm">Terima & Kontrak</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('web.projects.proposal.decide', [$project->id, $proposal->id]) }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="rejected">
                                    <x-ui.button type="submit" size="sm" variant="ghost">Tolak</x-ui.button>
                                </form>
                            </div>
                        @endif

                        @if($proposal->status === 'accepted')
                            @if($project->contracts->isEmpty())
                                <form method="POST" action="{{ route('web.projects.proposal.contract', [$project->id, $proposal->id]) }}" class="mt-4">
                                    @csrf
                                    <x-ui.button type="submit" size="sm">Buat Kontrak + Milestone</x-ui.button>
                                </form>
                            @else
                                <div class="mt-4 rounded-xl bg-emerald-50 p-3.5 text-sm text-emerald-800">
                                    Kontrak aktif. Milestone dapat dikelola di halaman kontrak.
                                </div>
                            @endif
                        @endif
                    </article>
                @empty
                    <x-ui.empty-state title="Belum ada proposal" description="Freelancer melihat proyekmu dan akan mengirim proposal."/>
                @endforelse
            </div>
        @endif
    </div>

    <aside class="space-y-4 lg:sticky lg:top-24 lg:h-fit">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="font-bold text-slate-900">Budget</h2>
            <p class="mt-1.5 text-xl font-extrabold text-teal-700">
                @if($project->budget_type === 'fixed')
                    {{ (new \App\Support\Money\Money((int) $project->budget_max))->format() }}
                @elseif($project->budget_min && $project->budget_max)
                    {{ (new \App\Support\Money\Money((int) $project->budget_min))->format() }} – {{ (new \App\Support\Money\Money((int) $project->budget_max))->format() }}
                @else
                    Nego
                @endif
            </p>
            <dl class="mt-4 space-y-2.5 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd><x-ui.status-badge :status="$project->status" type="project"/></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Proposal</dt><dd class="font-semibold">{{ $project->proposals_count }}</dd></div>
                @if($project->awardedPartner)<div class="flex justify-between gap-2"><dt class="text-slate-500">Diterima</dt><dd class="truncate font-semibold">{{ $project->awardedPartner->display_name }}</dd></div>@endif
            </dl>
        </div>

        @if(! $isOwner && auth()->check())
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                @if($myPartner && $project->status === 'receiving_proposals')
                    <h2 class="font-bold text-slate-900">Kirim Proposal</h2>
                    <form method="POST" action="{{ route('web.projects.proposal.submit', $project->id) }}" class="mt-3 space-y-3">
                        @csrf
                        <x-ui.textarea name="cover_letter" placeholder="Kenapa kamu yang paling cocok?" :rows="4" required/>
                        <x-ui.input name="price" label="Harga (Rp)" type="number" min="1000" required/>
                        <x-ui.input name="timeline_days" label="Durasi (hari)" type="number" min="1" required/>
                        <x-ui.button type="submit" full>Kirim Proposal</x-ui.button>
                    </form>
                @elseif(! $myPartner)
                    <p class="text-sm text-slate-500">Ingin mengerjakan proyek ini? Daftar sebagai penyedia dulu.</p>
                    <a href="{{ route('web.partner.onboarding') }}" class="mt-3 block rounded-xl bg-teal-600 py-2.5 text-center text-sm font-bold text-white hover:bg-teal-700">Jadi Penyedia</a>
                @endif
            </div>
        @endif
    </aside>
</div>
@endsection
