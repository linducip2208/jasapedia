@props(['title' => 'Konfirmasi tindakan', 'description' => null, 'action' => '#', 'confirmLabel' => 'Ya, lanjutkan', 'cancelLabel' => 'Batal', 'danger' => false])
<div x-data="{ open: false }" class="inline">
    <button @click="open = true" class="{{ $attributes->get('class') }}">{{ $slot }}</button>
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="alertdialog" aria-modal="true" aria-label="{{ $title }}">
        <div class="absolute inset-0 bg-slate-900/50" @click="open = false" aria-hidden="true"></div>
        <div class="relative w-full max-w-sm rounded-t-2xl bg-white p-6 shadow-xl sm:rounded-2xl">
            <h3 class="font-bold text-slate-900">{{ $title }}</h3>
            @if($description)<p class="mt-1.5 text-sm text-slate-500">{{ $description }}</p>@endif
            <form method="POST" action="{{ $action }}" class="mt-5 flex gap-2.5">
                @csrf
                <button type="button" @click="open = false" class="h-11 flex-1 rounded-xl border border-slate-300 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ $cancelLabel }}</button>
                <button type="submit" class="h-11 flex-1 rounded-xl text-sm font-semibold text-white {{ $danger ? 'bg-rose-600 hover:bg-rose-700' : 'bg-teal-600 hover:bg-teal-700' }}">{{ $confirmLabel }}</button>
            </form>
        </div>
    </div>
</div>
