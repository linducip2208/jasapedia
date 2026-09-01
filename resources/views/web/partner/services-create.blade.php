@extends('layouts.partner')

@section('title', 'Buat Jasa | Jasapedia')

@section('partner-content')
<div class="mx-auto max-w-2xl">
    <h1 class="text-xl font-extrabold text-slate-900">Buat Jasa Baru</h1>

    <form method="POST" action="{{ route('web.partner.services.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        @csrf
        <x-ui.select name="category_id" label="Kategori" required>
            <option value="">Pilih kategori</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </x-ui.select>

        <x-ui.input name="title" label="Judul jasa" placeholder="Contoh: Cuci AC Ruangan 1-2 PK" :value="old('title')" required/>

        <x-ui.textarea name="description" label="Deskripsi" placeholder="Jelaskan cakupan pekerjaan, keahlian, dan hasil yang didapat pelanggan..." :rows="5" required/>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.select name="price_model" label="Model harga">
                <option value="fixed">Tetap</option>
                <option value="per_unit">Per unit</option>
                <option value="hourly">Per jam</option>
                <option value="daily">Per hari</option>
            </x-ui.select>
            <x-ui.input name="base_price" label="Harga dasar (Rp)" type="number" min="1000" :value="old('base_price')" required/>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="unit_label" label="Satuan (opsional)" placeholder="unit / jam / km" :value="old('unit_label')"/>
            <x-ui.input name="warranty_days" label="Garansi (hari)" type="number" min="0" max="365" :value="old('warranty_days', 0)"/>
        </div>

        <x-ui.select name="fulfillment_type" label="Tipe fulfillment">
            <option value="appointment">Booking jadwal (appointment)</option>
            <option value="instant_booking">Instan</option>
            <option value="survey_required">Perlu survei</option>
        </x-ui.select>

        <x-ui.select name="delivery_mode" label="Mode layanan">
            <option value="onsite">Datang ke lokasi (onsite)</option>
            <option value="online">Online</option>
            <option value="hybrid">Hybrid</option>
        </x-ui.select>

        <label class="flex items-center gap-2.5 text-sm text-slate-700">
            <input type="checkbox" name="emergency_capable" value="1" class="rounded border-slate-300 text-teal-600 focus:ring-teal-600"/>
            Siap layanan darurat 24/7
        </label>

        <div>
            <label class="mb-1.5 block text-sm font-semibold text-slate-700">Foto jasa (maks 6)</label>
            <input type="file" name="gallery[]" multiple accept=".jpg,.jpeg,.png,.webp" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-teal-50 file:px-3 file:py-1.5 file:text-sm file:font-bold file:text-teal-700"/>
            @error('gallery')<p class="mt-1 text-xs font-semibold text-rose-600">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-slate-400">JPG/PNG/WebP, maks 4MB per file.</p>
        </div>

        <x-ui.button type="submit" full>Publikasikan Jasa</x-ui.button>
    </form>
</div>
@endsection
