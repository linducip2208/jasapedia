@extends('layouts.app')

@section('title', 'Dashboard | Jasapedia')

@section('content')
    <script>location.replace('{{ route('web.account.dashboard') }}');</script>
    <div class="py-20 text-center text-sm text-slate-500">Mengalihkan ke dashboard akun...</div>
@endsection
