<?php

namespace App\Modules\Partners\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Company\Models\Company;
use App\Modules\Core\Company\Models\PaymentTerm;
use App\Modules\Core\Company\Models\PriceList;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'type',
        'code',
        'name',
        'phone',
        'email',
        'city',
        'nif',
        'address',
        'opening_balance',
        'payment_term_id',
        'price_list_id',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function paymentTerm(): BelongsTo
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(PartnerContact::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(PartnerAddress::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(PartnerBankAccount::class);
    }

    public function mobileWallets(): HasMany
    {
        return $this->hasMany(PartnerMobileWallet::class);
    }

    public function scopeCustomers(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('type', ['customer', 'both']);
    }

    public function scopeSuppliers(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('type', ['supplier', 'both']);
    }
}

