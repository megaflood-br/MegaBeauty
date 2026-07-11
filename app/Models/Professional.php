<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Professional extends Model
{
    protected $fillable = [
    'tenant_id', 'user_id', 'photo', 'name', 'nickname', 'profession', 'generate_schedule', 'phone', 'email', 'birthday',
    'cpf', 'rg', 'cnpj', 'cep', 'street', 'number', 'complement', 'neighborhood', 'city', 'state',
    'earns_commission', 'commission_type', 'default_commission', 'tax_deduction_rule', 'discount_deduction_rule',
    'deduct_additional_cost', 'deduct_consumed_products', 'consumed_product_price_type', 'notes', 'is_active'
];

protected $casts = [
    'is_active' => 'boolean',
    'generate_schedule' => 'boolean',
    'earns_commission' => 'boolean',
    'deduct_additional_cost' => 'boolean',
    'birthday' => 'date',
];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ProfessionalSchedule::class)->orderBy('day_of_week', 'asc');
    }
}
