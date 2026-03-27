@php
    $size = $size ?? 60;
    $link = $link ?? ($product ? route('products.show', $product) : null);
    $title = $title ?? ($product?->name ?? 'Produit');
    $meta = $meta ?? collect([$product?->sku, $product?->barcode])->filter()->implode(' · ');
    $initials = collect(preg_split('/\s+/', trim((string) $title)))->filter()->take(2)->map(fn ($part) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($part, 0, 1)))->implode('') ?: 'PR';
    $radius = max(16, (int) floor($size / 3));
@endphp

@if ($product)
    <div style="display:flex; align-items:center; gap:14px; min-width:0;">
        @php($thumb = $product->image_url)
        @if ($link)
            <a href="{{ $link }}" style="position:relative; display:inline-flex; flex:0 0 {{ $size }}px; width:{{ $size }}px; height:{{ $size }}px; border-radius:{{ $radius }}px; overflow:hidden; border:1px solid #d8e3f1; background:linear-gradient(180deg, #ffffff 0%, #f7fbff 100%); align-items:center; justify-content:center; box-shadow:0 14px 24px rgba(15, 23, 42, 0.07);">
        @endif
        @if ($thumb)
            <img src="{{ $thumb }}" alt="{{ $title }}" style="width:100%; height:100%; object-fit:cover; display:block;">
            <span style="position:absolute; inset:auto 6px 6px auto; min-width:14px; height:14px; border-radius:999px; border:2px solid #fff; background:linear-gradient(135deg, #22c55e 0%, #16a34a 100%);"></span>
        @else
            <span style="display:inline-flex; width:{{ $size }}px; height:{{ $size }}px; align-items:center; justify-content:center; border-radius:{{ $radius }}px; background:linear-gradient(135deg, #dbeafe 0%, #bfdbfe 48%, #dbeafe 100%); color:#16324f; font-weight:900; letter-spacing:.04em; font-size:{{ max(13, (int) floor($size / 2.6)) }}px;">{{ $initials }}</span>
        @endif
        @if ($link)
            </a>
        @endif
        <div style="display:grid; gap:6px; min-width:0;">
            @if ($link)
                <a href="{{ $link }}" style="font-weight:800; line-height:1.32; color:#14253b; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; text-decoration:none;">{{ $title }}</a>
            @else
                <div style="font-weight:800; line-height:1.32; color:#14253b; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">{{ $title }}</div>
            @endif
            @if ($meta)
                <div style="display:flex; flex-wrap:wrap; gap:6px; align-items:center; min-width:0;">
                    <span class="muted" style="font-size:13px; line-height:1.35; display:inline-flex; align-items:center; gap:6px; padding:5px 10px; border-radius:999px; background:#f1f6fd; border:1px solid #d9e5f5; color:#49627f; max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $meta }}</span>
                </div>
            @endif
        </div>
    </div>
@else
    <span class="muted">Produit indisponible</span>
@endif
