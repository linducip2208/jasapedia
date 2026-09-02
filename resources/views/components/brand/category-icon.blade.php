@props(['icon' => null, 'class' => 'h-6 w-6'])

@php
    // Category icon paths (24x24 stroke) — mirrors DemoMediaPool::ICON_PATHS
    $paths = [
        'code' => '<path d="M8 9l-4 3 4 3M16 9l4 3-4 3M13 5l-2 14"/>',
        'pen-tool' => '<path d="M12 19l7-7-4-4-7 7-1 5z"/><path d="M15 8l1-5 4 4-5 1"/>',
        'megaphone' => '<path d="M3 11l16-7-4 16-5-5z"/><path d="M10 15l-2 6"/>',
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'calculator' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8M8 12h.01M12 12h.01M16 12h.01M8 16h.01M12 16h.01M16 16h.01"/>',
        'scale' => '<path d="M12 3v18M5 7h14M7 7l-3 7a4 4 0 0 0 6 0zM17 7l-3 7a4 4 0 0 0 6 0z"/>',
        'spray' => '<path d="M9 3v6M6 9h6l1 12H5zM18 3l3 3-9 9-3-3z"/>',
        'ac-unit' => '<rect x="3" y="5" width="18" height="7" rx="1"/><path d="M6 16c1-1 3-1 4 0s3 1 4 0 3-1 4 0M6 19c1-1 3-1 4 0"/>',
        'pipe' => '<path d="M14 7a3 3 0 1 1 4 4l-6 6a4 4 0 0 1-6-6z"/><path d="M12 9l3 3"/>',
        'zap' => '<path d="M13 2L4 14h6l-1 8 9-12h-6z"/>',
        'drill' => '<path d="M14 7l3-3 3 3-3 3M3 21l8-8M17 13l4 4-4 4-4-4"/>',
        'paint-roller' => '<rect x="3" y="4" width="12" height="6" rx="1"/><path d="M15 7h4v4h-5v3M14 14v6"/>',
        'crane' => '<path d="M5 21V6l10-3M5 9h14M15 6v4"/><path d="M15 10v5a2 2 0 0 0 4 0v-1"/>',
        'cctv' => '<path d="M3 8l13-4 2 6-13 4zM7 13v4a2 2 0 0 0 2 2h4"/><circle cx="19" cy="15" r="2"/>',
        'bug' => '<circle cx="12" cy="9" r="4"/><path d="M8 9H3M21 9h-5M9 13l-4 6M15 13l4 6M12 13v6"/>',
        'car' => '<path d="M5 13l1.5-4A2 2 0 0 1 8.4 8h7.2a2 2 0 0 1 1.9 1.3L19 13"/><rect x="3" y="13" width="18" height="5" rx="1"/><circle cx="7" cy="18" r="1.5"/><circle cx="17" cy="18" r="1.5"/>',
        'truck' => '<rect x="1" y="7" width="13" height="9" rx="1"/><path d="M14 10h4l3 3v3h-7"/><circle cx="6" cy="18" r="1.5"/><circle cx="17" cy="18" r="1.5"/>',
        'stage' => '<path d="M4 21V9l8-6 8 6v12"/><path d="M4 13h16"/><circle cx="12" cy="6" r="1"/>',
        'camera' => '<rect x="2" y="7" width="20" height="13" rx="2"/><circle cx="12" cy="13" r="4"/><path d="M8 7l2-3h4l2 3"/>',
        'book' => '<path d="M2 8l10-5 10 5-10 5z"/><path d="M6 10v5c0 2 12 2 12 0v-5M22 8v6"/>',
        'spa' => '<circle cx="12" cy="6" r="3"/><path d="M12 9v6M8 21c0-3 1.8-6 4-6s4 3 4 6"/>',
    ];
    $path = $paths[$icon] ?? '<circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/>';
@endphp

<svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $path !!}</svg>
