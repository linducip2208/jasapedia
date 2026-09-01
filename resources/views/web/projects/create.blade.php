@extends('layouts.app')

@section('title', 'Buat Proyek | Jasapedia')

@section('content')
<div class="mx-auto max-w-2xl">
    <x-ui.stepper :steps="['Kategori', 'Detail', 'Budget', 'Tinjau']" :activeIndex="1"/>
    <h1 class="mt-5 text-xl font-extrabold text-slate-900">Ceritakan proyekmu</h1>

    <form method="POST" action="{{ route('web.projects.store') }}" class="mt-6 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        @csrf
        <x-ui.select name="category_id" label="Kategori" required>
            <option value="">Pilih kategori</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.input name="title" label="Judul proyek" placeholder="Contoh: Landing page company profile" :value="old('title')" required/>

        <x-ui.textarea name="description" label="Deskripsi pekerjaan" placeholder="Jelaskan tujuan, scope, referensi, dan hasil yang diharapkan..." :rows="6" required/>

        <div>
            <label class="mb-1.5 block text-sm font-semibold text-slate-700">Keahlian yang dibutuhkan</label>
            <input type="text" name="skills[]" placeholder="Contoh: Laravel, Figma, SEO (pisahkan dengan koma saat mengisi)" class="h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20" @error('skills.*') aria-invalid="true" @enderror/>
            <p class="mt-1 text-xs text-slate-400">Contoh: desain-grafis, laravel, copywriting</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.select name="budget_type" label="Tipe budget">
                <option value="range" @selected(old('budget_type') === 'range')">Kisaran</option>
                <option value="fixed" @selected(old('budget_type') === 'fixed')">Tetap</option>
                <option value="hourly" @selected(old('budget_type') === 'hourly')">Per jam</option>
            </x-ui.select>
            <x-ui.input name="deadline" label="Deadline (opsional)" type="date" :value="old('deadline')"/>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="budget_min" label="Budget minimum (Rp)" type="number" min="0" :value="old('budget_min')"/>
            <x-ui.input name="budget_max" label="Budget maksimum (Rp)" type="number" min="0" :value="old('budget_max')"/>
        </div>

        <x-ui.button type="submit" full>Publikasikan Proyek</x-ui.button>
    </form>
</div>
@endsection
