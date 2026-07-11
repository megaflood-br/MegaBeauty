<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

   protected $fillable = [
    'tenant_id', 'category_id', 'name', 'brand', 'sku_code', 'cost_price', 'sale_price',
    'professional_price', 'default_commission_type', 'default_commission_value','show_in_commands', // Adicionado aqui
    'stock', 'output_unit_type', 'output_unit_equivalent', 'minimum_stock', 'is_active'
];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
