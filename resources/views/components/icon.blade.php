@props(['name', 'class' => ''])

<svg {{ $attributes->merge(['class' => 'ui-icon '.$class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('camera')
            <path d="M4 7.5h3l1.5-2h7l1.5 2h3v11H4z" />
            <circle cx="12" cy="13" r="3.5" />
            @break
        @case('check')
            <path d="m5 12.5 4.2 4.2L19.5 6.5" />
            @break
        @case('report')
            <path d="M7 3.5h7l4 4V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z" />
            <path d="M14 3.5v4h4M9 12h6M9 15.5h6" />
            @break
        @case('tools')
            <path d="m14.5 6.5 3-3a5 5 0 0 1-6.8 6.8l-6.6 6.6a2 2 0 0 0 2.8 2.8l6.6-6.6a5 5 0 0 1 6.8-6.8l-3 3-2.8-.2Z" />
            @break
        @case('home')
            <path d="m3.5 10 8.5-7 8.5 7" />
            <path d="M5.5 9v11h13V9M9.5 20v-6h5v6" />
            @break
        @case('calendar')
            <rect x="3.5" y="5" width="17" height="16" rx="2" />
            <path d="M7.5 3v4M16.5 3v4M3.5 9h17M8 13h2M14 13h2M8 17h2" />
            @break
        @case('share')
            <circle cx="18" cy="5" r="2.5" />
            <circle cx="6" cy="12" r="2.5" />
            <circle cx="18" cy="19" r="2.5" />
            <path d="m8.2 10.8 7.6-4.4M8.2 13.2l7.6 4.4" />
            @break
        @case('eye')
            <path d="M2.5 12s3.2-6 9.5-6 9.5 6 9.5 6-3.2 6-9.5 6-9.5-6-9.5-6Z" />
            <circle cx="12" cy="12" r="2.5" />
            @break
        @case('eye-off')
            <path d="m3 3 18 18M10.6 6.2a10.8 10.8 0 0 1 1.4-.2c6.3 0 9.5 6 9.5 6a16 16 0 0 1-3 3.8M6.2 6.6C3.8 8.2 2.5 12 2.5 12s3.2 6 9.5 6c1.2 0 2.2-.2 3.2-.6" />
            <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" />
            @break
    @endswitch
</svg>
