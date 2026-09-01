@props(['message' => null, 'type' => 'success'])
@if(session($type) || session('success') || session('info') || session('warning') || $errors->any() || $message)
@php
    $text = $message ?? session('success') ?? session('info') ?? session('warning') ?? session($type) ?? ($errors->first() ? 'Periksa kembali isian formulir.' : null);
    $toneMap = ['success' => 'success', 'info' => 'info', 'warning' => 'warning', 'error' => 'danger'];
    $tone = $errors->any() && ! session('success') ? 'danger' : ($toneMap[$type] ?? 'success');
@endphp
<div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)" x-transition.opacity
    class="fixed bottom-20 left-1/2 z-[60] -translate-x-1/2 px-4 sm:bottom-6 sm:left-auto sm:right-6 sm:translate-x-0" role="status" aria-live="polite">
    <div class="flex items-center gap-2.5 rounded-xl px-4 py-3 text-sm font-semibold text-white shadow-lg {{ $tone === 'danger' ? 'bg-rose-600' : ($tone === 'warning' ? 'bg-amber-500' : ($tone === 'info' ? 'bg-sky-600' : 'bg-emerald-600')) }}">
        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
        <span class="max-w-xs sm:max-w-sm">{{ $text }}</span>
        <button @click="show = false" aria-label="Tutup notifikasi" class="ml-1 opacity-70 hover:opacity-100">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
    </div>
</div>
@endif
