@props(['name' => 'cross'])
<svg {{ $attributes->merge(['class' => 'size-6']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('arrow') <path d="M4 12h15m-6-6 6 6-6 6"/> @break
        @case('book') <path d="M12 6c-3-2-6-2-10-1v14c4-1 7-1 10 1 3-2 6-2 10-1V5c-4-1-7-1-10 1Zm0 0v14"/> @break
        @case('heart') <path d="M20 5c-3-3-6-1-8 1-2-2-5-4-8-1-5 5 1 10 8 15 7-5 13-10 8-15Z"/> @break
        @case('people') <circle cx="9" cy="7" r="3"/><path d="M2 21v-3a7 7 0 0 1 14 0v3m1-17a3 3 0 0 1 0 6m3 11v-3a6 6 0 0 0-3-5"/> @break
        @case('sun') <circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1 1m12 12 1 1M5 19l1-1M18 6l1-1"/> @break
        @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/> @break
        @case('calendar') <rect x="3" y="5" width="18" height="16" rx="1"/><path d="M7 3v4m10-4v4M3 10h18m-13 4h2m4 0h2m-8 4h2"/> @break
        @case('dashboard') <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/> @break
        @case('inbox') <path d="M4 4h16v16H4V4Zm0 11h4l2 2h4l2-2h4"/> @break
        @case('report') <path d="M5 3h14v18H5V3Zm4 13v-4m3 4V8m3 8v-6"/> @break
        @case('chart') <path d="M4 19V5m0 14h16M7 15l4-4 3 2 5-6"/> @break
        @case('settings') <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H3v-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V3h4v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/> @break
        @case('shield') <path d="M12 3 4 6v5c0 5 3.4 8.6 8 10 4.6-1.4 8-5 8-10V6l-8-3Zm-3 9 2 2 4-4"/> @break
        @case('audit') <path d="M5 3h14v18H5V3Zm4 5h6M9 12h6m-6 4h4"/> @break
        @case('lock') <rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 4v3"/> @break
        @case('menu') <path d="M4 7h16M4 12h16M4 17h16"/> @break
        @case('chevron-down') <path d="m6 9 6 6 6-6"/> @break
        @case('church') <path d="M9 4h6m-3-2v6m-7 5 7-5 7 5v8H5v-8Zm-3 3 3-3m14 0 3 3M10 21v-5h4v5"/> @break
        @case('facebook') <circle cx="12" cy="12" r="9"/><path d="M14.5 8h-1.25A2.25 2.25 0 0 0 11 10.25V21m-3-7h7"/> @break
        @case('phone') <path d="M8.4 3H5.2A2.2 2.2 0 0 0 3 5.2C3 13.9 10.1 21 18.8 21a2.2 2.2 0 0 0 2.2-2.2v-3.2l-4.1-1-1.1 2.2a14.7 14.7 0 0 1-8.6-8.6l2.2-1.1L8.4 3Z"/> @break
        @case('mail') <rect x="3" y="5" width="18" height="14" rx="1"/><path d="m4 7 8 6 8-6"/> @break
        @default <path d="M10 2h4v6h6v4h-6v10h-4V12H4V8h6V2Z"/>
    @endswitch
</svg>
