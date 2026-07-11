@props(['name'])

<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('menu')
            <path d="M4 7h16M4 12h16M4 17h16" />
            @break
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="2" /><rect x="14" y="3" width="7" height="7" rx="2" /><rect x="3" y="14" width="7" height="7" rx="2" /><rect x="14" y="14" width="7" height="7" rx="2" />
            @break
        @case('procurement')
            <path d="M6 3h12l2 5H4l2-5Z" /><path d="M5 8v12h14V8M9 12h6M9 16h4" />
            @break
        @case('document')
            <path d="M7 3h7l4 4v14H7z" /><path d="M14 3v5h5M10 12h5M10 16h5" />
            @break
        @case('receipt')
            <path d="M6 3h12v18l-3-2-3 2-3-2-3 2z" /><path d="M9 8h6M9 12h6M9 16h3" />
            @break
        @case('inventory')
            <path d="m4 8 8-4 8 4-8 4z" /><path d="m4 8v8l8 4 8-4V8M12 12v8" />
            @break
        @case('warehouse')
            <path d="m3 10 9-6 9 6v10H3z" /><path d="M7 20v-6h10v6M8 10h.01M12 10h.01M16 10h.01" />
            @break
        @case('asset')
            <rect x="3" y="6" width="18" height="13" rx="3" /><path d="M9 6V4h6v2M3 11h18M10 14h4" />
            @break
        @case('maintenance')
            <path d="M14.7 6.3a4 4 0 0 0-5 5L4 17l3 3 5.7-5.7a4 4 0 0 0 5-5l-2.6 2.6-3-3z" />
            @break
        @case('reports')
            <path d="M4 20V10M10 20V4M16 20v-7M22 20H2" />
            @break
        @case('master')
            <ellipse cx="12" cy="5" rx="8" ry="3" /><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6" />
            @break
        @case('users')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
            @break
        @case('approval')
            <path d="M9 11l3 3L22 4" /><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
            @break
        @case('audit')
            <path d="M9 3h6l1 3h3v15H5V6h3z" /><path d="M9 12h6M9 16h4M9 8h6" />
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21h-4v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H3v-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.6V3h4v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.1v4H21a1.7 1.7 0 0 0-1.6 1Z" />
            @break
        @case('building')
            <path d="M4 21V4h11v17M15 9h5v12M8 8h3M8 12h3M8 16h3M18 13h.01M18 17h.01M2 21h20" />
            @break
        @case('chevron')
            <path d="m6 9 6 6 6-6" />
            @break
        @default
            <circle cx="12" cy="12" r="9" /><path d="M12 8v8M8 12h8" />
    @endswitch
</svg>
