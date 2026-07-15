<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnamnesisTemplate extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'form_schema',
    ];

    protected $casts = [
        'form_schema' => 'array',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (session()->has('tenant_id') && empty($model->tenant_id)) {
                $model->tenant_id = session()->get('tenant_id');
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
