<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ScheduleBlock extends Model
{
    protected $fillable = [
        'tenant_id',
        'professional_id',
        'date',
        'start_time',
        'end_time',
        'title',
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

    public function professional(): BelongsTo { return $this->belongsTo(Professional::class, 'professional_id'); }
}
