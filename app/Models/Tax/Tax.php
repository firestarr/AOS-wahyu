<?php
// app/Models/Tax/Tax.php
namespace App\Models\Tax;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Item;
use App\Models\Sales\Customer;
use App\Models\Vendor;

class Tax extends Model
{
    protected $table = 'taxes';
    protected $primaryKey = 'tax_id';

    protected $fillable = [
        'name',
        'description',
        'tax_type',
        'computation_type',
        'rate',
        'amount',
        'is_active',
        'included_in_price',
        'sequence',
        'tax_group_id'
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'included_in_price' => 'boolean',
        'sequence' => 'integer'
    ];

    /**
     * Get the tax group
     */
    public function taxGroup(): BelongsTo
    {
        return $this->belongsTo(TaxGroup::class, 'tax_group_id');
    }

    /**
     * Get items that use this tax for sales
     */
    public function salesItems(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_taxes', 'tax_id', 'item_id')
                    ->wherePivot('tax_type', 'sales');
    }

    /**
     * Get items that use this tax for purchases
     */
    public function purchaseItems(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'item_taxes', 'tax_id', 'item_id')
                    ->wherePivot('tax_type', 'purchase');
    }

    /**
     * Get customers that use this tax by default
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_taxes', 'tax_id', 'customer_id');
    }

    /**
     * Get vendors that use this tax by default
     */
    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(Vendor::class, 'vendor_taxes', 'tax_id', 'vendor_id');
    }

    /**
     * Calculate tax amount for a given base amount
     */
    public function calculateTaxAmount($baseAmount, $isIncluded = null)
    {
        $isIncluded = $isIncluded ?? $this->included_in_price;

        if ($this->computation_type === 'fixed') {
            return $this->amount;
        }

        if ($isIncluded) {
            // Tax is included in the price, calculate the tax portion
            return $baseAmount - ($baseAmount / (1 + ($this->rate / 100)));
        } else {
            // Tax is added on top
            return $baseAmount * ($this->rate / 100);
        }
    }

    /**
     * Calculate the price excluding tax when tax is included
     */
    public function calculateExcludingAmount($includingAmount)
    {
        if ($this->computation_type === 'fixed') {
            return $includingAmount - $this->amount;
        }

        return $includingAmount / (1 + ($this->rate / 100));
    }

    /**
     * Scope for active taxes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for sales taxes
     */
    public function scopeSales($query)
    {
        return $query->whereIn('tax_type', ['sales', 'both']);
    }

    /**
     * Scope for purchase taxes
     */
    public function scopePurchase($query)
    {
        return $query->whereIn('tax_type', ['purchase', 'both']);
    }
}