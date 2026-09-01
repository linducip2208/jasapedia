@props(['provider'])
@php
$level = $provider->level_label ?? 'Penyedia';
@endphp
<article class="flex flex-col items-center rounded-2xl border border-slate-200 bg-white p-5 text-center shadow-sm transition hover:shadow-md">
    <a href="{{ route('web.provider.show', ['slug' => $provider->slug ?? $provider->id]) }}" class="flex flex-col items-center">
        <x-ui.avatar :name="$provider->display_name" :src="$provider->avatar_url ?? null" :verified="(bool) ($provider->verified_at ?? $provider->is_verified ?? false)" size="lg"/>
        <h3 class="mt-2.5 line-clamp-1 font-semibold text-slate-900 hover:text-teal-700">{{ $provider->display_name }}</h3>
        <p class="mt-0.5 text-xs text-slate-500">{{ $provider->city?->name ?? $provider->city_name ?? 'Indonesia' }}</p>
        <div class="mt-2"><x-ui.rating :value="$provider->rating_avg ?? 0" :count="$provider->reviews_count ?? null" size="xs"/></div>
        <x-ui.badge tone="teal" class="mt-2">{{ $level }}</x-ui.badge>
    </a>
</article>
