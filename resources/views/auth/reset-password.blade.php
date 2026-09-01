<x-guest-layout>
    <x-slot name="title">Atur Ulang Kata Sandi | Jasapedia</x-slot>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <h1 class="text-lg font-bold text-slate-900">Buat kata sandi baru</h1>
        <input type="hidden" name="token" value="{{ $request->route('token') }}"/>

        <div class="mt-5 space-y-4">
            <x-ui.input id="email" label="Email" type="email" name="email" :value="old('email', $request->email)" required/>
            <x-ui.input id="password" label="Kata Sandi Baru" type="password" name="password" required autocomplete="new-password"/>
            <x-ui.input id="password_confirmation" label="Ulangi Kata Sandi" type="password" name="password_confirmation" required autocomplete="new-password"/>
        </div>

        <div class="mt-5">
            <x-ui.button type="submit" full>Simpan Kata Sandi</x-ui.button>
        </div>
    </form>
</x-guest-layout>
