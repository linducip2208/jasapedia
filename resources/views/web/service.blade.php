@extends('layouts.app')

@section('title', $service->title)

@section('content')
<div class="grid gap-8 lg:grid-cols-[1fr_360px]">
    <div class="space-y-6">
        <div class="rounded-2xl bg-gradient-to-br from-indigo-100 to-teal-100 p-10 text-center text-5xl">{{ $service->category->icon ?? '🛠️' }}</div>

        <div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="rounded-full bg-indigo-50 px-2 py-1 font-medium text-indigo-700">{{ $service->category->name }}</span>
                <span class="rounded-full bg-slate-100 px-2 py-1">{{ $service->delivery_mode === 'onsite' ? 'Datang ke lokasi' : ($service->delivery_mode === 'remote' ? 'Remote' : ucfirst($service->delivery_mode)) }}</span>
                @if($service->emergency_capable)<span class="rounded-full bg-rose-50 px-2 py-1 font-medium text-rose-600">⚡ ASAP</span>@endif
                @if($service->warranty_days > 0)<span class="rounded-full bg-teal-50 px-2 py-1 font-medium text-teal-700">Garansi {{ $service->warranty_days }} hari</span>@endif
            </div>
            <h1 class="mt-3 text-2xl font-bold">{{ $service->title }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ $service->partner->display_name }} @if($service->partner->isVerified())<span class="text-teal-600">✔ Terverifikasi</span>@endif · ★ {{ $service->partner->rating_avg }} ({{ $service->partner->rating_count }} ulasan) · {{ $service->partner->completed_jobs }} pekerjaan</p>
        </div>

        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="font-bold">Deskripsi</h2>
            <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $service->description }}</p>
            @if($service->inclusions)
            <h3 class="mt-4 text-sm font-bold">Termasuk</h3>
            <p class="text-sm text-slate-700">{{ $service->inclusions }}</p>
            @endif
            @if($service->exclusions)
            <h3 class="mt-4 text-sm font-bold">Tidak termasuk</h3>
            <p class="text-sm text-slate-700">{{ $service->exclusions }}</p>
            @endif
        </section>

        @if($service->addons->isNotEmpty())
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="font-bold">Layanan Tambahan</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach($service->addons as $addon)
                <li class="flex justify-between"><span>{{ $addon->name }}</span><span class="font-medium">Rp{{ number_format($addon->price, 0, ',', '.') }}</span></li>
                @endforeach
            </ul>
        </section>
        @endif
    </div>

    {{-- Checkout card --}}
    <aside class="h-fit space-y-4 rounded-xl border border-slate-200 bg-white p-5 lg:sticky lg:top-20" x-data="{ qty: {{ $service->min_quantity ?: 1 }}, emergency: false }">
        <p class="text-2xl font-bold text-indigo-700">Rp<span x-text="({{ $service->base_price }} * qty).toLocaleString('id-ID')"></span>{{ $service->unit_label ? '/'.$service->unit_label : '' }}</p>
        <form method="POST" action="{{ route('web.checkout') }}">
            @csrf
            <input type="hidden" name="service_id" value="{{ $service->id }}">
            @if(in_array($service->price_model, ['per_unit']))
            <label class="block text-sm font-medium">Jumlah {{ $service->unit_label ?? 'unit' }}
                <input type="number" name="quantity" min="{{ $service->min_quantity ?: 1 }}" x-model="qty" class="mt-1 w-full rounded-lg border-slate-300" required>
            </label>
            @endif
            @if(in_array($service->fulfillment_type, ['appointment', 'per_unit', 'instant_booking']))
            <label class="mt-3 block text-sm font-medium">Jadwal
                <input type="datetime-local" name="scheduled_at" class="mt-1 w-full rounded-lg border-slate-300" required>
            </label>
            @endif
            @if($service->emergency_capable)
            <label class="mt-3 flex items-center gap-2 text-sm">
                <input type="checkbox" name="emergency" value="1" x-model="emergency" class="rounded border-slate-300">
                Butuh teknisi segera (+Rp{{ number_format($service->emergency_surcharge, 0, ',', '.') }})
            </label>
            @endif
            <label class="mt-3 block text-sm font-medium">Catatan untuk teknisi
                <textarea name="customer_note" rows="2" class="mt-1 w-full rounded-lg border-slate-300" placeholder="Opsional"></textarea>
            </label>
            <button class="mt-4 w-full rounded-full bg-indigo-600 py-3 font-bold text-white hover:bg-indigo-700 disabled:opacity-50" @if(!auth()->check()) disabled title="Login dulu" @endif>
                {{ auth()->check() ? 'Pesan Sekarang' : 'Login untuk memesan' }}
            </button>
            @if(!auth()->check())
            <a href="{{ route('login') }}" class="mt-2 block text-center text-sm font-medium text-indigo-600 hover:underline">Masuk / Daftar</a>
            @endif
            <p class="mt-3 text-center text-xs text-slate-500">Pembayaran aman · Dibayarkan ke vendor setelah selesai</p>
        </form>
    </aside>
</div>
@endsection
