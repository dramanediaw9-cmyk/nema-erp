@php
    $size = (int) ($size ?? 48);
    $link = $link ?? ($product ? route('products.show', $product) : null);
    $title = $title ?? ($product?->display_name ?? $product?->name ?? 'Produit');
    $metaParts = collect([
        $product?->sku,
        $product?->barcode,
        $product?->is_variant ? $product?->variant_label : null,
    ])->filter()->unique()->values();
    $meta = $meta ?? $metaParts->implode(' | ');
    $initials = collect(preg_split('/\s+/', trim((string) $title)))->filter()->take(2)->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))->implode('') ?: 'PR';
    $sizeCss = "var(--product-inline-size, {$size}px)";
    $radiusCss = 'var(--product-inline-radius, '.max(12, (int) floor($size / 3)).'px)';
    $gapCss = 'var(--product-inline-gap, 10px)';
    $indicatorCss = 'var(--product-inline-indicator-size, '.max(10, (int) floor($size / 4)).'px)';
    $titleSizeCss = 'var(--product-inline-title-size, 14px)';
    $metaSizeCss = 'var(--product-inline-meta-size, 12px)';
@endphp

@if ($product)
    <div style="display:flex; align-items:center; gap:{{ $gapCss }}; min-width:0;">
        @php($thumb = $product->image_url)
        @if ($link)
            <a href="{{ $link }}" style="position:relative; display:inline-flex; flex:0 0 {{ $sizeCss }}; width:{{ $sizeCss }}; height:{{ $sizeCss }}; border-radius:{{ $radiusCss }}; overflow:hidden; border:1px solid rgba(102, 82, 56, 0.12); background:linear-gradient(180deg, #fffdf8 0%, #f4ece0 100%); align-items:center; justify-content:center; box-shadow:0 10px 20px rgba(42, 28, 18, 0.08);">
        @endif
        @if ($thumb)
            <img src="{{ $thumb }}" alt="{{ $title }}" style="width:100%; height:100%; object-fit:cover; display:block;">
            <span style="position:absolute; inset:auto 4px 4px auto; min-width:{{ $indicatorCss }}; height:{{ $indicatorCss }}; border-radius:999px; border:2px solid #fff8f0; background:linear-gradient(135deg, #22c55e 0%, #16a34a 100%);"></span>
        @else
            <span style="display:inline-flex; width:{{ $sizeCss }}; height:{{ $sizeCss }}; align-items:center; justify-content:center; border-radius:{{ $radiusCss }}; background:linear-gradient(135deg, #f4dcc0 0%, #efc99a 50%, #f7ead7 100%); color:#5d3a18; font-weight:900; letter-spacing:.04em; font-size:calc({{ $sizeCss }} / 2.8);">{{ $initials }}</span>
        @endif
        @if ($link)
            </a>
        @endif
        <div style="display:grid; gap:4px; min-width:0;">
            @if ($link)
                <a href="{{ $link }}" style="font-weight:800; line-height:1.28; color:#1f1a14; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; text-decoration:none; font-size:{{ $titleSizeCss }};">{{ $title }}</a>
            @else
                <div style="font-weight:800; line-height:1.28; color:#1f1a14; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; font-size:{{ $titleSizeCss }};">{{ $title }}</div>
            @endif
            @if ($meta)
                <div class="muted" style="font-size:{{ $metaSizeCss }}; line-height:1.25; max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $meta }}</div>
            @endif
        </div>
    </div>
@else
    <span class="muted">Produit indisponible</span>
@endif
