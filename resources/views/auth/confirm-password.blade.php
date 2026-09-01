<x-guest-layout>
    <x-slot name="title">Konfirmasi Kata Sandi | Jasapedia</x-slot>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <h1 class="text-lg font-bold text-slate-900">Area aman</h1>
        <p class="mt-1 text-sm text-slate-500">Konfirmasi kata sandi kamu untuk melanjutkan.</p>

        <div class="mt-5">
            <x-ui.input id="password" label="Kata Sandi" type="password" name="password" required autocomplete="current-password"/>
        </div>

        <div class="mt-5 flex justify-end">
            <x-ui.button type="submit">Konfirmasi</x-ui.button>
        </div>
    </form>
</x-guest-layout>
