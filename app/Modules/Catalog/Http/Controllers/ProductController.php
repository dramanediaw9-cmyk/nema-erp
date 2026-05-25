<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductAttribute;
use App\Modules\Catalog\Models\ProductAttributeValue;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Core\Audit\Services\ActivityFeedService;
use App\Modules\Core\Company\Models\TaxRule;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Partners\Models\Partner;
use App\Support\ActivityLogger;
use App\Support\CurrentWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ProductController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
        private readonly ActivityFeedService $activityFeedService,
    ) {}

    public function index(Request $request, CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $filters = $this->filters($request);

        return view('products.index', [
            'products' => $this->indexQuery($companyId, $filters)
                ->paginate(15)
                ->withQueryString(),
            'filters' => $filters,
            'categories' => ProductCategory::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function show(Product $product, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $product->company_id, 403);

        $currentStock = (float) (StockMovement::query()
            ->where('company_id', $product->company_id)
            ->where('product_id', $product->id)
            ->selectRaw('COALESCE(SUM(quantity_in - quantity_out), 0) as balance')
            ->value('balance') ?? 0);

        $stockByWarehouse = StockMovement::query()
            ->with(['branch', 'warehouse'])
            ->where('company_id', $product->company_id)
            ->where('product_id', $product->id)
            ->selectRaw('branch_id, warehouse_id, COALESCE(SUM(quantity_in - quantity_out), 0) as balance')
            ->groupBy('branch_id', 'warehouse_id')
            ->get()
            ->filter(fn (StockMovement $movement) => abs((float) $movement->balance) > 0.0001)
            ->sortBy(fn (StockMovement $movement) => ($movement->branch?->name ?? '').' '.($movement->warehouse?->name ?? ''))
            ->values();

        $recentMovements = StockMovement::query()
            ->with(['branch', 'warehouse'])
            ->where('company_id', $product->company_id)
            ->where('product_id', $product->id)
            ->latest('movement_date')
            ->latest('id')
            ->limit(12)
            ->get();

        $trackedLots = $product->tracking_type === 'none'
            ? collect()
            : ProductLot::query()
                ->with(['warehouse', 'goodsReceipt'])
                ->where('company_id', $product->company_id)
                ->where('product_id', $product->id)
                ->latest('received_at')
                ->latest('id')
                ->limit(20)
                ->get();

        return view('products.show', [
            'product' => $product->load([
                'category',
                'parent',
                'variants.attributeValues.attribute',
                'attributeValues.attribute',
                'saleTaxRule',
                'purchaseTaxRule',
                'supplierInfos.supplier',
            ]),
            'currentStock' => $currentStock,
            'stockByWarehouse' => $stockByWarehouse,
            'recentMovements' => $recentMovements,
            'trackedLots' => $trackedLots,
            'deletionGuard' => $this->deletionGuard($product),
            'recentActivities' => $this->activityFeedService->recentForSubjects($product->company_id, [$product]),
        ]);
    }

    public function create(CurrentWorkspace $workspace): View
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        return view('products.create', [
            'product' => new Product([
                'type' => 'stockable',
                'unit' => 'unite',
                'sales_unit_ratio' => 1,
                'purchase_unit_ratio' => 1,
                'is_active' => true,
                'sale_ok' => true,
                'purchase_ok' => true,
                'sale_blocked' => false,
                'sale_block_reason' => null,
                'purchase_blocked' => false,
                'purchase_block_reason' => null,
                'invoice_policy' => 'ordered',
                'tracking_type' => 'none',
                'auto_replenish' => false,
                'reorder_max_qty' => null,
                'reorder_multiple_qty' => null,
                'purchase_lead_time_days' => null,
            ]),
            'categories' => $this->categories($companyId),
            'taxRules' => $this->taxRules($companyId),
            'variantParents' => $this->variantParents($companyId),
            'variantAttributes' => $this->variantAttributes($companyId),
            'suppliers' => $this->suppliers($companyId),
            'selectedVariantValueIds' => collect(old('variant_value_ids', []))->filter()->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function store(Request $request, CurrentWorkspace $workspace): RedirectResponse
    {
        $companyId = $workspace->companyId();
        abort_if(! $companyId, 403);

        $data = $this->validateProduct($request, $companyId);
        $canViewProductCosts = $this->canViewProductCosts($request);
        $supplierInfos = $this->normalizeSupplierInfos($companyId, $data['supplier_infos'] ?? [], null, $canViewProductCosts);
        unset($data['supplier_infos']);
        $data['company_id'] = $companyId;
        $data['sku'] = $data['sku'] ?: $this->generateSku($companyId);
        $data['barcode'] = $data['barcode'] ?: null;
        $data['invoice_policy'] = $data['invoice_policy'] ?? 'ordered';
        $data['tracking_type'] = $data['tracking_type'] ?? 'none';
        $data['sale_ok'] = $request->boolean('sale_ok', true);
        $data['purchase_ok'] = $request->boolean('purchase_ok', true);
        $data['sale_blocked'] = $request->boolean('sale_blocked');
        $data['purchase_blocked'] = $request->boolean('purchase_blocked');
        $data['sale_block_reason'] = trim((string) ($data['sale_block_reason'] ?? '')) ?: null;
        $data['purchase_block_reason'] = trim((string) ($data['purchase_block_reason'] ?? '')) ?: null;
        $data['auto_replenish'] = $request->boolean('auto_replenish');
        $data['is_active'] = $request->boolean('is_active', true);
        $data['purchase_price'] = $canViewProductCosts ? round((float) ($data['purchase_price'] ?? 0), 2) : 0.0;
        $storedImagePath = $this->storeUploadedImage($request);
        $storedImageDisk = $storedImagePath ? $this->productImageDisk() : null;
        $data['image_path'] = $storedImagePath;
        $data['image_disk'] = $storedImageDisk;

        $variantPayload = $this->prepareVariantPayload($companyId, null, $data);
        $data['parent_product_id'] = $variantPayload['parent_product_id'];
        $data['is_variant'] = $variantPayload['is_variant'];
        $data['variant_label'] = $variantPayload['variant_label'];
        $data['variant_signature'] = $variantPayload['variant_signature'];
        unset($data['variant_value_ids']);

        try {
            $product = DB::transaction(function () use ($data, $supplierInfos, $variantPayload) {
                $product = Product::query()->create($data);
                $this->syncVariantValues($product, $variantPayload['value_ids']);
                $this->syncSupplierInfos($product, $supplierInfos);
                $this->activityLogger->log('products.create', 'Creation produit', $product, [
                    'sale_ok' => $product->sale_ok,
                    'purchase_ok' => $product->purchase_ok,
                    'sale_blocked' => $product->sale_blocked,
                    'sale_block_reason' => $product->sale_block_reason,
                    'purchase_blocked' => $product->purchase_blocked,
                    'purchase_block_reason' => $product->purchase_block_reason,
                    'invoice_policy' => $product->invoice_policy,
                    'tracking_type' => $product->tracking_type,
                    'is_variant' => $product->is_variant,
                    'parent_product_id' => $product->parent_product_id,
                    'variant_label' => $product->variant_label,
                ]);

                return $product;
            });
        } catch (Throwable $exception) {
            $this->deleteStoredImage($storedImagePath, $storedImageDisk);

            throw $exception;
        }

        return redirect()->route('products.show', $product)->with('success', 'Produit cree avec succes.');
    }

    public function edit(Product $product, CurrentWorkspace $workspace): View
    {
        abort_if($workspace->companyId() !== $product->company_id, 403);

        return view('products.edit', [
            'product' => $product->load(['attributeValues.attribute', 'supplierInfos.supplier']),
            'categories' => $this->categories($product->company_id),
            'taxRules' => $this->taxRules($product->company_id),
            'variantParents' => $this->variantParents($product->company_id, $product->id),
            'variantAttributes' => $this->variantAttributes($product->company_id),
            'suppliers' => $this->suppliers($product->company_id),
            'selectedVariantValueIds' => collect(old('variant_value_ids', $product->attributeValues->pluck('id')->all()))->filter()->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function update(Request $request, Product $product, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $product->company_id, 403);

        $data = $this->validateProduct($request, $product->company_id, $product->id);
        $canViewProductCosts = $this->canViewProductCosts($request);
        $supplierInfos = $this->normalizeSupplierInfos($product->company_id, $data['supplier_infos'] ?? [], $product, $canViewProductCosts);
        unset($data['supplier_infos']);
        $data['sku'] = $data['sku'] ?: $product->sku;
        $data['barcode'] = $data['barcode'] ?: null;
        $data['invoice_policy'] = $data['invoice_policy'] ?? ($product->invoice_policy ?: 'ordered');
        $data['tracking_type'] = $data['tracking_type'] ?? ($product->tracking_type ?: 'none');
        $data['sale_ok'] = $request->boolean('sale_ok', true);
        $data['purchase_ok'] = $request->boolean('purchase_ok', true);
        $data['sale_blocked'] = $request->boolean('sale_blocked');
        $data['purchase_blocked'] = $request->boolean('purchase_blocked');
        $data['sale_block_reason'] = trim((string) ($data['sale_block_reason'] ?? '')) ?: null;
        $data['purchase_block_reason'] = trim((string) ($data['purchase_block_reason'] ?? '')) ?: null;
        $data['auto_replenish'] = $request->boolean('auto_replenish');
        $data['is_active'] = $request->boolean('is_active', true);
        $data['purchase_price'] = $canViewProductCosts ? round((float) ($data['purchase_price'] ?? $product->purchase_price), 2) : (float) $product->purchase_price;
        $currentImagePath = $product->image_path;
        $currentImageDisk = $product->imageDisk();
        $replacementImagePath = null;
        $replacementImageDisk = null;
        $deletePreviousImageAfterCommit = false;

        $variantPayload = $this->prepareVariantPayload($product->company_id, $product, $data);
        $data['parent_product_id'] = $variantPayload['parent_product_id'];
        $data['is_variant'] = $variantPayload['is_variant'];
        $data['variant_label'] = $variantPayload['variant_label'];
        $data['variant_signature'] = $variantPayload['variant_signature'];
        unset($data['variant_value_ids']);

        if ($request->hasFile('image')) {
            $replacementImagePath = $this->storeUploadedImage($request);
            $replacementImageDisk = $replacementImagePath ? $this->productImageDisk() : null;
            $deletePreviousImageAfterCommit = (bool) $currentImagePath;
            $data['image_path'] = $replacementImagePath;
            $data['image_disk'] = $replacementImageDisk;
        } elseif ($request->boolean('remove_image')) {
            $deletePreviousImageAfterCommit = (bool) $currentImagePath;
            $data['image_path'] = null;
            $data['image_disk'] = null;
        }

        try {
            DB::transaction(function () use ($data, $product, $supplierInfos, $variantPayload) {
                $product->update($data);
                $this->syncVariantValues($product, $variantPayload['value_ids']);
                $this->syncSupplierInfos($product, $supplierInfos);
                $this->activityLogger->log('products.update', 'Mise a jour produit', $product, [
                    'sale_ok' => $product->sale_ok,
                    'purchase_ok' => $product->purchase_ok,
                    'sale_blocked' => $product->sale_blocked,
                    'sale_block_reason' => $product->sale_block_reason,
                    'purchase_blocked' => $product->purchase_blocked,
                    'purchase_block_reason' => $product->purchase_block_reason,
                    'invoice_policy' => $product->invoice_policy,
                    'tracking_type' => $product->tracking_type,
                    'is_variant' => $product->is_variant,
                    'parent_product_id' => $product->parent_product_id,
                    'variant_label' => $product->variant_label,
                ]);
            });
        } catch (Throwable $exception) {
            $this->deleteStoredImage($replacementImagePath, $replacementImageDisk);

            throw $exception;
        }

        if ($deletePreviousImageAfterCommit) {
            $this->deleteStoredImage($currentImagePath, $currentImageDisk);
        }

        return redirect()->route('products.show', $product)->with('success', 'Produit mis a jour avec succes.');
    }

    public function archive(Product $product, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $product->company_id, 403);

        if (! $product->is_active) {
            return redirect()->route('products.show', $product)->with('success', 'Produit deja archive.');
        }

        $product->update(['is_active' => false]);
        $this->activityLogger->log('products.archive', 'Archivage produit', $product, [
            'sku' => $product->sku,
            'name' => $product->name,
        ]);

        return redirect()->route('products.show', $product)->with('success', 'Produit archive avec succes.');
    }

    public function restore(Product $product, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $product->company_id, 403);

        if ($product->is_active) {
            return redirect()->route('products.show', $product)->with('success', 'Produit deja actif.');
        }

        $product->update(['is_active' => true]);
        $this->activityLogger->log('products.restore', 'Reactivation produit', $product, [
            'sku' => $product->sku,
            'name' => $product->name,
        ]);

        return redirect()->route('products.show', $product)->with('success', 'Produit reactive avec succes.');
    }

    public function destroy(Product $product, CurrentWorkspace $workspace): RedirectResponse
    {
        abort_if($workspace->companyId() !== $product->company_id, 403);

        $deletionGuard = $this->deletionGuard($product);

        if (! $deletionGuard['can_delete']) {
            $details = $deletionGuard['usage']
                ->map(fn (array $usage) => $usage['label'].' ('.$usage['count'].')')
                ->implode(', ');

            return redirect()->route('products.show', $product)->with(
                'error',
                'Suppression impossible : ce produit est deja utilise. Archive-le a la place. References detectees : '.$details.'.',
            );
        }

        $imagePath = $product->image_path;
        $imageDisk = $product->imageDisk();

        DB::transaction(function () use ($product) {
            $this->activityLogger->log('products.delete', 'Suppression produit', $product, [
                'sku' => $product->sku,
                'name' => $product->name,
            ]);
            $product->delete();
        });
        $this->deleteStoredImage($imagePath, $imageDisk);

        return redirect()->route('products.index')->with('success', 'Produit supprime avec succes.');
    }

    private function validateProduct(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        $canViewProductCosts = $this->canViewProductCosts($request);

        return $request->validate([
            'category_id' => [
                'nullable',
                Rule::exists('product_categories', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('products', 'sku')->where(fn ($query) => $query->where('company_id', $companyId))->ignore($ignoreId),
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'barcode')->where(fn ($query) => $query->where('company_id', $companyId))->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'parent_product_id' => [
                'nullable',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'variant_value_ids' => ['nullable', 'array'],
            'variant_value_ids.*' => [
                'nullable',
                Rule::exists('product_attribute_values', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('is_active', true)),
            ],
            'sales_unit_name' => ['nullable', 'string', 'max:50'],
            'sales_unit_ratio' => ['nullable', 'numeric', 'min:0.001'],
            'purchase_unit_name' => ['nullable', 'string', 'max:50'],
            'purchase_unit_ratio' => ['nullable', 'numeric', 'min:0.001'],
            'type' => ['required', Rule::in(['stockable', 'service'])],
            'sale_ok' => ['nullable', 'boolean'],
            'purchase_ok' => ['nullable', 'boolean'],
            'sale_blocked' => ['nullable', 'boolean'],
            'sale_block_reason' => ['nullable', 'string'],
            'purchase_blocked' => ['nullable', 'boolean'],
            'purchase_block_reason' => ['nullable', 'string'],
            'invoice_policy' => ['nullable', Rule::in(['ordered', 'delivered'])],
            'tracking_type' => ['nullable', Rule::in(['none', 'lot', 'serial'])],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'purchase_price' => [$canViewProductCosts ? 'required' : 'nullable', 'numeric', 'min:0'],
            'sale_tax_rule_id' => ['nullable', Rule::exists('tax_rules', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'purchase_tax_rule_id' => ['nullable', Rule::exists('tax_rules', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'auto_replenish' => ['nullable', 'boolean'],
            'reorder_max_qty' => ['nullable', 'numeric', 'gt:0'],
            'reorder_multiple_qty' => ['nullable', 'numeric', 'gt:0'],
            'purchase_lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'description' => ['nullable', 'string'],
            'sales_description' => ['nullable', 'string'],
            'purchase_description' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'supplier_infos' => ['nullable', 'array'],
            'supplier_infos.*.supplier_id' => [
                'nullable',
                Rule::exists('partners', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->whereIn('type', ['supplier', 'both'])->where('is_active', true)),
            ],
            'supplier_infos.*.supplier_product_code' => ['nullable', 'string', 'max:100'],
            'supplier_infos.*.supplier_product_name' => ['nullable', 'string', 'max:255'],
            'supplier_infos.*.min_qty' => ['nullable', 'numeric', 'gt:0'],
            'supplier_infos.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier_infos.*.lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'supplier_infos.*.is_preferred' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_image' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function canViewProductCosts(Request $request): bool
    {
        return (bool) $request->user()?->hasPermission('products.cost.view');
    }

    private function variantParents(int $companyId, ?int $ignoreId = null): Collection
    {
        return Product::query()
            ->where('company_id', $companyId)
            ->topLevel()
            ->when($ignoreId, fn (Builder $query, int $productId) => $query->whereKeyNot($productId))
            ->orderBy('name')
            ->get();
    }

    private function variantAttributes(int $companyId): Collection
    {
        return ProductAttribute::query()
            ->with(['values' => fn ($query) => $query->where('is_active', true)->orderBy('value')])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function prepareVariantPayload(int $companyId, ?Product $currentProduct, array $data): array
    {
        $parentProductId = ! empty($data['parent_product_id']) ? (int) $data['parent_product_id'] : null;
        $valueIds = collect($data['variant_value_ids'] ?? [])
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if (! $parentProductId && $valueIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'parent_product_id' => 'Choisis un produit parent avant de definir les valeurs de variante.',
            ]);
        }

        if ($currentProduct && $currentProduct->variants()->exists() && $parentProductId) {
            throw ValidationException::withMessages([
                'parent_product_id' => 'Ce produit possede deja des variantes enfants et ne peut pas devenir lui-meme une variante.',
            ]);
        }

        if (! $parentProductId) {
            return [
                'parent_product_id' => null,
                'is_variant' => false,
                'variant_label' => null,
                'variant_signature' => null,
                'value_ids' => [],
            ];
        }

        if ($currentProduct && $currentProduct->id === $parentProductId) {
            throw ValidationException::withMessages([
                'parent_product_id' => 'Un produit ne peut pas etre sa propre famille de variantes.',
            ]);
        }

        $parentProduct = Product::query()
            ->where('company_id', $companyId)
            ->topLevel()
            ->find($parentProductId);

        if (! $parentProduct) {
            throw ValidationException::withMessages([
                'parent_product_id' => 'Le produit parent selectionne est invalide.',
            ]);
        }

        if ($parentProduct->is_variant) {
            throw ValidationException::withMessages([
                'parent_product_id' => 'Une variante ne peut pas servir de produit parent.',
            ]);
        }

        if ($valueIds->isEmpty()) {
            throw ValidationException::withMessages([
                'variant_value_ids' => 'Selectionne au moins une valeur pour caracteriser cette variante.',
            ]);
        }

        $values = ProductAttributeValue::query()
            ->with('attribute')
            ->where('company_id', $companyId)
            ->whereIn('id', $valueIds)
            ->where('is_active', true)
            ->get();

        if ($values->count() !== $valueIds->count()) {
            throw ValidationException::withMessages([
                'variant_value_ids' => 'Une ou plusieurs valeurs de variante sont invalides ou inactives.',
            ]);
        }

        $attributeIds = $values
            ->pluck('product_attribute_id')
            ->filter()
            ->map(fn ($id) => (int) $id);

        if ($attributeIds->count() !== $attributeIds->unique()->count()) {
            throw ValidationException::withMessages([
                'variant_value_ids' => 'Choisis une seule valeur par attribut sur une meme variante.',
            ]);
        }

        $sortedValues = $values
            ->sortBy(fn (ProductAttributeValue $value) => [
                $value->attribute?->name ?? '',
                $value->value,
                $value->id,
            ])
            ->values();

        $variantLabel = $sortedValues
            ->map(fn (ProductAttributeValue $value) => trim(($value->attribute?->name ? $value->attribute->name.': ' : '').$value->value))
            ->implode(' · ');

        $variantSignature = $sortedValues
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->implode('-');

        $duplicateExists = Product::query()
            ->where('company_id', $companyId)
            ->where('parent_product_id', $parentProductId)
            ->where('variant_signature', $variantSignature)
            ->when($currentProduct, fn (Builder $query) => $query->whereKeyNot($currentProduct->id))
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'variant_value_ids' => 'Cette combinaison de valeurs existe deja pour ce produit parent.',
            ]);
        }

        return [
            'parent_product_id' => $parentProductId,
            'is_variant' => true,
            'variant_label' => $variantLabel,
            'variant_signature' => $variantSignature,
            'value_ids' => $sortedValues->pluck('id')->all(),
        ];
    }

    private function syncVariantValues(Product $product, array $valueIds): void
    {
        $product->attributeValues()->sync($valueIds);
    }

    private function normalizeSupplierInfos(int $companyId, array $rows, ?Product $product = null, bool $canViewProductCosts = true): array
    {
        $existingBySupplier = ($product?->loadMissing('supplierInfos')->supplierInfos ?? collect())->keyBy('supplier_id');
        $normalized = collect($rows)
            ->map(fn ($row) => is_array($row) ? $row : [])
            ->map(function (array $row) use ($existingBySupplier, $canViewProductCosts) {
                $supplierId = isset($row['supplier_id']) && $row['supplier_id'] !== ''
                    ? (int) $row['supplier_id']
                    : null;

                return [
                    'supplier_id' => $supplierId,
                    'supplier_product_code' => trim((string) ($row['supplier_product_code'] ?? '')) ?: null,
                    'supplier_product_name' => trim((string) ($row['supplier_product_name'] ?? '')) ?: null,
                    'min_qty' => filled($row['min_qty'] ?? null) ? (float) $row['min_qty'] : null,
                    'unit_cost' => $canViewProductCosts
                        ? (filled($row['unit_cost'] ?? null) ? round((float) $row['unit_cost'], 2) : null)
                        : ($supplierId && $existingBySupplier->has($supplierId) ? (float) $existingBySupplier->get($supplierId)->unit_cost : null),
                    'lead_time_days' => filled($row['lead_time_days'] ?? null) ? (int) $row['lead_time_days'] : null,
                    'is_preferred' => filter_var($row['is_preferred'] ?? false, FILTER_VALIDATE_BOOL),
                ];
            })
            ->filter(fn (array $row) => $row['supplier_id'])
            ->values();

        if ($normalized->isEmpty()) {
            return [];
        }

        if ($normalized->pluck('supplier_id')->unique()->count() !== $normalized->count()) {
            throw ValidationException::withMessages([
                'supplier_infos' => 'Chaque fournisseur ne peut apparaitre qu une seule fois sur la fiche produit.',
            ]);
        }

        $preferredInfo = $normalized->firstWhere('is_preferred', true) ?: $normalized->first();
        $preferredSupplierId = $preferredInfo['supplier_id'];

        return $normalized
            ->map(function (array $row) use ($preferredSupplierId) {
                $row['is_preferred'] = $row['supplier_id'] === $preferredSupplierId;

                return $row;
            })
            ->all();
    }

    private function syncSupplierInfos(Product $product, array $supplierInfos): void
    {
        $product->supplierInfos()->delete();

        foreach ($supplierInfos as $supplierInfo) {
            $product->supplierInfos()->create(array_merge($supplierInfo, [
                'tenant_id' => $product->tenant_id,
                'company_id' => $product->company_id,
                'product_id' => $product->id,
            ]));
        }
    }

    private function categories(int $companyId)
    {
        return ProductCategory::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();
    }

    private function suppliers(int $companyId): Collection
    {
        return Partner::query()
            ->suppliers()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function indexQuery(int $companyId, array $filters): Builder
    {
        $balances = StockMovement::query()
            ->select('product_id')
            ->selectRaw('SUM(quantity_in - quantity_out) as current_stock')
            ->where('company_id', $companyId)
            ->groupBy('product_id');

        return Product::query()
            ->with(['category', 'parent'])
            ->where('products.company_id', $companyId)
            ->leftJoin('product_categories', 'products.category_id', '=', 'product_categories.id')
            ->leftJoinSub($balances, 'balances', fn ($join) => $join->on('products.id', '=', 'balances.product_id'))
            ->select('products.*')
            ->selectRaw('COALESCE(balances.current_stock, 0) as current_stock')
            ->when($filters['search'], function (Builder $query, string $search) {
                $like = '%'.$search.'%';

                $query->where(function (Builder $nested) use ($like) {
                    $nested->where('products.name', 'like', $like)
                        ->orWhere('products.sku', 'like', $like)
                        ->orWhere('products.barcode', 'like', $like)
                        ->orWhere('products.variant_label', 'like', $like)
                        ->orWhere('products.sales_description', 'like', $like)
                        ->orWhere('products.purchase_description', 'like', $like)
                        ->orWhere('product_categories.name', 'like', $like)
                        ->orWhereHas('parent', fn (Builder $parentQuery) => $parentQuery->where('name', 'like', $like));
                });
            })
            ->when($filters['category_id'], fn (Builder $query, int $categoryId) => $query->where('products.category_id', $categoryId))
            ->when($filters['type'], fn (Builder $query, string $type) => $query->where('products.type', $type))
            ->when($filters['status'] === 'active', fn (Builder $query) => $query->where('products.is_active', true))
            ->when($filters['status'] === 'inactive', fn (Builder $query) => $query->where('products.is_active', false))
            ->when($filters['stock_state'], fn (Builder $query) => $query->where('products.type', 'stockable'))
            ->when($filters['stock_state'] === 'low', fn (Builder $query) => $query->whereRaw('COALESCE(balances.current_stock, 0) <= products.min_stock'))
            ->when($filters['stock_state'] === 'positive', fn (Builder $query) => $query->whereRaw('COALESCE(balances.current_stock, 0) > products.min_stock'))
            ->when($filters['stock_state'] === 'zero', fn (Builder $query) => $query->whereRaw('COALESCE(balances.current_stock, 0) <= 0'))
            ->orderBy('products.name');
    }

    private function filters(Request $request): array
    {
        $type = $request->string('type')->trim()->value() ?: null;
        if (! in_array($type, ['stockable', 'service'], true)) {
            $type = null;
        }

        $status = $request->string('status')->trim()->value() ?: null;
        if (! in_array($status, ['active', 'inactive'], true)) {
            $status = null;
        }

        $stockState = $request->string('stock_state')->trim()->value() ?: null;
        if (! in_array($stockState, ['low', 'positive', 'zero'], true)) {
            $stockState = null;
        }

        return [
            'search' => $request->string('search')->trim()->value() ?: null,
            'category_id' => $request->integer('category_id') ?: null,
            'type' => $type,
            'status' => $status,
            'stock_state' => $stockState,
            'view' => in_array($request->string('view')->trim()->value(), ['list', 'kanban'], true)
                ? $request->string('view')->trim()->value()
                : 'list',
        ];
    }

    private function deletionGuard(Product $product): array
    {
        $usage = collect([
            ['table' => 'stock_movements', 'label' => 'mouvements de stock'],
            ['table' => 'sales_quote_items', 'label' => 'lignes de devis'],
            ['table' => 'sales_order_items', 'label' => 'lignes de commandes clients'],
            ['table' => 'sales_invoice_items', 'label' => 'lignes de factures clients'],
            ['table' => 'sales_credit_note_items', 'label' => 'lignes d avoirs clients'],
            ['table' => 'delivery_note_items', 'label' => 'lignes de bons de livraison'],
            ['table' => 'purchase_request_items', 'label' => 'lignes de demandes d achat'],
            ['table' => 'purchase_order_items', 'label' => 'lignes de commandes fournisseurs'],
            ['table' => 'purchase_bill_items', 'label' => 'lignes de factures fournisseurs'],
            ['table' => 'goods_receipt_items', 'label' => 'lignes de receptions fournisseurs'],
            ['table' => 'stock_transfer_items', 'label' => 'lignes de transferts de stock'],
            ['table' => 'stock_count_items', 'label' => 'lignes d inventaire'],
            ['table' => 'pos_return_items', 'label' => 'retours point de vente'],
            ['table' => 'price_list_items', 'label' => 'listes de prix'],
            ['table' => 'products', 'label' => 'variantes enfants', 'column' => 'parent_product_id'],
        ])
            ->map(function (array $dependency) use ($product) {
                return [
                    'label' => $dependency['label'],
                    'count' => (int) DB::table($dependency['table'])
                        ->where($dependency['column'] ?? 'product_id', $product->id)
                        ->count(),
                ];
            })
            ->filter(fn (array $dependency) => $dependency['count'] > 0)
            ->values();

        return [
            'can_delete' => $usage->isEmpty(),
            'usage' => $usage,
        ];
    }

    private function taxRules(int $companyId)
    {
        return TaxRule::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function generateSku(int $companyId): string
    {
        return app(\App\Modules\Core\Company\Services\DocumentNumberService::class)
            ->nextNumber($companyId, 'product_sku');
    }

    private function storeUploadedImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        return $request->file('image')->store('products', $this->productImageDisk());
    }

    private function deleteStoredImage(?string $imagePath, ?string $disk = null): void
    {
        $disk = $disk ?: $this->productImageDisk();

        if ($imagePath && Storage::disk($disk)->exists($imagePath)) {
            Storage::disk($disk)->delete($imagePath);
        }
    }

    private function productImageDisk(): string
    {
        return (string) config('nema.product_media_disk', 'public');
    }
}
