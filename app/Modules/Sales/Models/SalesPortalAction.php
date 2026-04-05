<?php

namespace App\Modules\Sales\Models;

use App\Modules\Core\Branch\Models\Branch;
use App\Modules\Core\Company\Models\Company;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SalesPortalAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'actionable_type',
        'actionable_id',
        'action_type',
        'signer_name',
        'signer_phone',
        'signer_title',
        'signer_company',
        'signer_note',
        'accepted_terms',
        'signature_hash',
        'signature_image_data_url',
        'signed_at',
        'deposit_amount',
        'deposit_method',
        'deposit_reference',
        'deposit_note',
        'deposit_expected_at',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'accepted_terms' => 'boolean',
            'signed_at' => 'datetime',
            'deposit_expected_at' => 'date',
            'deposit_amount' => 'decimal:2',
            'properties' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function actionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function depositMethodLabel(): ?string
    {
        return match ($this->deposit_method) {
            'bank_transfer' => 'Virement bancaire',
            'wave' => 'Wave',
            'orange_money' => 'Orange Money',
            'moov_money' => 'Moov Money',
            'cash' => 'Especes',
            'cheque' => 'Cheque',
            'other' => 'Autre moyen',
            default => null,
        };
    }

    public function signatureCode(): string
    {
        return strtoupper(substr((string) $this->signature_hash, 0, 12));
    }
}

