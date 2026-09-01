@extends('layouts.guest')

@section('title', 'Jadi Penyedia | Jasapedia Partner')

@section('content')
<div class="text-center">
    <h1 class="text-lg font-extrabold text-slate-900">Jadi Penyedia Jasapedia</h1>
    <p class="mt-1 text-sm text-slate-500">3 langkah: profil → keahlian → verifikasi.</p>
</div>

<form method="POST" action="{{ route('web.partner.onboarding.complete') }}" class="mt-5 space-y-4">
    @csrf
    <fieldset class="rounded-xl border border-slate-200 p-3.5">
        <legend class="px-1 text-xs font-bold uppercase tracking-wide text-slate-400">1 · Tipe penyedia</legend>
        <div class="mt-1 space-y-2.5">
            <x-ui.radio name="type" value="individual" label="Teknisi/pekerja individu" :checked="old('type', 'individual') === 'individual'"/>
            <x-ui.radio name="type" value="freelancer" label="Freelancer digital" :checked="old('type') === 'freelancer'"/>
            <x-ui.radio name="type" value="vendor_company" label="Perusahaan jasa (punya tim)" :checked="old('type') === 'vendor_company'"/>
        </div>
    </fieldset>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.input name="display_name" label="Nama tampil / nama usaha" :value="old('display_name', auth()->user()->name)" required/>
        <x-ui.input name="city" label="Kota domisili" placeholder="Jakarta Selatan" :value="old('city')"/>
    </div>

    <x-ui.textarea name="about" label="Tentang layanan kamu" placeholder="Ceritakan pengalaman dan layanan yang kamu tawarkan..." :rows="3"/>

    <div>
        <label class="mb-1.5 block text-sm font-semibold text-slate-700">2 · Keahlian (pisahkan dengan koma saat mengisi)</label>
        <input type="text" name="skills[]" placeholder="Contoh: cuci-ac, servis-laptop, desain-grafis" class="h-11 w-full rounded-xl border border-slate-300 px-3.5 text-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-600/20"/>
    </div>

    <div x-data="{ isCompany: {{ old('type') === 'vendor_company' ? 'true' : 'false' }} }" class="space-y-3 rounded-xl border border-slate-200 p-3.5">
        <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-700">
            <input type="checkbox" x-model="isCompany" name="has_company_detail" value="1" class="rounded border-slate-300 text-teal-600 focus:ring-teal-600"/>
            3 · Detail perusahaan (untuk vendor_company)
        </label>
        <div x-show="isCompany" x-cloak class="grid gap-3">
            <x-ui.input name="organization[name]" label="Nama usaha" :value="old('organization.name')"/>
            <x-ui.input name="organization[npwp]" label="NPWP (opsional)" :value="old('organization.npwp')"/>
        </div>
    </div>

    <x-ui.button type="submit" full>Simpan & Lanjut Verifikasi</x-ui.button>
</form>
@endsection
