<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Service extends Model
{
    protected $fillable = [
        'tenant_id', 'category_id', 'name', 'slug', 'price',
        'additional_cost', 'commission_percentage', 'duration_minutes',
        'description', 'is_active', 'image_path','anamnesis_template_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relacionamento corrigido apontando explicitamente para a tabela pivô 'service_product'
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'service_product', 'service_id', 'product_id')
                    ->withPivot('consumed_quantity')
                    ->withTimestamps();
    }

    // Adicione no seu app/Models/Service.php
    public function anamnesisTemplate()
    {
        return $this->belongsTo(AnamnesisTemplate::class);
    }
}
