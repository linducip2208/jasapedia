@props(['label'])
<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex h-10 w-10 items-center justify-center rounded-full text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600', 'aria-label' => $label]) }}>
    {{ $slot }}
</button>
