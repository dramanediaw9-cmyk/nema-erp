<?php

namespace App\Modules\Catalog\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\TaxRule;
use App\Modules\Inventory\Models\ProductLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Partners\Models\Partner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Validation\ValidationException;

class Product extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'category_id',
        'parent_product_id',
        'sku',
        'barcode',
        'name',
        'unit',
        'sales_unit_name',
        'sales_unit_ratio',
        'purchase_unit_name',
        'purchase_unit_ratio',
        'type',
        'sale_ok',
        'purchase_ok',
        'sale_blocked',
        'sale_block_reason',
        'purchase_blocked',
        'purchase_block_reason',
        'invoice_policy',
        'tracking_type',
        'sale_price',
        'purchase_price',
        'sale_tax_rule_id',
        'purchase_tax_rule_id',
        'min_stock',
        'auto_replenish',
        'reorder_max_qty',
        'reorder_multiple_qty',
        'purchase_lead_time_days',
        'description',
        'sales_description',
        'purchase_description',
        'internal_notes',
        'image_path',
        'image_disk',
        'is_active',
        'is_variant',
        'variant_label',
        'variant_signature',
    ];

    protected function casts(): array
    {
        return [
            'sale_ok' => 'boolean',
            'purchase_ok' => 'boolean',
            'sale_blocked' => 'boolean',
            'purchase_blocked' => 'boolean',
            'sales_unit_ratio' => 'decimal:3',
            'purchase_unit_ratio' => 'decimal:3',
            'sale_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'min_stock' => 'decimal:3',
            'auto_replenish' => 'boolean',
            'reorder_max_qty' => 'decimal:3',
            'reorder_multiple_qty' => 'decimal:3',
            'purchase_lead_time_days' => 'integer',
            'is_active' => 'boolean',
            'is_variant' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_product_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_product_id')->orderBy('name');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductAttributeValue::class, 'product_variant_attribute_value')
            ->with(['attribute'])
            ->withTimestamps();
    }

    public function lots(): HasMany
    {
        return $this->hasMany(ProductLot::class)->latest('received_at')->latest('id');
    }

    public function saleTaxRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class, 'sale_tax_rule_id');
    }

    public function purchaseTaxRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class, 'purchase_tax_rule_id');
    }

    public function supplierInfos(): HasMany
    {
        return $this->hasMany(ProductSupplier::class)->orderByDesc('is_preferred')->orderBy('supplier_id');
    }

    public function preferredSupplierInfo(): HasOne
    {
        return $this->hasOne(ProductSupplier::class)->where('is_preferred', true);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSaleable(Builder $query): Builder
    {
        return $query->active()
            ->where('sale_ok', true)
            ->where('sale_blocked', false);
    }

    public function scopePurchasable(Builder $query): Builder
    {
        return $query->active()
            ->where('purchase_ok', true)
            ->where('purchase_blocked', false);
    }

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_product_id');
    }

    public function salesUnitSummary(): ?string
    {
        $label = trim((string) $this->sales_unit_name);
        $ratio = (float) ($this->sales_unit_ratio ?? 0);

        if ($label === '' || $ratio <= 0) {
            return null;
        }

        return $label.' x '.rtrim(rtrim(number_format($ratio, 3, '.', ''), '0'), '.');
    }

    public function purchaseUnitSummary(): ?string
    {
        $label = trim((string) $this->purchase_unit_name);
        $ratio = (float) ($this->purchase_unit_ratio ?? 0);

        if ($label === '' || $ratio <= 0) {
            return null;
        }

        return $label.' x '.rtrim(rtrim(number_format($ratio, 3, '.', ''), '0'), '.');
    }

    public function variantValuesSummary(): ?string
    {
        if (! $this->relationLoaded('attributeValues')) {
            $this->load('attributeValues.attribute');
        }

        $segments = $this->attributeValues
            ->sortBy(fn (ProductAttributeValue $value) => ($value->attribute?->name ?? '').' '.$value->value)
            ->map(fn (ProductAttributeValue $value) => ($value->attribute?->name ? $value->attribute->name.': ' : '').$value->value)
            ->values();

        return $segments->isEmpty() ? null : $segments->implode(' · ');
    }

    public function supplierInfoFor(?Partner $supplier = null): ?ProductSupplier
    {
        if (! $this->relationLoaded('supplierInfos')) {
            $this->load('supplierInfos.supplier');
        }

        $infos = $this->supplierInfos
            ->sortByDesc(fn (ProductSupplier $info) => (int) $info->is_preferred)
            ->values();

        if ($supplier) {
            $match = $infos->firstWhere('supplier_id', $supplier->id);
            if ($match) {
                return $match;
            }
        }

        return $infos->first();
    }

    public function getDisplayNameAttribute(): string
    {
        if (! $this->is_variant) {
            return (string) $this->name;
        }

        $baseName = $this->parent?->name ?: $this->name;
        $variantPart = $this->variant_label ?: $this->variantValuesSummary();

        return $variantPart ? trim($baseName.' · '.$variantPart) : (string) $baseName;
    }

    public function familyName(): ?string
    {
        if ($this->is_variant) {
            return $this->parent?->name;
        }

        return $this->variants()->exists() ? $this->name : null;
    }

    public function saleBlockSummary(): ?string
    {
        $reason = trim((string) $this->sale_block_reason);

        return $reason !== '' ? $reason : null;
    }

    public function purchaseBlockSummary(): ?string
    {
        $reason = trim((string) $this->purchase_block_reason);

        return $reason !== '' ? $reason : null;
    }

    public function assertAvailableForSale(string $context = 'vente'): void
    {
        if (! $this->is_active) {
            throw ValidationException::withMessages([
                'items' => 'Le produit '.$this->display_name.' est inactif et ne peut pas etre utilise pour cette '.$context.'.',
            ]);
        }

        if (! $this->sale_ok) {
            throw ValidationException::withMessages([
                'items' => 'Le produit '.$this->display_name.' n est pas autorise a la vente.',
            ]);
        }

        if ($this->sale_blocked) {
            throw ValidationException::withMessages([
                'items' => 'Le produit '.$this->display_name.' est temporairement bloque a la vente.'.($this->saleBlockSummary() ? ' Motif : '.$this->saleBlockSummary().'.' : ''),
            ]);
        }
    }

    public function assertAvailableForPurchase(string $context = 'achat'): void
    {
        if (! $this->is_active) {
            throw ValidationException::withMessages([
                'items' => 'Le produit '.$this->display_name.' est inactif et ne peut pas etre utilise pour cet '.$context.'.',
            ]);
        }

        if (! $this->purchase_ok) {
            throw ValidationException::withMessages([
                'items' => 'Le produit '.$this->display_name.' n est pas autorise a l achat.',
            ]);
        }

        if ($this->purchase_blocked) {
            throw ValidationException::withMessages([
                'items' => 'Le produit '.$this->display_name.' est temporairement bloque a l achat.'.($this->purchaseBlockSummary() ? ' Motif : '.$this->purchaseBlockSummary().'.' : ''),
            ]);
        }
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return url(route('products.media.show', ['path' => $this->image_path], false));
    }

    public function imageDisk(): string
    {
        return (string) ($this->image_disk ?: config('nema.product_media_disk', 'public'));
    }
}
