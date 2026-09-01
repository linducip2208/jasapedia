@props(['order'])
@php
$label = \App\Support\Money::format($order->total);
@endphp
<article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $order->code }}</p>
            <a href="{{ route('web.orders.show', ['id' => $order->id]) }}" class="mt-0.5 block truncate font-semibold text-slate-900 hover:text-teal-700">
                {{ $order->service?->title ?? ($order->items->first()?->title ?? 'Pesanan') }}
            </a>
            <p class="mt-0.5 text-xs text-slate-500">{{ $order->created_at->translatedFormat('d M Y, H:i') }}</p>
        </div>
        <div class="text-right">
            <x-ui.status-badge :status="$order->status"/>
            <p class="mt-1 text-sm font-bold text-slate-900">{{ $label }}</p>
        </div>
    </div>
</article>
