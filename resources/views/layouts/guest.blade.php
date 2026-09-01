<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0D9488">
    <title>@yield('title', 'Jasapedia — Semua Jasa, Satu Platform')</title>
    <link rel="icon" href="{{ asset('branding/favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('branding/apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<div class="flex min-h-screen flex-col items-center justify-center px-4">
    <div class="w-full max-w-md">
        <div class="mb-6 flex flex-col items-center text-center">
            <a href="{{ route('web.home') }}" aria-label="Jasapedia">
                <x-brand.logo class="h-14 w-14"/>
            </a>
            <p class="mt-2 text-lg font-extrabold tracking-tight text-slate-900">Jasa<span class="text-teal-600">pedia</span></p>
            <p class="text-xs text-slate-500">Semua Jasa, Satu Platform</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            {{ $slot }}
        </div>
        <p class="mt-5 text-center text-xs text-slate-400">&copy; {{ date('Y') }} Jasapedia</p>
    </div>
</div>
</body>
</html>
