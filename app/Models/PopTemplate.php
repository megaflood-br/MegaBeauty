<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PopTemplate extends Model
{
    protected $fillable = [
        'tenant_id',
        'title',
        'content',
        'category',
        'is_active'
    ];

    protected static function booted()
    {
        // Adiciona o filtro global de tenant (segurança multi-tenant)
        static::addGlobalScope(new TenantScope);

        // Preenche o tenant_id automaticamente ao criar um novo registro pelo sistema web
        static::creating(function ($model) {
            if (Auth::check() && Auth::user()->tenant_id) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });
    }
}
