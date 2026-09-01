@extends('layouts.admin')

@section('title', 'Admin Pengguna | Jasapedia')

@section('admin-content')
<h1 class="text-xl font-extrabold text-white">Pengguna</h1>

<div class="mt-4 overflow-x-auto rounded-2xl ring-1 ring-slate-800">
    <table class="w-full min-w-[640px] text-sm">
        <thead class="bg-slate-900 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Roles</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Terdaftar</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-800 bg-slate-900/40">
            @forelse($users as $user)
                <tr class="hover:bg-slate-800/40">
                    <td class="px-4 py-3 font-bold text-white">{{ $user->name }}</td>
                    <td class="px-4 py-3 text-slate-400">{{ $user->email }}</td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-1">
                            @foreach($user->roles as $role)
                                <x-ui.badge tone="indigo" size="sm">{{ $role->name }}</x-ui.badge>
                            @endforeach
                        </div>
                    </td>
                    <td class="px-4 py-3"><x-ui.badge tone="{{ $user->status === 'active' ? 'green' : 'rose' }}">{{ $user->status }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-slate-400">{{ $user->created_at->translatedFormat('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">Belum ada pengguna.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<x-ui.pagination :paginator="$users"/>
@endsection
