{{-- Full app icon: rounded squircle container + mark --}}
@props(['class' => 'h-9 w-9'])
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 512 512" fill="none" aria-hidden="true">
    <defs>
        <linearGradient id="jp-brand-bg-{{ md5($class) }}" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#0D9488"/>
            <stop offset="1" stop-color="#115E59"/>
        </linearGradient>
    </defs>
    <rect width="512" height="512" rx="120" fill="url(#jp-brand-bg-{{ md5($class) }})"/>
    <g stroke="#FFFFFF" stroke-width="34" stroke-linecap="round">
        <path d="M256 132 a124 124 0 0 1 107.4 62"/>
        <path d="M363.4 194 a124 124 0 0 1 0 124"/>
        <path d="M363.4 318 a124 124 0 0 1 -214.8 0"/>
        <path d="M148.6 318 a124 124 0 0 1 0 -124"/>
        <path d="M148.6 194 a124 124 0 0 1 62.6 -56.7"/>
    </g>
    <circle cx="256" cy="256" r="52" fill="#F59E0B"/>
</svg>
