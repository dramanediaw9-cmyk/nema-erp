<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ProductCatalogCleanupService
{
    private const DEPENDENCIES = [
        ['table' => 'stock_movements'],
        ['table' => 'sales_quote_items'],
        ['table' => 'sales_order_items'],
        ['table' => 'sales_invoice_items'],
        ['table' => 'sales_credit_note_items'],
        ['table' => 'delivery_note_items'],
        ['table' => 'purchase_request_items'],
        ['table' => 'purchase_order_items'],
        ['table' => 'purchase_bill_items'],
        ['table' => 'goods_receipt_items'],
        ['table' => 'stock_transfer_items'],
        ['table' => 'stock_count_items'],
        ['table' => 'pos_return_items'],
        ['table' => 'price_list_items'],
        ['table' => 'products', 'column' => 'parent_product_id'],
    ];

    public function clean(int $companyId): array
    {
        $candidates = $this->candidates($companyId);
        $candidateIds = $candidates->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($candidateIds === []) {
            return [
                'detected' => 0,
                'deleted' => 0,
                'archived' => 0,
                'invalid_name' => 0,
                'invalid_price' => 0,
            ];
        }

        $usedIds = $this->usedProductIds($candidateIds);
        $archivedIds = array_values(array_intersect($candidateIds, $usedIds));
        $deletedIds = array_values(array_diff($candidateIds, $archivedIds));
        $images = $candidates
            ->whereIn('id', $deletedIds)
            ->filter(fn (Product $product): bool => filled($product->image_path))
            ->groupBy(fn (Product $product): string => $product->imageDisk());

        DB::transaction(function () use ($archivedIds, $deletedIds): void {
            foreach (array_chunk($archivedIds, 500) as $ids) {
                Product::query()->whereKey($ids)->update([
                    'is_active' => false,
                    'sale_ok' => false,
                    'purchase_ok' => false,
                    'sale_blocked' => true,
                    'sale_block_reason' => 'Archive automatiquement : nom ou prix de vente inexploitable.',
                    'purchase_blocked' => true,
                    'purchase_block_reason' => 'Archive automatiquement : nom ou prix de vente inexploitable.',
                    'updated_at' => now(),
                ]);
            }

            foreach (array_chunk($deletedIds, 500) as $ids) {
                Product::query()->whereKey($ids)->delete();
            }
        });

        $images->each(function (Collection $products, string $disk): void {
            Storage::disk($disk)->delete($products->pluck('image_path')->filter()->all());
        });

        return [
            'detected' => count($candidateIds),
            'deleted' => count($deletedIds),
            'archived' => count($archivedIds),
            'invalid_name' => $candidates->filter(
                fn (Product $product): bool => str_contains((string) $product->cleanup_reason, 'nom'),
            )->count(),
            'invalid_price' => $candidates->filter(
                fn (Product $product): bool => str_contains((string) $product->cleanup_reason, 'prix'),
            )->count(),
        ];
    }

    public function invalidReasonForValues(
        ?string $name,
        mixed $salePrice,
        ?string $parentName = null,
        ?string $variantLabel = null,
    ): ?string {
        $effectiveName = trim(collect([$parentName ?: $name, $variantLabel])->filter()->implode(' '));
        $invalidName = ! $this->isRealProductName($effectiveName);
        $invalidPrice = ! is_numeric($salePrice) || (float) $salePrice <= 0;

        return match (true) {
            $invalidName && $invalidPrice => 'nom_et_prix_invalides',
            $invalidName => 'nom_invalide',
            $invalidPrice => 'prix_invalide',
            default => null,
        };
    }

    private function candidates(int $companyId): Collection
    {
        return Product::query()
            ->withoutGlobalScopes()
            ->with(['parent' => fn ($query) => $query->withoutGlobalScopes()->select(['id', 'name'])])
            ->where('company_id', $companyId)
            ->get([
                'id',
                'company_id',
                'parent_product_id',
                'name',
                'variant_label',
                'sale_price',
                'image_path',
                'image_disk',
            ])
            ->map(function (Product $product): Product {
                $product->cleanup_reason = $this->invalidReasonForValues(
                    $product->name,
                    $product->sale_price,
                    $product->parent?->name,
                    $product->variant_label,
                );

                return $product;
            })
            ->filter(fn (Product $product): bool => $product->cleanup_reason !== null)
            ->values();
    }

    private function usedProductIds(array $candidateIds): array
    {
        $used = [];

        foreach (self::DEPENDENCIES as $dependency) {
            $table = $dependency['table'];
            $column = $dependency['column'] ?? 'product_id';

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            foreach (array_chunk($candidateIds, 500) as $ids) {
                DB::table($table)
                    ->whereIn($column, $ids)
                    ->pluck($column)
                    ->each(function ($id) use (&$used): void {
                        $used[(int) $id] = true;
                    });
            }
        }

        return array_keys($used);
    }

    private function isRealProductName(string $name): bool
    {
        $name = trim(html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $placeholder = mb_strtolower(preg_replace('/[\s._\-\/]+/u', '', $name) ?? '');

        if ($name === '' || in_array($placeholder, [
            '', 'na', 'n/a', 'none', 'null', 'sansnom', 'inconnu', 'unknown',
            'product', 'produit', 'article', 'test',
        ], true)) {
            return false;
        }

        // Les libelles de Fily's sont en alphabet latin. Deux lettres ASCII
        // consecutives suffisent pour conserver les noms courts (ex. 7Up).
        return preg_match('/[A-Za-z]{2,}/', $name) === 1;
    }
}

