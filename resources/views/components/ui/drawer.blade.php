@props(['name' => null])
<div x-data="{ open: true }" x-show="open" x-cloak class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="{{ $name ?? 'Panel' }}">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="open = false" aria-hidden="true"></div>
    <div class="absolute inset-y-0 right-0 flex w-full max-w-md flex-col bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="font-bold text-slate-900">{{ $name }}</h2>
            <button @click="open = false" class="rounded-full p-1.5 text-slate-400 hover:bg-slate-100" aria-label="Tutup">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-5">{{ $slot }}</div>
    </div>
</div>
