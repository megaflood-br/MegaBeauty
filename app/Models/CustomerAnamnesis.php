<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomerAnamnesis extends Model
{
    protected $fillable = [
        'token',
        'tenant_id',
        'customer_id',
        'service_id',
        'appointment_id',
        'responses',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'responses' => 'array',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (empty($model->token)) {
                $model->token = (string) Str::uuid();
            }
            if (session()->has('tenant_id') && empty($model->tenant_id)) {
                $model->tenant_id = session()->get('tenant_id');
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
