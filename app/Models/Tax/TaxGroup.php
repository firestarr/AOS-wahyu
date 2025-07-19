<?php
// app/Models/Tax/TaxGroup.php

namespace App\Models\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxGroup extends Model
{
    protected $table = 'tax_groups';
    protected $primaryKey = 'tax_group_id';

    protected $fillable = [
        'name',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    /**
     * Get the taxes for this group
     */
    public function taxes(): HasMany
    {
        return $this->hasMany(Tax::class, 'tax_group_id');
    }

    /**
     * Get active taxes for this group
     */
    public function activeTaxes(): HasMany
    {
        return $this->taxes()->where('is_active', true);
    }
}