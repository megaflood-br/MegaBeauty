<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Command extends Model
{
    protected $fillable = [
        'tenant_id', 'customer_id', 'professional_id', 'code', 'status',
        'total_services', 'total_products', 'discount', 'total_amount',
        'payment_method', 'finished_at'
    ];

    protected $casts = [
        'finished_at' => 'datetime',
    ];

    /**
     * Relacionamento corrigido temporariamente para apontar para User
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(CommandService::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(CommandProduct::class);
    }
}
