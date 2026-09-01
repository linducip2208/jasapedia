<x-guest-layout>
    <x-slot name="title">Masuk | Jasapedia</x-slot>

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <h1 class="text-lg font-bold text-slate-900">Masuk ke akun kamu</h1>
        <p class="mt-1 text-sm text-slate-500">Lanjutkan pesanan, booking teknisi, dan kelola proyekmu.</p>

        <div class="mt-5 space-y-4">
            <x-ui.input id="email" label="Email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username"/>
            <x-ui.input id="password" label="Kata Sandi" type="password" name="password" required autocomplete="current-password"/>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-teal-600 focus:ring-teal-600"/>
                Ingat saya
            </label>
        </div>

        @if($errors->any())
            <p class="mt-3 text-sm font-semibold text-rose-600">{{ $errors->first() }}</p>
        @endif

        <div class="mt-5 flex items-center justify-between">
            <a href="{{ route('password.request') }}" class="text-sm font-semibold text-teal-700 hover:underline">Lupa sandi?</a>
            <x-ui.button type="submit">Masuk</x-ui.button>
        </div>
    </form>

    <p class="mt-5 border-t border-slate-100 pt-4 text-center text-sm text-slate-500">
        Belum punya akun?
        <a href="{{ route('register') }}" class="font-bold text-teal-700 hover:underline">Daftar gratis</a>
    </p>
</x-guest-layout>
