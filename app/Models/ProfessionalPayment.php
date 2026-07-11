<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfessionalPayment extends Model
{
    // Define a tabela explicitamente para não ter erro
    protected $table = 'professional_payments';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'professional_id',
        'amount',
        'payment_date',
        'payment_method',
        'notes',
    ];
}
