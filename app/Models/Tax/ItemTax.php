<?php
// app/Models/Tax/ItemTax.php

namespace App\Models\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Item;

class ItemTax extends Model
{
    protected $table = 'item_taxes';

    protected $fillable = [
        'item_id',
        'tax_id',
        'tax_type'
    ];

    /**
     * Get the item
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * Get the tax
     */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }
}
