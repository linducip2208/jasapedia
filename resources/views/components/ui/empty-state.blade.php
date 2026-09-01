@props(['title' => 'Belum ada data', 'description' => null, 'actionUrl' => null, 'actionLabel' => null])
<div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50/50 px-6 py-14 text-center">
    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-teal-50 text-teal-600">
        <x-brand.mark class="h-7 w-7"/>
    </div>
    <h3 class="mt-4 font-semibold text-slate-800">{{ $title }}</h3>
    @if($description)<p class="mt-1 max-w-sm text-sm text-slate-500">{{ $description }}</p>@endif
    @if($actionUrl && $actionLabel)
        <a href="{{ $actionUrl }}" class="mt-4 inline-flex h-10 items-center rounded-xl bg-teal-600 px-4 text-sm font-semibold text-white hover:bg-teal-700">{{ $actionLabel }}</a>
    @endif
    {{ $slot }}
</div>
