<?php

// app/Models/Tax/CustomerTax.php

namespace App\Models\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Sales\Customer;

class CustomerTax extends Model
{
    protected $table = 'customer_taxes';

    protected $fillable = [
        'customer_id',
        'tax_id'
    ];

    /**
     * Get the customer
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the tax
     */
    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }
}