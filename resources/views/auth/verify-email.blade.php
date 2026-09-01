<x-guest-layout>
    <x-slot name="title">Verifikasi Email | Jasapedia</x-slot>

    <h1 class="text-lg font-bold text-slate-900">Verifikasi email kamu</h1>
    <p class="mt-1 text-sm text-slate-500">Terima kasih sudah mendaftar! Kami mengirim tautan verifikasi ke email kamu. Buka tautan tersebut untuk mengaktifkan akun.</p>

    @if(session('status') == 'verification-link-sent')
        <p class="mt-3 rounded-xl bg-emerald-50 px-3.5 py-2.5 text-sm font-semibold text-emerald-700">Tautan verifikasi baru telah dikirim.</p>
    @endif

    <div class="mt-5 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-ui.button type="submit" variant="outline" size="sm">Kirim Ulang Tautan</x-ui.button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="text-sm font-semibold text-slate-500 hover:text-rose-600">Keluar</button>
        </form>
    </div>
</x-guest-layout>
