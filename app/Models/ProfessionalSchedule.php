<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalSchedule extends Model
{
    protected $fillable = [
        'professional_id', 'day_of_week', 'start_time', 'end_time',
        'lunch_start', 'lunch_end', 'is_working'
    ];

    protected $casts = [
        'is_working' => 'boolean',
    ];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
