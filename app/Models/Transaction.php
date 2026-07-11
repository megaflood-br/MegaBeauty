<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'financial_category_id',
        'account_id', // Certifique-se de que está mapeado aqui
        'payment_method_id',
        'gross_amount',
        'fee_amount',
        'net_amount',
        'due_date',
        'payment_date',
        'status',
        'notes',
        'source_type',
        'source_reference'
    ];

    /**
     * Relacionamento com a Conta Bancária / Caixa
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function financialCategory(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'financial_category_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class, 'professional_id');
    }
}
