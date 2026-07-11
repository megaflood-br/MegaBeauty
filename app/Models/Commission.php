<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Commission extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'transaction_id',
        'source_type',
        'source_id',
        'base_amount',
        'commission_percentage',
        'calculated_amount',
        'status',
        'accrued_date',
        'professional_payment_id'
    ];

    /**
     * Relação polimórfica nativa para buscar dinamicamente a origem (Comanda ou outra tabela)
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
