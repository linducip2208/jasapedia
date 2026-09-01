@props(['align' => 'right', 'width' => 'w-56'])
<div x-data="{ open: false }" class="relative">
    <button @click="open = !open" @click.outside="open = false" @keydown.escape.window="open = false" class="focus-visible:outline-none" aria-haspopup="true" aria-expanded="false">
        {{ $trigger }}
    </button>
    <div x-show="open" x-transition.opacity.duration.150ms x-cloak
        class="absolute z-40 mt-2 {{ $align === 'right' ? 'right-0' : 'left-0' }} {{ $width }} overflow-hidden rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg">
        {{ $slot }}
    </div>
</div>
