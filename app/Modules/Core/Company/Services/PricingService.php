<?php

namespace App\Modules\Core\Company\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Core\Company\Models\PriceListItem;
use App\Modules\Partners\Models\Partner;
use Illuminate\Support\Collection;

class PricingService
{
    public function rulesForPriceList(int $companyId, ?int $priceListId, array $productIds = []): Collection
    {
        if (! $priceListId) {
            return collect();
        }

        return PriceListItem::query()
            ->where('company_id', $companyId)
            ->where('price_list_id', $priceListId)
            ->when($productIds !== [], fn ($query) => $query->whereIn('product_id', $productIds))
            ->orderBy('product_id')
            ->orderByDesc('min_qty')
            ->get(['product_id', 'min_qty', 'price'])
            ->groupBy('product_id');
    }

    public function resolveGroupedPrice(Collection|array|null $rules, float $qty, float $fallback): float
    {
        if (! $rules instanceof Collection) {
            return round($fallback, 2);
        }

        $matched = $rules->first(fn ($row) => (float) $row->min_qty <= $qty);

        return round((float) ($matched->price ?? $fallback), 2);
    }

    public function resolveForPartner(int $companyId, ?Partner $partner, Product $product, float $qty, float $fallback): float
    {
        if (! $partner?->price_list_id) {
            return round($fallback, 2);
        }

        return $this->resolveForPriceListId($companyId, $partner->price_list_id, $product->id, $qty, $fallback);
    }

    public function resolveForPriceListId(int $companyId, ?int $priceListId, int $productId, float $qty, float $fallback): float
    {
        if (! $priceListId) {
            return round($fallback, 2);
        }

        $matched = PriceListItem::query()
            ->where('company_id', $companyId)
            ->where('price_list_id', $priceListId)
            ->where('product_id', $productId)
            ->where('min_qty', '<=', $qty)
            ->orderByDesc('min_qty')
            ->first(['price']);

        return round((float) ($matched?->price ?? $fallback), 2);
    }

    public function rulesPayloadForCompany(int $companyId): array
    {
        return PriceListItem::query()
            ->where('company_id', $companyId)
            ->whereHas('priceList', fn ($query) => $query->where('is_active', true))
            ->orderBy('price_list_id')
            ->orderBy('product_id')
            ->orderByDesc('min_qty')
            ->get(['price_list_id', 'product_id', 'min_qty', 'price'])
            ->groupBy('price_list_id')
            ->map(fn (Collection $priceListRows) => $priceListRows
                ->groupBy('product_id')
                ->map(fn (Collection $productRows) => $productRows
                    ->map(fn (PriceListItem $row) => [
                        'min_qty' => (float) $row->min_qty,
                        'price' => (float) $row->price,
                    ])
                    ->values()
                    ->all()
                )
                ->all()
            )
            ->all();
    }
}
