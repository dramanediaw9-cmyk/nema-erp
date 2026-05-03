@php
    if (! isset($status)) {
        $status = isset($type)
            ? \App\Support\ErpStatusPresenter::present($type, $value ?? null, ['label' => $label ?? null, 'tone' => $tone ?? null])
            : \App\Support\ErpStatusPresenter::present('generic', $value ?? null, ['label' => $label ?? null, 'tone' => $tone ?? null]);
    }
@endphp

<span class="badge {{ $status['class'] ?? 'badge-muted' }}">{{ $status['label'] ?? ($label ?? '') }}</span>
