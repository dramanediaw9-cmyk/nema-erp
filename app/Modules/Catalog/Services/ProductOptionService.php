<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductOptionService
{
    /**
     * Retourne un petit jeu initial et conserve toujours les produits deja
     * selectionnes. Les autres produits sont retrouves par recherche AJAX.
     */
    public function initial(int $companyId, string $mode = 'active', array $selectedIds = [], int $limit = 40): Collection
    {
        $selectedIds = collect($selectedIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $query = $this->query($companyId, $mode)->with('parent');
        $initial = (clone $query)
            ->orderBy('name')
            ->orderBy('id')
            ->limit(max(10, min($limit, 75)))
            ->get();

        if ($selectedIds === []) {
            return $initial;
        }

        return (clone $query)
            ->whereIn('id', $selectedIds)
            ->get()
            ->merge($initial)
            ->unique('id')
            ->sortBy(fn (Product $product): string => mb_strtolower($product->display_name))
            ->values();
    }

    public function search(int $companyId, string $mode, ?string $search, int $limit = 40): array
    {
        $search = trim((string) $search);
        $limit = max(10, min($limit, 50));
        $query = $this->query($companyId, $mode)->with('parent');

        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function (Builder $nested) use ($like): void {
                $nested->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhere('variant_label', 'like', $like);
            });
        }

        $products = $query
            ->orderBy('name')
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $products->count() > $limit;

        return [
            'results' => $products->take($limit)->map(fn (Product $product): array => [
                'id' => $product->id,
                'text' => trim($product->sku.' - '.$product->display_name),
                'name' => $product->display_name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'unit' => $product->unit,
                'price' => (float) $product->sale_price,
                'cost' => (float) $product->purchase_price,
                'sale_description' => $product->sales_description ?: ($product->description ?: $product->display_name),
                'purchase_description' => $product->purchase_description ?: ($product->description ?: $product->display_name),
                'sale_unit_summary' => $product->salesUnitSummary() ?: $product->unit,
                'purchase_unit_summary' => $product->purchaseUnitSummary() ?: $product->unit,
            ])->values(),
            'has_more' => $hasMore,
        ];
    }

    private function query(int $companyId, string $mode): Builder
    {
        $query = Product::query()->where('company_id', $companyId);

        return match ($mode) {
            'saleable' => $query->saleable(),
            'purchasable' => $query->purchasable(),
            'stockable' => $query->active()->where('type', 'stockable'),
            'parents' => $query->active()->topLevel(),
            default => $query->active(),
        };
    }
}
