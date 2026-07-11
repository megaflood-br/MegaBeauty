<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandProduct extends Model
{
    protected $fillable = ['command_id', 'product_id', 'quantity', 'price'];

    public function command(): BelongsTo
    {
        return $this->belongsTo(Command::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
