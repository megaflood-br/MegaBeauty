<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandService extends Model
{
    protected $fillable = ['command_id', 'service_id', 'professional_id', 'price', 'commission_value'];

    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
