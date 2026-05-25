@php
    $size = $size ?? 22;
    $name = $name ?? 'grid';
@endphp

@switch($name)
    @case('search')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="6.5"></circle>
            <path d="M16 16l5 5"></path>
        </svg>
        @break

    @case('pin')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8 4h8"></path>
            <path d="M9 4v5l-3 3v1h12v-1l-3-3V4"></path>
            <path d="M12 13v7"></path>
        </svg>
        @break

    @case('sell')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 7h14l-1 12H6L5 7Z"></path>
            <path d="M9 7a3 3 0 0 1 6 0"></path>
            <path d="M12 10v6"></path>
            <path d="M9.5 13H14.5"></path>
        </svg>
        @break

    @case('buy')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="9" cy="19" r="1.5"></circle>
            <circle cx="17" cy="19" r="1.5"></circle>
            <path d="M3 5h2l2.2 9.2a1 1 0 0 0 1 .8h8.7a1 1 0 0 0 1-.7L21 8H8"></path>
        </svg>
        @break

    @case('expense')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3.5" y="6.5" width="17" height="11" rx="2.5"></rect>
            <path d="M7 12h10"></path>
            <path d="M12 9v6"></path>
        </svg>
        @break

    @case('approval')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="8"></circle>
            <path d="M8.5 12.2l2.3 2.3 4.7-5.1"></path>
        </svg>
        @break

    @case('report')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 19V9"></path>
            <path d="M12 19V5"></path>
            <path d="M19 19v-7"></path>
            <path d="M4 19h16"></path>
        </svg>
        @break

    @case('import')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 15V5"></path>
            <path d="M8.5 8.5L12 5l3.5 3.5"></path>
            <path d="M5 14.5v3a1.5 1.5 0 0 0 1.5 1.5h11a1.5 1.5 0 0 0 1.5-1.5v-3"></path>
        </svg>
        @break

    @case('stock')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 8.5 12 4l8 4.5-8 4.5L4 8.5Z"></path>
            <path d="M4 8.5V16l8 4 8-4V8.5"></path>
            <path d="M12 13v7"></path>
        </svg>
        @break

    @case('alert')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 4 20 18H4L12 4Z"></path>
            <path d="M12 9v4.5"></path>
            <circle cx="12" cy="16.7" r=".8" fill="currentColor" stroke="none"></circle>
        </svg>
        @break

    @case('wallet')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <rect x="7.5" y="3.5" width="9" height="17" rx="2.5"></rect>
            <path d="M10 7h4"></path>
            <path d="M10 16.5h4"></path>
        </svg>
        @break

    @case('bank')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9 12 4l9 5"></path>
            <path d="M5 10v8"></path>
            <path d="M9.5 10v8"></path>
            <path d="M14.5 10v8"></path>
            <path d="M19 10v8"></path>
            <path d="M3 20h18"></path>
        </svg>
        @break

    @case('settings')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3"></circle>
            <path d="M12 3.5v2"></path>
            <path d="M12 18.5v2"></path>
            <path d="M4.9 4.9l1.4 1.4"></path>
            <path d="M17.7 17.7l1.4 1.4"></path>
            <path d="M3.5 12h2"></path>
            <path d="M18.5 12h2"></path>
            <path d="M4.9 19.1l1.4-1.4"></path>
            <path d="M17.7 6.3l1.4-1.4"></path>
        </svg>
        @break

    @case('ops')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 4l7 3v5c0 4.5-2.8 7.3-7 8-4.2-.7-7-3.5-7-8V7l7-3Z"></path>
            <path d="M9.5 12.5 11 14l3.5-4"></path>
        </svg>
        @break

    @case('rocket')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 4c3.5 0 6 2.5 6 6-3 .5-5.5 3-6 6-3.5 0-6-2.5-6-6 0-3.5 2.5-6 6-6Z"></path>
            <path d="M8 16 5 19"></path>
            <path d="M10 18l-1.5 3"></path>
            <circle cx="14.5" cy="9.5" r="1.2"></circle>
        </svg>
        @break

    @case('customer')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="3.2"></circle>
            <path d="M5.5 18a6.5 6.5 0 0 1 13 0"></path>
        </svg>
        @break

    @case('team')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="9" cy="9" r="2.7"></circle>
            <circle cx="16.5" cy="10" r="2.2"></circle>
            <path d="M4.8 18a5.2 5.2 0 0 1 8.4-4.1"></path>
            <path d="M13.8 17.4a4.3 4.3 0 0 1 5.4-.6"></path>
        </svg>
        @break

    @case('building')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <rect x="5" y="3.5" width="14" height="17" rx="2"></rect>
            <path d="M9 7h1"></path>
            <path d="M14 7h1"></path>
            <path d="M9 11h1"></path>
            <path d="M14 11h1"></path>
            <path d="M9 15h1"></path>
            <path d="M14 15h1"></path>
            <path d="M11 20.5v-3h2v3"></path>
        </svg>
        @break

    @case('orders')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <rect x="6" y="4" width="12" height="16" rx="2"></rect>
            <path d="M9 9h6"></path>
            <path d="M9 13h6"></path>
            <path d="M9 17h4"></path>
        </svg>
        @break

    @case('calendar')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <rect x="4" y="6" width="16" height="14" rx="2"></rect>
            <path d="M8 4v4"></path>
            <path d="M16 4v4"></path>
            <path d="M4 10h16"></path>
        </svg>
        @break

    @case('document')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8 3.5h6l4 4V19a1.5 1.5 0 0 1-1.5 1.5h-8A1.5 1.5 0 0 1 7 19V5A1.5 1.5 0 0 1 8.5 3.5Z"></path>
            <path d="M14 3.5V8h4"></path>
            <path d="M9.5 12h5"></path>
            <path d="M9.5 15.5h5"></path>
        </svg>
        @break

    @case('gauge')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 17a7 7 0 1 1 14 0"></path>
            <path d="M12 12l3.5-2"></path>
            <path d="M12 17h.01"></path>
        </svg>
        @break

    @case('flash')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M13 3 6 13h5l-1 8 8-11h-5l0-7Z"></path>
        </svg>
        @break

    @case('pulse')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 12h4l2-4 4 8 2-4h6"></path>
        </svg>
        @break

    @case('pos')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <rect x="5" y="6" width="14" height="12" rx="2"></rect>
            <path d="M8 10h8"></path>
            <path d="M8 14h3"></path>
            <path d="M14 14h2"></path>
        </svg>
        @break

    @case('truck')
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 7h10v8H4Z"></path>
            <path d="M14 10h3l3 3v2h-6"></path>
            <circle cx="8" cy="18" r="1.8"></circle>
            <circle cx="18" cy="18" r="1.8"></circle>
            <path d="M9.8 18h6.4"></path>
        </svg>
        @break

    @default
        <svg aria-hidden="true" viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
            <rect x="4" y="4" width="6" height="6" rx="1.2"></rect>
            <rect x="14" y="4" width="6" height="6" rx="1.2"></rect>
            <rect x="4" y="14" width="6" height="6" rx="1.2"></rect>
            <rect x="14" y="14" width="6" height="6" rx="1.2"></rect>
        </svg>
@endswitch
