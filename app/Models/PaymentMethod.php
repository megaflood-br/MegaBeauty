<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'account_id', // Mapeado para permitir o vínculo com a conta bancária
        'name',
        'type',
        'fee_percentage',
        'fixed_fee',
        'payout_days_interval',
        'is_active',
    ];

    protected $casts = [
        'fee_percentage' => 'float',
        'fixed_fee' => 'float',
        'payout_days_interval' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Escopo Global para Multi-Tenant automático
     */
    protected static function booted()
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('tenant_id', auth()->user()->tenant_id);
            }
        });
    }

    /**
     * Relacionamento: O meio de pagamento pode estar vinculado a uma Conta Bancária / Caixa
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Relacionamento: A forma de pagamento pertence a um Tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
