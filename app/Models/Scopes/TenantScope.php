<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * Aplica o escopo a todas as consultas (queries) do Model.
     */
    public function apply(Builder $builder, Model $model)
    {
        // Só aplica o filtro automático se houver um usuário logado no sistema
        if (Auth::check() && Auth::user()->tenant_id) {
            $builder->where('tenant_id', Auth::user()->tenant_id);
        }
    }
}
