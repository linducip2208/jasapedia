<x-guest-layout>
    <x-slot name="title">Lupa Kata Sandi | Jasapedia</x-slot>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <h1 class="text-lg font-bold text-slate-900">Lupa kata sandi?</h1>
        <p class="mt-1 text-sm text-slate-500">Masukkan email kamu. Kami akan mengirim tautan untuk membuat sandi baru.</p>

        <div class="mt-5">
            <x-ui.input id="email" label="Email" type="email" name="email" :value="old('email')" required autofocus/>
        </div>

        @if(session('status'))
            <p class="mt-3 rounded-xl bg-emerald-50 px-3.5 py-2.5 text-sm font-semibold text-emerald-700">{{ session('status') }}</p>
        @endif

        <div class="mt-5 flex items-center justify-between">
            <a href="{{ route('login') }}" class="text-sm font-semibold text-slate-600 hover:underline">&larr; Kembali masuk</a>
            <x-ui.button type="submit">Kirim Tautan</x-ui.button>
        </div>
    </form>
</x-guest-layout>
