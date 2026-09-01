<x-guest-layout>
    <x-slot name="title">Daftar | Jasapedia</x-slot>

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <h1 class="text-lg font-bold text-slate-900">Buat akun Jasapedia</h1>
        <p class="mt-1 text-sm text-slate-500">Satu akun untuk memesan jasa, mencari teknisi, dan mengelola proyek.</p>

        <div class="mt-5 space-y-4">
            <x-ui.input id="name" label="Nama Lengkap" type="text" name="name" :value="old('name')" required autofocus autocomplete="name"/>
            <x-ui.input id="email" label="Email" type="email" name="email" :value="old('email')" required autocomplete="email"/>
            <x-ui.input id="phone" label="Nomor WhatsApp" type="tel" name="phone" :value="old('phone')" hint="Format Indonesia, contoh 081234567890" required autocomplete="tel"/>
            <x-ui.input id="password" label="Kata Sandi" type="password" name="password" required autocomplete="new-password"/>

            <fieldset class="rounded-xl border border-slate-200 p-3.5">
                <legend class="px-1 text-xs font-bold uppercase tracking-wide text-slate-400">Saya di sini untuk</legend>
                <div class="mt-1 space-y-2.5">
                    <x-ui.radio name="purpose" value="customer" label="Saya mencari jasa" :checked="old('purpose', 'customer') === 'customer'"/>
                    <x-ui.radio name="purpose" value="provider" label="Saya ingin menjadi penyedia" :checked="old('purpose') === 'provider'"/>
                </div>
            </fieldset>
        </div>

        @if($errors->any())
            <p class="mt-3 text-sm font-semibold text-rose-600">{{ $errors->first() }}</p>
        @endif

        <div class="mt-5">
            <x-ui.button type="submit" full>Daftar</x-ui.button>
        </div>
    </form>

    <p class="mt-5 border-t border-slate-100 pt-4 text-center text-sm text-slate-500">
        Sudah punya akun?
        <a href="{{ route('login') }}" class="font-bold text-teal-700 hover:underline">Masuk</a>
    </p>
</x-guest-layout>
