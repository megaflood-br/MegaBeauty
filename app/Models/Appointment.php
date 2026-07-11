<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Appointment extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'professional_id',
        'service_id',
        'command_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'notes'
    ];

    protected static function booted()
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('tenant_id', auth()->user()->tenant_id);
            }
        });
    }

    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_id'); }
    public function professional(): BelongsTo { return $this->belongsTo(Professional::class, 'professional_id'); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class, 'service_id'); }
    public function command(): BelongsTo { return $this->belongsTo(Command::class, 'command_id'); }
}
