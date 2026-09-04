@extends('layouts.admin')

@section('title', 'Admin Sengketa | Jasapedia')

@section('admin-content')
<h1 class="h3 fw-bolder">Sengketa & Trust</h1>

<div class="mt-3 d-flex flex-column gap-3">
    @forelse($disputes as $dispute)
        <article class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                    <div>
                        <p class="font-monospace small text-body-secondary mb-1">{{ $dispute->code ?? '#'.$dispute->id }} · Order {{ $dispute->order?->code }}</p>
                        <h2 class="h6 fw-bold mb-1">{{ $dispute->title ?? ($dispute->subject ?? 'Sengketa') }}</h2>
                        @if(isset($dispute->description))<p class="small text-body-secondary mb-0" style="max-width: 42rem;">{{ $dispute->description }}</p>@endif
                    </div>
                    <div class="text-end">
                        <x-admin.status-badge :status="$dispute->status"/>
                        @if(isset($dispute->amount))<p class="small fw-bold text-warning mt-1 mb-0">{{ (new \App\Support\Money\Money((int) $dispute->amount))->format() }}</p>@endif
                    </div>
                </div>

                @if($dispute->status === 'open')
                    <form method="POST" action="{{ route('web.admin.disputes.resolve', $dispute->id) }}" class="row g-2 mt-3">
                        @csrf
                        <div class="col-sm-4 col-lg-3">
                            <select name="resolution" class="form-select" aria-label="Resolusi">
                                <option value="refund_customer">Refund customer</option>
                                <option value="reject">Tolak sengketa</option>
                            </select>
                        </div>
                        <div class="col">
                            <input name="note" placeholder="Catatan resolusi (wajib)" required class="form-control" aria-label="Catatan"/>
                        </div>
                        <div class="col-sm-auto">
                            <button type="submit" class="btn btn-brand w-100">Selesaikan</button>
                        </div>
                    </form>
                @endif
            </div>
        </article>
    @empty
        <div class="card"><div class="card-body text-center text-body-secondary py-5">Tidak ada sengketa terbuka.</div></div>
    @endforelse
</div>
<x-admin.pagination :paginator="$disputes"/>
@endsection
