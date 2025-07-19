<?php
// app/Models/Sales/SOLine.php (Updated with new tax fields)

namespace App\Models\Sales;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Item;
use App\Models\UnitOfMeasure;
use App\Models\Sales\DeliveryLine;
use App\models\Sales\Customer;
use App\models\Sales\SalesOrder;
use App\Models\Tax\Tax;
use App\Models\Tax\TaxGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SOLine extends Model
{
    protected $table = 'SOLine';
    protected $primaryKey = 'line_id';
    public $timestamps = true;

    protected $fillable = [
        'so_id',
        'item_id',
        'unit_price',
        'quantity',
        'uom_id',
        'delivery_date',
        'discount',
        'tax',
        'tax_rate',
        'applied_taxes',
        'subtotal_before_tax',
        'tax_inclusive_amount',
        'subtotal',
        'total',
        'base_currency_unit_price',
        'base_currency_subtotal',
        'base_currency_discount',
        'base_currency_tax',
        'base_currency_total',
        'notes'
    ];

    protected $casts = [
        'unit_price' => 'decimal:5',
        'quantity' => 'decimal:4',
        'discount' => 'decimal:5',
        'tax' => 'decimal:5',
        'tax_rate' => 'decimal:4',
        'applied_taxes' => 'array', // JSON field for tax breakdown
        'subtotal_before_tax' => 'decimal:5',
        'tax_inclusive_amount' => 'decimal:5',
        'subtotal' => 'decimal:5',
        'total' => 'decimal:5',
        'base_currency_unit_price' => 'decimal:5',
        'base_currency_subtotal' => 'decimal:5',
        'base_currency_discount' => 'decimal:5',
        'base_currency_tax' => 'decimal:5',
        'base_currency_total' => 'decimal:5',
        'delivery_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the sales order that owns this line
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'so_id', 'so_id');
    }

    /**
     * Get the item for this line
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    /**
     * Get the unit of measure for this line
     */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id', 'uom_id');
    }

    /**
     * Get delivery lines for this sales order line
     */
    public function deliveryLines(): HasMany
    {
        return $this->hasMany(DeliveryLine::class, 'so_line_id', 'line_id');
    }

    /**
     * Calculate remaining quantity to be delivered
     */
    public function getRemainingQuantityAttribute()
    {
        $deliveredQuantity = $this->deliveryLines()->sum('quantity');
        return $this->quantity - $deliveredQuantity;
    }

    /**
     * Check if this line is fully delivered
     */
    public function getIsFullyDeliveredAttribute()
    {
        return $this->remaining_quantity <= 0;
    }

    /**
     * Get delivery status for this line
     */
    public function getDeliveryStatusAttribute()
    {
        $remaining = $this->remaining_quantity;
        
        if ($remaining <= 0) {
            return 'Fully Delivered';
        } elseif ($remaining < $this->quantity) {
            return 'Partially Delivered';
        } else {
            return 'Not Delivered';
        }
    }

    /**
     * Get tax breakdown from applied_taxes JSON
     */
    public function getTaxBreakdownAttribute()
    {
        return $this->applied_taxes ?? [];
    }

    /**
     * Get effective tax rate (handles both percentage and combined rates)
     */
    public function getEffectiveTaxRateAttribute()
    {
        if ($this->subtotal_before_tax > 0) {
            return ($this->tax / $this->subtotal_before_tax) * 100;
        }
        return $this->tax_rate;
    }

    /**
     * Check if this line has tax-inclusive pricing
     */
    public function getHasInclusiveTaxAttribute()
    {
        $taxBreakdown = $this->tax_breakdown;
        if (empty($taxBreakdown)) {
            return false;
        }

        foreach ($taxBreakdown as $tax) {
            if (isset($tax['included_in_price']) && $tax['included_in_price']) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get unit price excluding tax
     */
    public function getUnitPriceExcludingTaxAttribute()
    {
        if ($this->has_inclusive_tax && $this->quantity > 0) {
            return $this->subtotal_before_tax / $this->quantity;
        }
        return $this->unit_price;
    }

    /**
     * Calculate line total with current values
     */
    public function calculateTotal()
    {
        $subtotal = $this->unit_price * $this->quantity;
        return $subtotal - $this->discount + $this->tax;
    }

    /**
     * Recalculate all amounts based on current price and quantity
     */
    public function recalculateAmounts($exchangeRate = 1.0)
    {
        $this->subtotal = $this->unit_price * $this->quantity;
        $this->total = $this->subtotal - $this->discount + $this->tax;
        
        // Base currency calculations
        $this->base_currency_unit_price = $this->unit_price * $exchangeRate;
        $this->base_currency_subtotal = $this->subtotal * $exchangeRate;
        $this->base_currency_discount = $this->discount * $exchangeRate;
        $this->base_currency_tax = $this->tax * $exchangeRate;
        $this->base_currency_total = $this->total * $exchangeRate;
        
        return $this;
    }
}