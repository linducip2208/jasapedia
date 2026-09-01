@extends('layouts.admin')

@section('title', 'Admin Sengketa | Jasapedia')

@section('admin-content')
<h1 class="text-xl font-extrabold text-white">Sengketa & Trust</h1>

<div class="mt-4 space-y-3">
    @forelse($disputes as $dispute)
        <article class="rounded-2xl bg-slate-900 p-5 ring-1 ring-slate-800">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-mono text-slate-500">{{ $dispute->code ?? '#'.$dispute->id }} · Order {{ $dispute->order?->code }}</p>
                    <h2 class="mt-0.5 font-bold text-white">{{ $dispute->title ?? ($dispute->subject ?? 'Sengketa') }}</h2>
                    @if(isset($dispute->description))<p class="mt-1 max-w-2xl text-sm text-slate-400">{{ $dispute->description }}</p>@endif
                </div>
                <div class="text-right">
                    <x-ui.status-badge :status="$dispute->status"/>
                    @if(isset($dispute->amount))<p class="mt-1 text-sm font-bold text-amber-400">{{ (new \App\Support\Money\Money((int) $dispute->amount))->format() }}</p>@endif
                </div>
            </div>

            @if($dispute->status === 'open')
                <form method="POST" action="{{ route('web.admin.disputes.resolve', $dispute->id) }}" class="mt-4 grid gap-2.5 rounded-xl bg-slate-800/50 p-4 sm:grid-cols-[160px_1fr_140px]">
                    @csrf
                    <select name="resolution" class="h-11 rounded-xl bg-slate-900 px-3 text-sm text-white ring-1 ring-slate-700" aria-label="Resolusi">
                        <option value="refund_customer">Refund customer</option>
                        <option value="reject">Tolak sengketa</option>
                    </select>
                    <input name="note" placeholder="Catatan resolusi (wajib)" required class="h-11 rounded-xl bg-slate-900 px-3.5 text-sm text-white ring-1 ring-slate-700" aria-label="Catatan"/>
                    <x-ui.button type="submit" size="sm">Selesaikan</x-ui.button>
                </form>
            @endif
        </article>
    @empty
        <p class="rounded-2xl bg-slate-900 px-4 py-10 text-center text-sm text-slate-500 ring-1 ring-slate-800">Tidak ada sengketa. 🎉</p>
    @endforelse
</div>
<x-ui.pagination :paginator="$disputes"/>
@endsection
