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
        @case('church') <path d="M9 4h6m-3-2v6m-7 5 7-5 7 5v8H5v-8Zm-3 3 3-3m14 0 3 3M10 21v-5h4v5"/> @break
        @case('facebook') <circle cx="12" cy="12" r="9"/><path d="M14.5 8h-1.25A2.25 2.25 0 0 0 11 10.25V21m-3-7h7"/> @break
        @case('phone') <path d="M8.4 3H5.2A2.2 2.2 0 0 0 3 5.2C3 13.9 10.1 21 18.8 21a2.2 2.2 0 0 0 2.2-2.2v-3.2l-4.1-1-1.1 2.2a14.7 14.7 0 0 1-8.6-8.6l2.2-1.1L8.4 3Z"/> @break
        @case('mail') <rect x="3" y="5" width="18" height="14" rx="1"/><path d="m4 7 8 6 8-6"/> @break
        @default <path d="M10 2h4v6h6v4h-6v10h-4V12H4V8h6V2Z"/>
    @endswitch
</svg>
