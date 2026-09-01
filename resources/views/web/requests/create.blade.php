@extends('layouts.app')

@section('title', 'Posting Kebutuhan | Jasapedia')

@section('content')
<div class="mx-auto max-w-2xl">
    <h1 class="text-xl font-extrabold text-slate-900">Posting Kebutuhan</h1>
    <p class="mt-1 text-sm text-slate-500">Ceritakan apa yang kamu butuhkan. Contoh: "AC saya bocor dan tidak dingin di Bekasi Selatan."</p>

    <form method="POST" action="{{ route('web.requests.store') }}" class="mt-6 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        @csrf
        <x-ui.select name="category_id" label="Kategori kebutuhan" required>
            <option value="">Pilih kategori...</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.input name="title" label="Judul singkat" placeholder="Servis AC bocor" :value="old('title')" required/>

        <x-ui.textarea name="description" label="Jelaskan kebutuhanmu" placeholder="Ceritakan kendalanya, lokasi, dan kondisi saat ini..." :rows="5" required/>

        <x-ui.input name="deadline" label="Batas waktu penawaran (opsional)" type="date" :value="old('deadline')" hint="Kosongkan jika fleksibel"/>

        <x-ui.button type="submit" full>Publikasikan Kebutuhan</x-ui.button>
        <p class="text-center text-xs text-slate-400">Gratis. Penawaran akan muncul di halaman kebutuhanmu.</p>
    </form>
</div>
@endsection
