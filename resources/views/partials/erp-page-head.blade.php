@php
    $eyebrow = $eyebrow ?? null;
    $title = $title ?? '';
    $description = $description ?? null;
    $actions = collect($actions ?? [])
        ->filter(fn ($action) => is_array($action) && filled($action['label'] ?? null) && filled($action['url'] ?? null))
        ->values();
    $chips = collect($chips ?? [])
        ->map(function ($chip) {
            if (is_array($chip)) {
                return $chip;
            }

            if (filled($chip)) {
                return ['label' => (string) $chip, 'tone' => 'muted'];
            }

            return null;
        })
        ->filter()
        ->values();
@endphp

<div class="page-head">
    <div style="display:grid; gap:8px; min-width:0;">
        @if ($eyebrow)
            <div class="topbar-label" style="margin:0;">{{ $eyebrow }}</div>
        @endif
        <div>
            <h2 style="margin:0;">{{ $title }}</h2>
            @if ($description)
                <div class="muted">{{ $description }}</div>
            @endif
        </div>
        @if ($chips->isNotEmpty())
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                @foreach ($chips as $chip)
                    @include('partials.erp-status-badge', [
                        'status' => $chip['status'] ?? null,
                        'type' => $chip['type'] ?? null,
                        'value' => $chip['value'] ?? null,
                        'label' => $chip['label'] ?? null,
                        'tone' => $chip['tone'] ?? 'muted',
                    ])
                @endforeach
            </div>
        @endif
    </div>
    @if ($actions->isNotEmpty())
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            @foreach ($actions as $action)
                <a href="{{ $action['url'] }}" class="button button-{{ $action['style'] ?? 'secondary' }}">{{ $action['label'] }}</a>
            @endforeach
        </div>
    @endif
</div>
