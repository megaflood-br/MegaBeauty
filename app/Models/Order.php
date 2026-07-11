<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'appointment_id',
        'total_services',
        'total_products',
        'discount_amount',
        'total_amount',
        'status',
        'closed_at',
    ];

    protected $casts = [
        'total_services' => 'decimal:2',
        'total_products' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * Relacionamento: Uma comanda tem vários itens (serviços e produtos)
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
