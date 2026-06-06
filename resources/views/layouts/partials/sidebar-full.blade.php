@php
    $modules = $erpNavigation['modules'] ?? [];
    $activeModule = $erpNavigation['active_module'] ?? null;
    $activeMenu = $erpNavigation['active_menu'] ?? [];
    $quickActions = $erpNavigation['quick_actions'] ?? [];
    $supportLinks = $erpNavigation['support_links'] ?? [];
@endphp

<nav class="sidebar-nav">
    @if (! empty($modules))
        <section class="nav-section nav-section--spotlight">
            <div class="nav-title">Applications</div>
            @foreach ($modules as $module)
                <a class="nav-link {{ $module['active'] ? 'active' : '' }}" href="{{ $module['url'] }}">
                    <span style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:12px; color:{{ $module['accent'] }}; background:{{ $module['surface'] }}; border:1px solid {{ $module['border'] }}; flex-shrink:0;">
                        @include('dashboard.partials.icon', ['name' => $module['icon'] ?? 'grid', 'size' => 16])
                    </span>
                    <span style="display:grid; gap:2px; min-width:0;">
                        <strong style="font-size:13px; font-weight:800; line-height:1.2;">{{ $module['label'] }}</strong>
                        <small style="color:#9cc0c0; font-size:11px; line-height:1.25;">{{ $module['hint'] }}</small>
                    </span>
                </a>
            @endforeach
        </section>
    @endif

    @if ($activeModule && ! empty($activeMenu))
        <section class="nav-section">
            <div class="nav-title">Dans {{ $activeModule['label'] }}</div>
            @foreach ($activeMenu as $item)
                <a class="nav-link {{ $item['active'] ? 'active' : '' }}" href="{{ $item['url'] }}">
                    <span style="display:grid; gap:2px; min-width:0;">
                        <strong style="font-size:13px; font-weight:800; line-height:1.2;">{{ $item['label'] }}</strong>
                    </span>
                </a>
            @endforeach
        </section>
    @endif

    @if ($activeModule && ! empty($quickActions))
        <section class="nav-section">
            <div class="nav-title">Actions rapides</div>
            @foreach ($quickActions as $action)
                <a class="nav-link {{ $action['active'] ? 'active' : '' }}" href="{{ $action['url'] }}">
                    <span style="display:grid; gap:2px; min-width:0;">
                        <strong style="font-size:13px; font-weight:800; line-height:1.2;">{{ $action['label'] }}</strong>
                    </span>
                </a>
            @endforeach
        </section>
    @endif

    @if (! empty($supportLinks))
        <section class="nav-section">
            <div class="nav-title">Acces transverses</div>
            @foreach ($supportLinks as $item)
                <a class="nav-link {{ $item['active'] ? 'active' : '' }}" href="{{ $item['url'] }}">
                    <span style="display:grid; gap:2px; min-width:0;">
                        <strong style="font-size:13px; font-weight:800; line-height:1.2;">{{ $item['label'] }}</strong>
                    </span>
                </a>
            @endforeach
        </section>
    @endif

    <section class="nav-section">
        <div class="nav-title">Compte</div>
        <a class="nav-link {{ request()->routeIs('account.profile.*') ? 'active' : '' }}" href="{{ route('account.profile.edit') }}">
            <span style="display:grid; gap:2px; min-width:0;">
                <strong style="font-size:13px; font-weight:800; line-height:1.2;">Mon compte</strong>
            </span>
        </a>
    </section>
</nav>
