@extends('layouts.admin')

@section('title', 'Admin Penyedia | Jasapedia')

@section('admin-content')
<h1 class="h3 fw-bolder">Penyedia & Verifikasi</h1>

<div class="card mt-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr><th scope="col">Nama</th><th scope="col">Tipe</th><th scope="col">Kota</th><th scope="col">Rating</th><th scope="col">Status</th><th scope="col">Aksi</th></tr>
            </thead>
            <tbody>
                @forelse($partners as $partner)
                    <tr>
                        <td>
                            <p class="fw-bold mb-0">{{ $partner->display_name }}</p>
                            <p class="small text-body-secondary mb-0">{{ $partner->user?->email }}</p>
                        </td>
                        <td class="text-body-secondary">{{ $partner->type }}</td>
                        <td class="text-body-secondary">{{ $partner->city ?? '-' }}</td>
                        <td><span class="badge text-bg-warning">{{ number_format($partner->rating_avg, 1) }}</span></td>
                        <td>
                            <span class="badge rounded-pill {{ $partner->verification_state === 'verified' ? 'badge-brand' : ($partner->verification_state === 'suspended' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ $partner->verification_state }}</span>
                        </td>
                        <td>
                            <form method="POST" action="{{ route('web.admin.partners.verify', $partner->id) }}" class="d-flex gap-1">
                                @csrf
                                <input type="hidden" name="state" value="{{ $partner->verification_state === 'verified' ? 'suspended' : 'verified' }}">
                                <button type="submit" class="btn btn-sm {{ $partner->verification_state === 'verified' ? 'btn-outline-danger' : 'btn-brand' }}">
                                    {{ $partner->verification_state === 'verified' ? 'Suspend' : 'Verifikasi' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-body-secondary py-5">Belum ada penyedia.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<x-admin.pagination :paginator="$partners"/>
@endsection
