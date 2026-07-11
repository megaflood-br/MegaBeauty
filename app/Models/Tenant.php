<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

   protected $fillable = [
    'name', 'slug', 'status', 'logo', 'company_type', 'document_cpf_cnpj', 'rg',
    'postal_code', 'address', 'number', 'complement', 'district', 'city', 'state'
];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
