<?php

namespace App\Modules\Core\Imports\Odoo\Services;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeValue;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Catalog\Models\ProductSupplier;
use App\Modules\Core\Company\Models\TaxRule;
use App\Modules\Core\Imports\Odoo\Contracts\OdooClient;
use App\Modules\Core\Imports\Odoo\Models\OdooConnection;
use App\Modules\Core\Imports\Odoo\Models\OdooProductImportError;
use App\Modules\Core\Imports\Odoo\Models\OdooProductImportRun;
use App\Modules\Core\Imports\Odoo\Models\OdooProductMapping;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Partners\Models\Partner;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class OdooProductImportService
{
    private const TEMPLATE_FIELDS = [
        'id', 'name', 'default_code', 'barcode', 'categ_id', 'list_price', 'standard_price',
        'taxes_id', 'supplier_taxes_id', 'uom_id', 'uom_po_id', 'image_1920', 'description',
        'description_sale', 'description_purchase', 'active', 'sale_ok', 'purchase_ok', 'type',
        'detailed_type', 'product_variant_ids', 'attribute_line_ids', 'write_date', 'tracking', 'invoice_policy',
    ];

    private const VARIANT_FIELDS = [
        'id', 'product_tmpl_id', 'name', 'display_name', 'default_code', 'barcode', 'lst_price',
        'list_price', 'standard_price', 'image_1920', 'image_variant_1920', 'active', 'sale_ok', 'purchase_ok', 'qty_available',
        'product_template_attribute_value_ids', 'product_template_variant_value_ids', 'write_date',
    ];

    public function __construct(
        private readonly OdooClientFactory $clients,
        private readonly StockService $stockService,
    ) {}

    public function createRun(OdooConnection $connection, string $mode, ?User $user = null): OdooProductImportRun
    {
        return OdooProductImportRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $connection->tenant_id,
            'company_id' => $connection->company_id,
            'branch_id' => $connection->branch_id,
            'odoo_connection_id' => $connection->id,
            'requested_by' => $user?->id,
            'mode' => $mode,
            'status' => 'queued',
            'phase' => 'templates',
            'cursor_id' => 0,
            'incremental_since' => $mode === 'incremental' ? $connection->last_sync_at : null,
            'sync_cutoff_at' => now()->utc(),
            'options' => [
                'batch_size' => $connection->batch_size,
                'import_images' => $connection->import_images,
                'import_stock' => $connection->import_stock,
            ],
        ]);
    }

    public function processNextBatch(OdooProductImportRun $run): bool
    {
        $run->refresh();
        if (in_array($run->status, ['completed', 'cancelled'], true)) {
            return false;
        }

        $connection = $run->connection()->firstOrFail();
        $client = $this->clients->make($connection);

        if (in_array($run->status, ['queued', 'failed'], true)) {
            $this->initialize($run, $client);
            $run->refresh();
        }

        $model = $run->phase === 'templates' ? 'product.template' : 'product.product';
        $fields = $run->phase === 'templates' ? self::TEMPLATE_FIELDS : self::VARIANT_FIELDS;
        $domain = $this->domain($run, true);
        $limit = max(10, min((int) ($run->options['batch_size'] ?? $connection->batch_size), (int) config('odoo.max_batch_size', 1000)));
        $records = $client->searchRead($model, $domain, $fields, $limit, 0, 'id asc');

        if ($records === []) {
            if ($run->phase === 'templates') {
                $run->forceFill([
                    'phase' => 'variants',
                    'cursor_id' => 0,
                    'heartbeat_at' => now(),
                ])->save();

                return true;
            }

            $this->complete($run, $connection);

            return false;
        }

        $context = $run->phase === 'templates'
            ? $this->templateContext($client, $run, $records)
            : $this->variantContext($client, $run, $connection, $records);

        foreach ($records as $record) {
            if ($run->fresh()->status === 'cancelled') {
                return false;
            }

            try {
                $result = $run->phase === 'templates'
                    ? $this->syncTemplate($run, $connection, $record, $context)
                    : $this->syncVariant($run, $connection, $record, $context);
                $run->increment($result.'_count');
            } catch (Throwable $exception) {
                $run->increment('failed_count');
                $this->logError($run, $model, $record['id'] ?? null, $exception->getMessage(), [
                    'name' => $record['name'] ?? $record['display_name'] ?? null,
                    'sku' => $record['default_code'] ?? null,
                    'barcode' => $record['barcode'] ?? null,
                ]);
            } finally {
                $run->increment('processed_count');
            }
        }

        $lastId = (int) max(array_map(fn (array $record): int => (int) ($record['id'] ?? 0), $records));
        $run->forceFill([
            'cursor_id' => $lastId,
            'batch_count' => $run->batch_count + 1,
            'heartbeat_at' => now(),
            'last_error' => null,
        ])->save();

        return true;
    }

    private function initialize(OdooProductImportRun $run, OdooClient $client): void
    {
        $client->authenticate();
        $templateCount = $client->searchCount('product.template', $this->domain($run));
        $variantCount = $client->searchCount('product.product', $this->domain($run));

        $run->forceFill([
            'status' => 'running',
            'source_total' => $templateCount + $variantCount,
            'started_at' => $run->started_at ?: now(),
            'heartbeat_at' => now(),
            'finished_at' => null,
            'last_error' => null,
        ])->save();
    }

    private function complete(OdooProductImportRun $run, OdooConnection $connection): void
    {
        DB::transaction(function () use ($run, $connection): void {
            $run->forceFill([
                'status' => 'completed',
                'phase' => 'completed',
                'cursor_id' => 0,
                'heartbeat_at' => now(),
                'finished_at' => now(),
                'last_error' => null,
            ])->save();

            $connection->forceFill([
                'last_sync_at' => $run->sync_cutoff_at,
                'health_status' => 'healthy',
                'last_error' => null,
            ])->save();
        });
    }

    private function domain(OdooProductImportRun $run, bool $withCursor = false): array
    {
        $domain = [];
        if ($run->incremental_since) {
            $domain[] = ['write_date', '>=', $run->incremental_since->utc()->format('Y-m-d H:i:s')];
        }
        if ($run->sync_cutoff_at) {
            $domain[] = ['write_date', '<=', $run->sync_cutoff_at->utc()->format('Y-m-d H:i:s')];
        }
        if ($withCursor && $run->cursor_id > 0) {
            $domain[] = ['id', '>', $run->cursor_id];
        }

        return $domain;
    }

    private function syncTemplate(OdooProductImportRun $run, OdooConnection $connection, array $record, array $context): string
    {
        $odooId = (int) $record['id'];
        $mapping = $context['mappings'][$odooId] ?? null;
        $variantIds = $this->ids($record['product_variant_ids'] ?? []);
        $singleVariant = count($variantIds) <= 1;
        $fallbackSku = $singleVariant
            ? 'ODOO-P-'.$connection->id.'-'.($variantIds[0] ?? $odooId)
            : 'ODOO-T-'.$connection->id.'-'.$odooId;
        $requestedSku = $singleVariant
            ? $this->sku($record['default_code'] ?? null, $fallbackSku)
            : $fallbackSku;
        $barcode = $this->nullableString($record['barcode'] ?? null);
        $mappedProduct = $mapping?->product;
        $skuProduct = $this->findBySku($run->company_id, $requestedSku);
        $barcodeProduct = $this->findByBarcode(
            $run->company_id,
            $barcode,
            null,
            $connection->id,
            'product.template',
            $odooId,
        );
        $product = $barcodeProduct ?: $skuProduct;

        if (! $product && $mappedProduct && ! $this->hasConflictingMapping($connection->id, 'product.template', $odooId, $mappedProduct->id)) {
            $product = $mappedProduct;
        }

        if (! $product && $singleVariant) {
            $product = $this->findBySkuOrBarcode(
                $run->company_id,
                $requestedSku,
                $barcode,
                null,
                $connection->id,
                'product.template',
                $odooId,
            );
        }

        $created = ! $product;
        $product ??= new Product;
        $sku = $this->availableSku($run->company_id, $requestedSku, $fallbackSku, $product);
        $barcode = $this->availableBarcode($run->company_id, $barcode, $product);
        $mappingNeedsRepair = $mapping && $product->exists && (int) $mapping->product_id !== (int) $product->id;
        $taxes = $context['taxes'];
        $saleTaxes = collect($this->ids($record['taxes_id'] ?? []))->map(fn (int $id): ?array => $taxes[$id] ?? null)->filter()->values()->all();
        $purchaseTaxes = collect($this->ids($record['supplier_taxes_id'] ?? []))->map(fn (int $id): ?array => $taxes[$id] ?? null)->filter()->values()->all();
        $related = [
            'attributes' => $context['attributes_by_template'][$odooId] ?? [],
            'suppliers' => $context['suppliers_by_template'][$odooId] ?? [],
            'sale_taxes' => $saleTaxes,
            'purchase_taxes' => $purchaseTaxes,
        ];
        $hash = $this->sourceHash($record, $related);

        if ($mapping && ! $mappingNeedsRepair && $mapping->source_hash === $hash) {
            $mapping->forceFill(['last_synced_at' => now()])->save();

            return 'skipped';
        }

        DB::transaction(function () use ($product, $run, $connection, $record, $related, $odooId, $hash, $sku, $barcode, $singleVariant): void {
            $product->fill([
                'tenant_id' => $run->tenant_id,
                'company_id' => $run->company_id,
                'category_id' => $this->categoryId($run, $record['categ_id'] ?? null),
                'parent_product_id' => null,
                'sku' => $sku,
                'barcode' => $barcode,
                'name' => $this->string($record['name'] ?? null, 'Produit Odoo '.$odooId),
                'unit' => $this->relationName($record['uom_id'] ?? null) ?: 'unite',
                'sales_unit_name' => $this->relationName($record['uom_id'] ?? null),
                'sales_unit_ratio' => 1,
                'purchase_unit_name' => $this->relationName($record['uom_po_id'] ?? null),
                'purchase_unit_ratio' => 1,
                'type' => $this->productType($record),
                'sale_ok' => $singleVariant && (bool) ($record['sale_ok'] ?? true),
                'purchase_ok' => $singleVariant && (bool) ($record['purchase_ok'] ?? true),
                'invoice_policy' => $this->nullableString($record['invoice_policy'] ?? null),
                'tracking_type' => $this->tracking($record['tracking'] ?? null),
                'sale_price' => (float) ($record['list_price'] ?? 0),
                'purchase_price' => (float) ($record['standard_price'] ?? 0),
                'sale_tax_rule_id' => $this->taxRuleId($run, $related['sale_taxes'][0] ?? null, 'sale'),
                'purchase_tax_rule_id' => $this->taxRuleId($run, $related['purchase_taxes'][0] ?? null, 'purchase'),
                'description' => $this->nullableString($record['description'] ?? null),
                'sales_description' => $this->nullableString($record['description_sale'] ?? null),
                'purchase_description' => $this->nullableString($record['description_purchase'] ?? null),
                'internal_notes' => $singleVariant ? null : 'Famille Odoo product.template #'.$odooId,
                'is_active' => (bool) ($record['active'] ?? true),
                'is_variant' => false,
                'variant_label' => null,
                'variant_signature' => null,
            ]);
            $this->storeImage($product, $record['image_1920'] ?? null, $connection, 'template', $odooId);
            $product->save();

            $this->syncTemplateAttributes($run, $related['attributes']);
            $this->syncSuppliers($run, $product, $related['suppliers'], $connection);
            foreach (array_slice($related['sale_taxes'], 1) as $tax) {
                $this->taxRuleId($run, $tax, 'sale');
            }
            foreach (array_slice($related['purchase_taxes'], 1) as $tax) {
                $this->taxRuleId($run, $tax, 'purchase');
            }

            OdooProductMapping::query()->updateOrCreate([
                'odoo_connection_id' => $connection->id,
                'odoo_model' => 'product.template',
                'odoo_id' => $odooId,
            ], [
                'tenant_id' => $run->tenant_id,
                'company_id' => $run->company_id,
                'product_id' => $product->id,
                'odoo_template_id' => $odooId,
                'source_hash' => $hash,
                'odoo_write_date' => $this->date($record['write_date'] ?? null),
                'last_synced_at' => now(),
            ]);
        });

        return $created ? 'created' : 'updated';
    }

    private function syncVariant(OdooProductImportRun $run, OdooConnection $connection, array $record, array $context): string
    {
        $odooId = (int) $record['id'];
        $templateId = $this->relationId($record['product_tmpl_id'] ?? null);
        $parentMapping = $context['template_mappings'][$templateId] ?? null;
        if (! $parentMapping?->product) {
            throw new \RuntimeException('Le produit parent Odoo #'.$templateId.' n a pas encore ete importe.');
        }

        $mapping = $context['mappings'][$odooId] ?? null;
        $parent = $parentMapping->product;
        $singleVariant = ! str_starts_with((string) $parent->sku, 'ODOO-T-'.$connection->id.'-');
        $fallbackSku = 'ODOO-P-'.$connection->id.'-'.$odooId;
        $requestedSku = $this->sku($record['default_code'] ?? null, $fallbackSku);
        $barcode = $this->nullableString($record['barcode'] ?? null);
        $mappedProduct = $mapping?->product;
        $excludeId = $singleVariant ? null : $parent->id;
        if ($singleVariant) {
            $product = $parent;
        } else {
            $skuProduct = $this->findBySku($run->company_id, $requestedSku, $excludeId);
            $barcodeProduct = $this->findByBarcode(
                $run->company_id,
                $barcode,
                $excludeId,
                $connection->id,
                'product.product',
                $odooId,
            );
            $product = $barcodeProduct ?: $skuProduct;
            if (! $product && $mappedProduct && ! $this->hasConflictingMapping($connection->id, 'product.product', $odooId, $mappedProduct->id)) {
                $product = $mappedProduct;
            }
            $product ??= $this->findBySkuOrBarcode(
                $run->company_id,
                $requestedSku,
                $barcode,
                $parent->id,
                $connection->id,
                'product.product',
                $odooId,
            );
        }

        $created = ! $product;
        $product ??= new Product;
        $sku = $this->availableSku($run->company_id, $requestedSku, $fallbackSku, $product);
        $barcode = $this->availableBarcode($run->company_id, $barcode, $product);
        $mappingNeedsRepair = $mapping && $product->exists && (int) $mapping->product_id !== (int) $product->id;
        $variantValues = $context['variant_values_by_variant'][$odooId] ?? [];
        $suppliers = $context['suppliers_by_variant'][$odooId] ?? $context['suppliers_by_template'][$templateId] ?? [];
        $stockQuantity = $context['stock_by_variant'][$odooId] ?? ($record['qty_available'] ?? null);
        $hash = $this->sourceHash($record, ['values' => $variantValues, 'suppliers' => $suppliers, 'stock_quantity' => $stockQuantity]);

        if ($mapping && ! $mappingNeedsRepair && $mapping->source_hash === $hash) {
            $mapping->forceFill(['last_synced_at' => now()])->save();

            return 'skipped';
        }

        DB::transaction(function () use ($product, $run, $connection, $record, $odooId, $templateId, $parent, $singleVariant, $sku, $barcode, $variantValues, $suppliers, $stockQuantity, $hash): void {
            $valueIds = $this->syncVariantValues($run, $variantValues, $connection);
            $variantLabel = collect($variantValues)
                ->map(fn (array $value): string => trim(($value['attribute_name'] ?? '').': '.($value['value_name'] ?? '')))
                ->filter()
                ->implode(' · ');

            $product->fill([
                'tenant_id' => $run->tenant_id,
                'company_id' => $run->company_id,
                'category_id' => $parent->category_id,
                'parent_product_id' => $singleVariant ? null : $parent->id,
                'sku' => $sku,
                'barcode' => $barcode,
                'name' => $singleVariant ? $parent->name : $this->string($record['name'] ?? null, $parent->name),
                'unit' => $parent->unit,
                'sales_unit_name' => $parent->sales_unit_name,
                'sales_unit_ratio' => $parent->sales_unit_ratio ?: 1,
                'purchase_unit_name' => $parent->purchase_unit_name,
                'purchase_unit_ratio' => $parent->purchase_unit_ratio ?: 1,
                'type' => $parent->type,
                'sale_ok' => (bool) ($record['sale_ok'] ?? ($singleVariant ? $parent->sale_ok : true)),
                'purchase_ok' => (bool) ($record['purchase_ok'] ?? ($singleVariant ? $parent->purchase_ok : true)),
                'invoice_policy' => $parent->invoice_policy,
                'tracking_type' => $parent->tracking_type,
                'sale_price' => (float) ($record['lst_price'] ?? $record['list_price'] ?? $parent->sale_price),
                'purchase_price' => (float) ($record['standard_price'] ?? $parent->purchase_price),
                'sale_tax_rule_id' => $parent->sale_tax_rule_id,
                'purchase_tax_rule_id' => $parent->purchase_tax_rule_id,
                'description' => $parent->description,
                'sales_description' => $parent->sales_description,
                'purchase_description' => $parent->purchase_description,
                'is_active' => (bool) ($record['active'] ?? $parent->is_active),
                'is_variant' => ! $singleVariant,
                'variant_label' => $singleVariant ? null : ($variantLabel ?: $this->variantName($record, $parent->name)),
                'variant_signature' => $singleVariant
                    ? null
                    : (collect($valueIds)->sort()->implode('-') ?: 'odoo-'.$odooId),
            ]);
            $image = $record['image_variant_1920'] ?? $record['image_1920'] ?? null;
            $this->storeImage($product, $image, $connection, 'variant', $odooId);
            $product->save();
            $product->attributeValues()->sync($singleVariant ? [] : $valueIds);
            $this->syncSuppliers($run, $product, $suppliers, $connection);

            OdooProductMapping::query()->updateOrCreate([
                'odoo_connection_id' => $connection->id,
                'odoo_model' => 'product.product',
                'odoo_id' => $odooId,
            ], [
                'tenant_id' => $run->tenant_id,
                'company_id' => $run->company_id,
                'product_id' => $product->id,
                'odoo_template_id' => $templateId,
                'source_hash' => $hash,
                'odoo_write_date' => $this->date($record['write_date'] ?? null),
                'last_synced_at' => now(),
            ]);

            if ($connection->import_stock && $stockQuantity !== null) {
                $this->stockService->synchronizeExternalBalance(
                    product: $product,
                    companyId: $run->company_id,
                    branchId: $run->branch_id,
                    targetQuantity: (float) $stockQuantity,
                    unitCost: (float) $product->purchase_price,
                    source: 'odoo_product_import',
                    sourceId: $run->id,
                    user: $run->requester,
                );
            }
        });

        return $created ? 'created' : 'updated';
    }

    private function templateContext(OdooClient $client, OdooProductImportRun $run, array $records): array
    {
        $templateIds = array_map(fn (array $record): int => (int) $record['id'], $records);
        $taxIds = collect($records)->flatMap(fn (array $record): array => [
            ...$this->ids($record['taxes_id'] ?? []),
            ...$this->ids($record['supplier_taxes_id'] ?? []),
        ])->unique()->values()->all();
        $taxes = collect($this->optionalRead($client, $run, 'account.tax', $taxIds, ['id', 'name', 'amount', 'type_tax_use', 'active']))
            ->keyBy(fn (array $tax): int => (int) $tax['id'])->all();
        $attributes = $this->templateAttributes($client, $run, $records);
        $suppliers = $this->supplierContext($client, $run, $templateIds);
        $mappings = OdooProductMapping::query()
            ->with('product')
            ->where('odoo_connection_id', $run->odoo_connection_id)
            ->where('odoo_model', 'product.template')
            ->whereIn('odoo_id', $templateIds)
            ->get()
            ->keyBy('odoo_id')
            ->all();

        return compact('taxes', 'mappings') + $attributes + $suppliers;
    }

    private function variantContext(OdooClient $client, OdooProductImportRun $run, OdooConnection $connection, array $records): array
    {
        $variantIds = array_map(fn (array $record): int => (int) $record['id'], $records);
        $templateIds = collect($records)->map(fn (array $record): int => $this->relationId($record['product_tmpl_id'] ?? null))->filter()->unique()->values()->all();
        $mappings = OdooProductMapping::query()
            ->with('product')
            ->where('odoo_connection_id', $run->odoo_connection_id)
            ->where('odoo_model', 'product.product')
            ->whereIn('odoo_id', $variantIds)
            ->get()->keyBy('odoo_id')->all();
        $templateMappings = OdooProductMapping::query()
            ->with('product')
            ->where('odoo_connection_id', $run->odoo_connection_id)
            ->where('odoo_model', 'product.template')
            ->whereIn('odoo_id', $templateIds)
            ->get()->keyBy('odoo_id')->all();
        $variantValues = $this->variantValues($client, $run, $records);
        $suppliers = $this->supplierContext($client, $run, $templateIds, $variantIds);
        $stockByVariant = $this->stockByVariant($client, $run, $connection, $variantIds);

        return ['mappings' => $mappings, 'template_mappings' => $templateMappings, 'stock_by_variant' => $stockByVariant] + $variantValues + $suppliers;
    }

    private function stockByVariant(OdooClient $client, OdooProductImportRun $run, OdooConnection $connection, array $variantIds): array
    {
        $locationIds = collect($connection->stock_location_ids ?? [])->map(fn (mixed $id): int => (int) $id)->filter()->values()->all();
        if (! $connection->import_stock || $locationIds === [] || $variantIds === []) {
            return [];
        }

        $rows = $this->optionalSearchRead($client, $run, 'stock.quant', [
            ['product_id', 'in', $variantIds],
            ['location_id', 'child_of', $locationIds],
        ], ['id', 'product_id', 'location_id', 'quantity']);

        return collect($rows)
            ->groupBy(fn (array $row): int => $this->relationId($row['product_id'] ?? null))
            ->map(fn ($quants): float => (float) $quants->sum(fn (array $quant): float => (float) ($quant['quantity'] ?? 0)))
            ->all();
    }

    private function templateAttributes(OdooClient $client, OdooProductImportRun $run, array $records): array
    {
        $lineIds = collect($records)->flatMap(fn (array $record): array => $this->ids($record['attribute_line_ids'] ?? []))->unique()->values()->all();
        $lines = $this->optionalRead($client, $run, 'product.template.attribute.line', $lineIds, ['id', 'product_tmpl_id', 'attribute_id', 'value_ids']);
        $valueIds = collect($lines)->flatMap(fn (array $line): array => $this->ids($line['value_ids'] ?? []))->unique()->values()->all();
        $values = collect($this->optionalRead($client, $run, 'product.attribute.value', $valueIds, ['id', 'name', 'attribute_id', 'active']))
            ->keyBy(fn (array $value): int => (int) $value['id']);
        $byTemplate = [];
        foreach ($lines as $line) {
            $templateId = $this->relationId($line['product_tmpl_id'] ?? null);
            $attributeId = $this->relationId($line['attribute_id'] ?? null);
            $entry = [
                'attribute_id' => $attributeId,
                'attribute_name' => $this->relationName($line['attribute_id'] ?? null) ?: 'Attribut Odoo '.$attributeId,
                'values' => collect($this->ids($line['value_ids'] ?? []))->map(fn (int $id): ?array => $values->has($id) ? [
                    'value_id' => $id,
                    'value_name' => $this->string($values[$id]['name'] ?? null, 'Valeur '.$id),
                    'active' => (bool) ($values[$id]['active'] ?? true),
                ] : null)->filter()->values()->all(),
            ];
            $byTemplate[$templateId][] = $entry;
        }

        return ['attributes_by_template' => $byTemplate];
    }

    private function variantValues(OdooClient $client, OdooProductImportRun $run, array $records): array
    {
        $byVariantIds = [];
        $allIds = [];
        foreach ($records as $record) {
            $ids = $this->ids($record['product_template_variant_value_ids'] ?? $record['product_template_attribute_value_ids'] ?? []);
            $byVariantIds[(int) $record['id']] = $ids;
            $allIds = [...$allIds, ...$ids];
        }
        $rows = collect($this->optionalRead($client, $run, 'product.template.attribute.value', array_values(array_unique($allIds)), [
            'id', 'attribute_id', 'product_attribute_value_id', 'name', 'product_tmpl_id',
        ]))->keyBy(fn (array $row): int => (int) $row['id']);
        $byVariant = [];
        foreach ($byVariantIds as $variantId => $ids) {
            $byVariant[$variantId] = collect($ids)->map(function (int $id) use ($rows): ?array {
                $row = $rows[$id] ?? null;
                if (! $row) {
                    return null;
                }
                $valueId = $this->relationId($row['product_attribute_value_id'] ?? null) ?: $id;

                return [
                    'attribute_id' => $this->relationId($row['attribute_id'] ?? null),
                    'attribute_name' => $this->relationName($row['attribute_id'] ?? null) ?: 'Attribut Odoo',
                    'value_id' => $valueId,
                    'value_name' => $this->relationName($row['product_attribute_value_id'] ?? null) ?: $this->string($row['name'] ?? null, 'Valeur '.$valueId),
                ];
            })->filter()->values()->all();
        }

        return ['variant_values_by_variant' => $byVariant];
    }

    private function supplierContext(OdooClient $client, OdooProductImportRun $run, array $templateIds, array $variantIds = []): array
    {
        $infos = $this->optionalSearchRead($client, $run, 'product.supplierinfo', [['product_tmpl_id', 'in', $templateIds]], [
            'id', 'partner_id', 'product_tmpl_id', 'product_id', 'product_code', 'product_name', 'min_qty', 'price', 'delay', 'sequence',
        ]);
        $partnerIds = collect($infos)->map(fn (array $info): int => $this->relationId($info['partner_id'] ?? null))->filter()->unique()->values()->all();
        $partners = collect($this->optionalRead($client, $run, 'res.partner', $partnerIds, ['id', 'name', 'phone', 'mobile', 'email', 'city', 'vat', 'street', 'active']))
            ->keyBy(fn (array $partner): int => (int) $partner['id'])->all();
        $byTemplate = [];
        $byVariant = [];
        foreach ($infos as $info) {
            $partnerId = $this->relationId($info['partner_id'] ?? null);
            $templateId = $this->relationId($info['product_tmpl_id'] ?? null);
            $variantId = $this->relationId($info['product_id'] ?? null);
            $entry = $info + ['partner' => $partners[$partnerId] ?? ['id' => $partnerId, 'name' => $this->relationName($info['partner_id'] ?? null)]];
            $byTemplate[$templateId][] = $entry;
            if ($variantId) {
                $byVariant[$variantId][] = $entry;
            }
        }

        return ['suppliers_by_template' => $byTemplate, 'suppliers_by_variant' => $byVariant];
    }

    private function optionalRead(OdooClient $client, OdooProductImportRun $run, string $model, array $ids, array $fields): array
    {
        try {
            return $client->read($model, $ids, $fields);
        } catch (Throwable $exception) {
            $this->logError($run, $model, null, 'Donnees associees ignorees: '.$exception->getMessage(), ['ids_count' => count($ids)]);

            return [];
        }
    }

    private function optionalSearchRead(OdooClient $client, OdooProductImportRun $run, string $model, array $domain, array $fields): array
    {
        try {
            return $client->searchRead($model, $domain, $fields, 0, 0, 'id asc');
        } catch (Throwable $exception) {
            $this->logError($run, $model, null, 'Donnees associees ignorees: '.$exception->getMessage());

            return [];
        }
    }

    private function syncTemplateAttributes(OdooProductImportRun $run, array $attributes): void
    {
        foreach ($attributes as $definition) {
            $attribute = $this->attribute($run, (int) $definition['attribute_id'], (string) $definition['attribute_name']);
            foreach ($definition['values'] as $value) {
                $this->attributeValue(
                    $run,
                    $attribute,
                    (int) $value['value_id'],
                    (string) $value['value_name'],
                    (bool) ($value['active'] ?? true),
                );
            }
        }
    }

    private function syncVariantValues(OdooProductImportRun $run, array $values, OdooConnection $connection): array
    {
        return collect($values)->map(function (array $definition) use ($run, $connection): int {
            $attribute = $this->attribute($run, (int) $definition['attribute_id'], (string) $definition['attribute_name']);
            $value = $this->attributeValue(
                $run,
                $attribute,
                (int) $definition['value_id'],
                (string) $definition['value_name'],
                true,
                $connection->id,
            );

            return $value->id;
        })->unique()->values()->all();
    }

    private function attributeValue(
        OdooProductImportRun $run,
        ProductAttribute $attribute,
        int $odooValueId,
        string $name,
        bool $isActive,
        ?int $connectionId = null,
    ): ProductAttributeValue {
        $code = 'OD'.($connectionId ?? $run->odoo_connection_id).'-V'.$odooValueId;
        $byCode = ProductAttributeValue::query()
            ->where('product_attribute_id', $attribute->id)
            ->where('code', $code)
            ->first();
        $byValue = ProductAttributeValue::query()
            ->where('product_attribute_id', $attribute->id)
            ->where('value', $name)
            ->first();

        if ($byCode && $byValue && ! $byCode->is($byValue)) {
            $links = DB::table('product_variant_attribute_value')
                ->where('product_attribute_value_id', $byCode->id)
                ->get(['product_id', 'created_at', 'updated_at']);
            foreach ($links as $link) {
                DB::table('product_variant_attribute_value')->insertOrIgnore([
                    'product_id' => $link->product_id,
                    'product_attribute_value_id' => $byValue->id,
                    'created_at' => $link->created_at,
                    'updated_at' => $link->updated_at,
                ]);
            }
            DB::table('product_variant_attribute_value')
                ->where('product_attribute_value_id', $byCode->id)
                ->delete();
            $byCode->delete();
        }

        $value = $byValue ?: $byCode ?: new ProductAttributeValue;
        $value->forceFill([
            'tenant_id' => $run->tenant_id,
            'company_id' => $run->company_id,
            'product_attribute_id' => $attribute->id,
            'value' => $name,
            'code' => $code,
            'is_active' => $isActive,
        ])->save();

        return $value;
    }

    private function attribute(OdooProductImportRun $run, int $odooId, string $name): ProductAttribute
    {
        return ProductAttribute::query()->updateOrCreate([
            'company_id' => $run->company_id,
            'code' => 'OD'.$run->odoo_connection_id.'-A'.$odooId,
        ], [
            'tenant_id' => $run->tenant_id,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function syncSuppliers(OdooProductImportRun $run, Product $product, array $infos, OdooConnection $connection): void
    {
        foreach ($infos as $index => $info) {
            $source = $info['partner'] ?? [];
            $odooId = (int) ($source['id'] ?? $this->relationId($info['partner_id'] ?? null));
            if ($odooId <= 0) {
                continue;
            }
            $supplier = Partner::query()->updateOrCreate([
                'company_id' => $run->company_id,
                'code' => 'ODOO-'.$connection->id.'-'.$odooId,
            ], [
                'tenant_id' => $run->tenant_id,
                'type' => 'supplier',
                'name' => $this->string($source['name'] ?? null, 'Fournisseur Odoo '.$odooId),
                'phone' => $this->nullableString($source['phone'] ?? $source['mobile'] ?? null),
                'email' => $this->nullableString($source['email'] ?? null),
                'city' => $this->nullableString($source['city'] ?? null),
                'nif' => $this->nullableString($source['vat'] ?? null),
                'address' => $this->nullableString($source['street'] ?? null),
                'notes' => 'Importe depuis Odoo res.partner #'.$odooId,
                'is_active' => (bool) ($source['active'] ?? true),
            ]);
            ProductSupplier::query()->updateOrCreate([
                'product_id' => $product->id,
                'supplier_id' => $supplier->id,
            ], [
                'tenant_id' => $run->tenant_id,
                'company_id' => $run->company_id,
                'supplier_product_code' => $this->nullableString($info['product_code'] ?? null),
                'supplier_product_name' => $this->nullableString($info['product_name'] ?? null),
                'min_qty' => (float) ($info['min_qty'] ?? 1),
                'unit_cost' => (float) ($info['price'] ?? $product->purchase_price),
                'lead_time_days' => (int) ($info['delay'] ?? 0),
                'is_preferred' => $index === 0,
            ]);
        }
    }

    private function categoryId(OdooProductImportRun $run, mixed $category): ?int
    {
        $name = $this->relationName($category);
        if (! $name) {
            return null;
        }

        return ProductCategory::query()->updateOrCreate([
            'company_id' => $run->company_id,
            'name' => $name,
        ], [
            'tenant_id' => $run->tenant_id,
            'description' => 'Categorie synchronisee depuis Odoo.',
            'is_active' => true,
        ])->id;
    }

    private function taxRuleId(OdooProductImportRun $run, ?array $tax, string $scope): ?int
    {
        if (! $tax || empty($tax['id'])) {
            return null;
        }

        return TaxRule::query()->updateOrCreate([
            'company_id' => $run->company_id,
            'code' => 'OD'.$run->odoo_connection_id.'-T'.$tax['id'],
        ], [
            'tenant_id' => $run->tenant_id,
            'name' => $this->string($tax['name'] ?? null, 'Taxe Odoo '.$tax['id']),
            'scope' => $scope,
            'tax_kind' => 'vat',
            'rate' => (float) ($tax['amount'] ?? 0),
            'is_active' => (bool) ($tax['active'] ?? true),
        ])->id;
    }

    private function findBySku(int $companyId, string $sku, ?int $excludeId = null): ?Product
    {
        return Product::query()
            ->where('company_id', $companyId)
            ->when($excludeId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->where('sku', $sku)
            ->first();
    }

    private function findBySkuOrBarcode(
        int $companyId,
        string $sku,
        ?string $barcode,
        ?int $excludeId,
        int $connectionId,
        string $odooModel,
        int $odooId,
    ): ?Product {
        $bySku = $this->findBySku($companyId, $sku, $excludeId);
        if ($bySku || ! $barcode) {
            return $bySku;
        }

        return $this->findByBarcode($companyId, $barcode, $excludeId, $connectionId, $odooModel, $odooId);
    }

    private function findByBarcode(
        int $companyId,
        ?string $barcode,
        ?int $excludeId,
        int $connectionId,
        string $odooModel,
        int $odooId,
    ): ?Product {
        if (! $barcode) {
            return null;
        }

        return Product::query()
            ->where('company_id', $companyId)
            ->when($excludeId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->where('barcode', $barcode)
            ->whereNotExists(function ($query) use ($connectionId, $odooModel, $odooId): void {
                $query->selectRaw('1')
                    ->from('odoo_product_mappings')
                    ->whereColumn('odoo_product_mappings.product_id', 'products.id')
                    ->where('odoo_product_mappings.odoo_connection_id', $connectionId)
                    ->where('odoo_product_mappings.odoo_model', $odooModel)
                    ->where('odoo_product_mappings.odoo_id', '<>', $odooId);
            })
            ->first();
    }

    private function availableSku(int $companyId, string $requested, string $fallback, Product $product): string
    {
        $conflict = $this->findBySku($companyId, $requested);
        if (! $conflict || ($product->exists && $conflict->is($product))) {
            return $requested;
        }

        if ($product->exists && filled($product->sku)) {
            return (string) $product->sku;
        }

        $candidate = $fallback;
        $suffix = 1;
        while ($this->findBySku($companyId, $candidate)) {
            $candidate = Str::limit($fallback, 240, '').'-'.$suffix++;
        }

        return $candidate;
    }

    private function availableBarcode(int $companyId, ?string $requested, Product $product): ?string
    {
        if (! $requested) {
            return null;
        }

        $conflict = Product::query()
            ->where('company_id', $companyId)
            ->where('barcode', $requested)
            ->first();
        if (! $conflict || ($product->exists && $conflict->is($product))) {
            return $requested;
        }

        return $product->exists ? $this->nullableString($product->barcode) : null;
    }

    private function hasConflictingMapping(int $connectionId, string $odooModel, int $odooId, int $productId): bool
    {
        return OdooProductMapping::query()
            ->where('odoo_connection_id', $connectionId)
            ->where('odoo_model', $odooModel)
            ->where('product_id', $productId)
            ->where('odoo_id', '<>', $odooId)
            ->exists();
    }

    private function storeImage(Product $product, mixed $encoded, OdooConnection $connection, string $kind, int $odooId): void
    {
        if (! $connection->import_images || ! is_string($encoded) || $encoded === '') {
            return;
        }
        $binary = base64_decode($encoded, true);
        if (! is_string($binary) || $binary === '' || strlen($binary) > (int) config('odoo.max_image_bytes', 8388608)) {
            return;
        }
        $imageInfo = @getimagesizefromstring($binary);
        $extension = match ($imageInfo['mime'] ?? null) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'png',
        };
        $disk = (string) config('odoo.image_disk', 'public');
        $path = 'products/odoo/'.$connection->id.'/'.$kind.'-'.$odooId.'.'.$extension;
        Storage::disk($disk)->put($path, $binary);
        $product->image_disk = $disk;
        $product->image_path = $path;
    }

    private function sourceHash(array $record, array $related): string
    {
        foreach (['image_1920', 'image_variant_1920'] as $field) {
            if (! empty($record[$field]) && is_string($record[$field])) {
                $record[$field] = hash('sha256', $record[$field]);
            }
        }

        return hash('sha256', json_encode([$record, $related], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function logError(OdooProductImportRun $run, ?string $model, ?int $id, string $message, array $context = []): void
    {
        OdooProductImportError::query()->create([
            'odoo_product_import_run_id' => $run->id,
            'odoo_model' => $model,
            'odoo_id' => $id,
            'phase' => $run->phase,
            'message' => Str::limit($message, 4000),
            'context' => Arr::only($context, ['name', 'sku', 'barcode', 'ids_count']),
            'retryable' => false,
        ]);
    }

    private function productType(array $record): string
    {
        return in_array($record['detailed_type'] ?? $record['type'] ?? null, ['service'], true) ? 'service' : 'stockable';
    }

    private function tracking(mixed $value): string
    {
        return in_array($value, ['lot', 'serial'], true) ? $value : 'none';
    }

    private function variantName(array $record, string $parentName): ?string
    {
        $name = $this->string($record['display_name'] ?? $record['name'] ?? null, '');
        $label = trim(Str::after($name, $parentName), ' -[]()');

        return $label !== '' ? $label : null;
    }

    private function sku(mixed $value, string $fallback): string
    {
        return Str::limit($this->string($value, $fallback), 255, '');
    }

    private function ids(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)->map(fn ($id): int => is_array($id) ? (int) ($id[0] ?? 0) : (int) $id)->filter()->unique()->values()->all();
    }

    private function relationId(mixed $value): int
    {
        return is_array($value) ? (int) ($value[0] ?? 0) : (int) $value;
    }

    private function relationName(mixed $value): ?string
    {
        return is_array($value) ? $this->nullableString($value[1] ?? null) : null;
    }

    private function string(mixed $value, string $fallback): string
    {
        $value = is_string($value) || is_numeric($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) || is_numeric($value) ? trim((string) $value) : '';

        return $value !== '' ? $value : null;
    }

    private function date(mixed $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse((string) $value, 'UTC') : null;
        } catch (Throwable) {
            return null;
        }
    }
}
