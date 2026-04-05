<?php

namespace App\Modules\Core\Company\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSequence extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'document_type',
        'prefix',
        'next_number',
        'padding',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
