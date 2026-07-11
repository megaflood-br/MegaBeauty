<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialCategory extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'name', 'type', 'is_active'];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
