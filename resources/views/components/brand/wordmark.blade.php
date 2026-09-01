{{-- Icon + wordmark lockup --}}
@props(['dark' => false, 'class' => ''])
<a href="{{ route('web.home') }}" {{ $attributes->merge(['class' => 'flex items-center gap-2.5 '.$class]) }} aria-label="Jasapedia — Semua Jasa, Satu Platform">
    <x-brand.logo class="h-9 w-9 shrink-0"/>
    <span class="text-xl font-extrabold tracking-tight {{ $dark ? 'text-white' : 'text-slate-900' }}">Jasa<span class="text-teal-600">pedia</span></span>
</a>
