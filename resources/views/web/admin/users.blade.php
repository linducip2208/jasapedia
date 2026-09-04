@extends('layouts.admin')

@section('title', 'Admin Pengguna | Jasapedia')

@section('admin-content')
<h1 class="h3 fw-bolder">Pengguna</h1>

<div class="card mt-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr><th scope="col">Nama</th><th scope="col">Email</th><th scope="col">Roles</th><th scope="col">Status</th><th scope="col">Terdaftar</th></tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td class="fw-bold">{{ $user->name }}</td>
                        <td class="text-body-secondary">{{ $user->email }}</td>
                        <td>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($user->roles as $role)
                                    <span class="badge rounded-pill text-bg-info">{{ $role->name }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td><span class="badge rounded-pill {{ $user->status === 'active' ? 'badge-brand' : 'text-bg-danger' }}">{{ $user->status }}</span></td>
                        <td class="text-body-secondary">{{ $user->created_at->translatedFormat('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-body-secondary py-5">Belum ada pengguna.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<x-admin.pagination :paginator="$users"/>
@endsection
