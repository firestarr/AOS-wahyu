<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\UnitOfMeasure;
use App\Models\ItemCategory;
use App\Models\CurrencyRate;
use App\Models\ItemPrice;
use App\Models\ItemBatch;
use App\Models\StockTransaction;
use App\Models\ItemStock;
use App\Models\Manufacturing\BOM;
use App\Models\Manufacturing\Routing;
use App\Models\Tax\Tax;

class Item extends Model
{
    use HasFactory;

    protected $table = 'items';
    protected $primaryKey = 'item_id';

    protected $fillable = [
        'item_code',
        'name',
        'description',
        'category_id',
        'uom_id',
        'current_stock',
        'minimum_stock',
        'maximum_stock',
        'is_purchasable',
        'is_sellable',
        'cost_price',
        'sale_price',
        'cost_price_currency',
        'sale_price_currency',
        'length',
        'width',
        'thickness',
        'weight',
        'tape_mat_pcc',
        'document_path',
        'hscode',
    ];

    protected $casts = [
        'current_stock' => 'float',
        'minimum_stock' => 'float',
        'maximum_stock' => 'float',
        'is_purchasable' => 'boolean',
        'is_sellable' => 'boolean',
        'cost_price' => 'float',
        'sale_price' => 'float',
        'length' => 'float',
        'width' => 'float',
        'thickness' => 'float',
        'weight' => 'float',
    ];

    protected $appends = [
        'calculated_current_stock',
        'stock_status',
        'stock_variance',
        'total_available_stock',
        'total_reserved_stock',
        'needs_sync'
    ];

    /**
     * Get the category that this item belongs to
     */
    public function category()
    {
        return $this->belongsTo(ItemCategory::class, 'category_id', 'category_id');
    }

    /**
     * Get the unit of measure for this item
     */
    public function unitOfMeasure()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id', 'uom_id');
    }

    // NEW: Accessor for formatted tape_mat_pcc display
    public function getTapeMatPccDisplayAttribute()
    {
        if (!$this->tape_mat_pcc) {
            return '-';
        }

        return match ($this->tape_mat_pcc) {
            'tape' => 'Tape',
            'material' => 'Material',
            default => ucfirst($this->tape_mat_pcc)
        };
    }

    // NEW: Get all available tape mat pcc options
    public static function getTapeMatPccOptions()
    {
        return [
            'tape' => 'Tape',
            'material' => 'Material'
        ];
    }

    /**
     * Get the batches for this item
     */
    public function batches()
    {
        return $this->hasMany(ItemBatch::class, 'item_id', 'item_id');
    }

    /**
     * Get the stock transactions for this item
     */
    public function stockTransactions()
    {
        return $this->hasMany(StockTransaction::class, 'item_id', 'item_id');
    }

    /**
     * Get the prices for this item
     */
    public function prices()
    {
        return $this->hasMany(ItemPrice::class, 'item_id', 'item_id');
    }

    /**
     * Get only the purchase prices for this item
     */
    public function purchasePrices()
    {
        return $this->hasMany(ItemPrice::class, 'item_id', 'item_id')
            ->where('price_type', 'purchase');
    }

    /**
     * Get only the sale prices for this item
     */
    public function salePrices()
    {
        return $this->hasMany(ItemPrice::class, 'item_id', 'item_id')
            ->where('price_type', 'sale');
    }

    /**
     * Get the BOMs where this item is the product
     */
    public function boms()
    {
        return $this->hasMany(BOM::class, 'item_id', 'item_id');
    }

    /**
     * Get the routings for this item
     */
    public function routings()
    {
        return $this->hasMany(Routing::class, 'item_id', 'item_id');
    }

    /**
     * Get the stocks for this item in all warehouses
     */
    public function stocks()
    {
        return $this->hasMany(ItemStock::class, 'item_id', 'item_id');
    }

    // ===== AUTO-CALCULATE CURRENT STOCK METHODS =====

    /**
     * Calculate current stock from all warehouses
     * This is the source of truth for current stock
     */
    public function getCalculatedCurrentStockAttribute()
    {
        return $this->stocks()->sum('quantity') ?: 0;
    }

    /**
     * Get real-time current stock (alias for calculated_current_stock)
     * Use this instead of current_stock column for accurate data
     */
    public function getRealCurrentStockAttribute()
    {
        return $this->calculated_current_stock;
    }

    /**
     * Check if there's variance between stored and calculated stock
     */
    public function getStockVarianceAttribute()
    {
        return $this->calculated_current_stock - $this->current_stock;
    }

    /**
     * Check if stock needs synchronization
     */
    public function getNeedsSyncAttribute()
    {
        return abs($this->stock_variance) > 0.01; // Allow small rounding differences
    }

    /**
     * Sync the current_stock column with calculated stock
     */
    public function syncCurrentStock()
    {
        $calculatedStock = $this->calculated_current_stock;

        if (abs($this->current_stock - $calculatedStock) > 0.01) {
            $oldStock = $this->current_stock;
            $this->current_stock = $calculatedStock;
            $this->save();

            return [
                'synced' => true,
                'old_stock' => $oldStock,
                'new_stock' => $calculatedStock,
                'variance_fixed' => $calculatedStock - $oldStock
            ];
        }

        return ['synced' => false, 'reason' => 'Already in sync'];
    }

    /**
     * Get total available stock (not reserved) across all warehouses
     */
    public function getTotalAvailableStockAttribute()
    {
        return $this->stocks()->sum('quantity') - $this->stocks()->sum('reserved_quantity');
    }

    /**
     * Get total reserved stock across all warehouses
     */
    public function getTotalReservedStockAttribute()
    {
        return $this->stocks()->sum('reserved_quantity');
    }

    /**
     * Get stock breakdown by warehouse
     */
    public function getStockBreakdownAttribute()
    {
        return $this->stocks()
            ->with('warehouse')
            ->get()
            ->map(function ($stock) {
                return [
                    'warehouse_id' => $stock->warehouse_id,
                    'warehouse_name' => $stock->warehouse->name ?? 'Unknown',
                    'quantity' => $stock->quantity,
                    'reserved_quantity' => $stock->reserved_quantity,
                    'available_quantity' => $stock->quantity - $stock->reserved_quantity
                ];
            });
    }

    // ===== EXISTING WAREHOUSE STOCK METHODS (Enhanced) =====

    /**
     * Get stock at specific warehouse
     *
     * @param int $warehouseId
     * @return ItemStock|null
     */
    public function getStockAtWarehouse($warehouseId)
    {
        return $this->stocks()->where('warehouse_id', $warehouseId)->first();
    }

    /**
     * Check if item has enough stock at specific warehouse
     *
     * @param int $warehouseId
     * @param float $quantity
     * @return bool
     */
    public function hasEnoughStockAtWarehouse($warehouseId, $quantity)
    {
        $stock = $this->getStockAtWarehouse($warehouseId);

        if (!$stock) {
            return false;
        }

        return $stock->quantity >= $quantity;
    }

    /**
     * Get available quantity at specific warehouse (total - reserved)
     *
     * @param int $warehouseId
     * @return float
     */
    public function getAvailableQuantityAtWarehouse($warehouseId)
    {
        $stock = $this->getStockAtWarehouse($warehouseId);

        if (!$stock) {
            return 0;
        }

        return $stock->quantity - $stock->reserved_quantity;
    }

    /**
     * Check if item has enough available stock at warehouse
     *
     * @param int $warehouseId
     * @param float $quantity
     * @return bool
     */
    public function hasEnoughAvailableAtWarehouse($warehouseId, $quantity)
    {
        $availableQty = $this->getAvailableQuantityAtWarehouse($warehouseId);
        return $availableQty >= $quantity;
    }

    /**
     * Check if item has stock in specific warehouse
     */
    public function hasStockInWarehouse($warehouseId)
    {
        return $this->stocks()
            ->where('warehouse_id', $warehouseId)
            ->where('quantity', '>', 0)
            ->exists();
    }

    /**
     * Get stock quantity in specific warehouse
     */
    public function getStockInWarehouse($warehouseId)
    {
        $stock = $this->stocks()
            ->where('warehouse_id', $warehouseId)
            ->first();

        return $stock ? $stock->quantity : 0;
    }

    /**
     * Get the stock status based on min/max levels
     * Enhanced to use calculated stock
     */
    public function getStockStatusAttribute()
    {
        $currentStock = $this->calculated_current_stock; // Use calculated instead of DB value

        if ($currentStock <= 0) {
            return 'out_of_stock';
        } elseif ($this->minimum_stock !== null && $currentStock <= $this->minimum_stock) {
            return 'low_stock';
        } elseif ($this->maximum_stock !== null && $currentStock >= $this->maximum_stock) {
            return 'overstock';
        } else {
            return 'in_stock';
        }
    }

    /**
     * Get human-readable stock status
     */
    public function getStockStatusTextAttribute()
    {
        $status = $this->stock_status;

        return [
            'out_of_stock' => 'Out of Stock',
            'low_stock' => 'Low Stock',
            'overstock' => 'Overstocked',
            'in_stock' => 'In Stock'
        ][$status] ?? 'Unknown';
    }

    /**
     * Get stock status with color coding for UI
     */
    public function getStockStatusColorAttribute()
    {
        $status = $this->stock_status;

        return [
            'out_of_stock' => 'danger',
            'low_stock' => 'warning',
            'overstock' => 'info',
            'in_stock' => 'success'
        ][$status] ?? 'secondary';
    }

    // ===== STATIC METHODS FOR MASS OPERATIONS =====

    /**
     * Static method to sync all items' current_stock
     */
    public static function syncAllCurrentStock()
    {
        $items = self::all();
        $syncedCount = 0;
        $results = [];

        foreach ($items as $item) {
            $result = $item->syncCurrentStock();
            if ($result['synced']) {
                $syncedCount++;
                $results[] = [
                    'item_id' => $item->item_id,
                    'item_code' => $item->item_code,
                    'old_stock' => $result['old_stock'],
                    'new_stock' => $result['new_stock'],
                    'variance_fixed' => $result['variance_fixed']
                ];
            }
        }

        return [
            'total_items_processed' => $items->count(),
            'items_synced' => $syncedCount,
            'items_already_synced' => $items->count() - $syncedCount,
            'sync_details' => $results
        ];
    }

    /**
     * Get items that need stock sync
     */
    public static function getItemsNeedingSync()
    {
        return self::all()->filter(function ($item) {
            return $item->needs_sync;
        });
    }

    /**
     * Get items with stock discrepancies
     */
    public static function getStockDiscrepancies()
    {
        return self::all()
            ->filter(function ($item) {
                return $item->needs_sync;
            })
            ->map(function ($item) {
                return [
                    'item_id' => $item->item_id,
                    'item_code' => $item->item_code,
                    'name' => $item->name,
                    'current_stock' => $item->current_stock,
                    'calculated_stock' => $item->calculated_current_stock,
                    'variance' => $item->stock_variance,
                    'warehouse_breakdown' => $item->stock_breakdown
                ];
            });
    }

    // ===== SCOPES FOR QUERYING =====

    /**
     * Scope for items with low stock
     */
    public function scopeLowStock($query)
    {
        return $query->whereHas('stocks', function ($q) {
            $q->selectRaw('item_id, SUM(quantity) as total_stock')
                ->groupBy('item_id')
                ->havingRaw('SUM(quantity) <= items.minimum_stock');
        });
    }

    /**
     * Scope for items out of stock
     */
    public function scopeOutOfStock($query)
    {
        return $query->whereDoesntHave('stocks', function ($q) {
            $q->where('quantity', '>', 0);
        });
    }

    /**
     * Scope for items that need stock sync
     */
    public function scopeNeedsSync($query)
    {
        return $query->whereHas('stocks', function ($q) {
            $q->selectRaw('item_id, SUM(quantity) as calculated_stock')
                ->groupBy('item_id')
                ->havingRaw('ABS(SUM(quantity) - items.current_stock) > 0.01');
        });
    }

    /**
     * Scope for items with stock in specific warehouse
     */
    public function scopeInWarehouse($query, $warehouseId)
    {
        return $query->whereHas('stocks', function ($q) use ($warehouseId) {
            $q->where('warehouse_id', $warehouseId)
                ->where('quantity', '>', 0);
        });
    }

    // ===== ENHANCED STOCK METHODS =====

    /**
     * Get stock value based on cost price
     */
    public function getStockValueAttribute()
    {
        return $this->calculated_current_stock * $this->cost_price;
    }

    /**
     * Get stock value in specific currency
     */
    public function getStockValueInCurrency($currencyCode, $date = null)
    {
        $costPrice = $this->getDefaultPurchasePriceInCurrency($currencyCode, $date);
        return $this->calculated_current_stock * $costPrice;
    }

    /**
     * Get stock turnover ratio (if you have sales data)
     */
    public function getStockTurnoverRatio($periodDays = 365)
    {
        $periodStart = now()->subDays($periodDays);

        $soldQuantity = $this->stockTransactions()
            ->where('transaction_type', 'sale')
            ->where('transaction_date', '>=', $periodStart)
            ->sum('quantity');

        $averageStock = $this->calculated_current_stock; // Simplified - could be more complex

        return $averageStock > 0 ? ($soldQuantity / $averageStock) : 0;
    }

    // ===== EXISTING PRICING METHODS =====

    /**
     * Get default purchase price for this item in specific currency.
     *
     * @param string $currencyCode
     * @param string|null $date
     * @return float
     */
    public function getDefaultPurchasePriceInCurrency($currencyCode, $date = null)
    {
        // If already in requested currency
        if ($this->cost_price_currency === $currencyCode) {
            return $this->cost_price;
        }

        // Get exchange rate
        $rate = CurrencyRate::getCurrentRate($this->cost_price_currency, $currencyCode, $date);

        if (!$rate) {
            // Return original price if no rate available
            return $this->cost_price;
        }

        return $this->cost_price * $rate;
    }

    /**
     * Get default sale price for this item in specific currency.
     *
     * @param string $currencyCode
     * @param string|null $date
     * @return float
     */
    public function getDefaultSalePriceInCurrency($currencyCode, $date = null)
    {
        // If already in requested currency
        if ($this->sale_price_currency === $currencyCode) {
            return $this->sale_price;
        }

        // Get exchange rate
        $rate = CurrencyRate::getCurrentRate($this->sale_price_currency, $currencyCode, $date);

        if (!$rate) {
            // Return original price if no rate available
            return $this->sale_price;
        }

        return $this->sale_price * $rate;
    }

    /**
     * Get the best purchase price for a specific vendor and quantity in specified currency.
     *
     * @param int|null $vendorId
     * @param float $quantity
     * @param string $currencyCode
     * @param string|null $date
     * @return float
     */
    public function getBestPurchasePriceInCurrency($vendorId = null, $quantity = 1, $currencyCode = null, $date = null)
    {
        $currencyCode = $currencyCode ?? config('app.base_currency', 'USD');
        $date = $date ?? now()->format('Y-m-d');

        // First try to find a vendor-specific price for the given quantity in requested currency
        if ($vendorId) {
            $vendorPrice = $this->purchasePrices()
                ->active()
                ->where('vendor_id', $vendorId)
                ->where('min_quantity', '<=', $quantity)
                ->where('currency_code', $currencyCode)
                ->orderBy('price', 'asc')
                ->orderBy('min_quantity', 'desc')
                ->first();

            if ($vendorPrice) {
                return $vendorPrice->price;
            }

            // Try to find vendor-specific price in any currency and convert
            $anyVendorPrice = $this->purchasePrices()
                ->active()
                ->where('vendor_id', $vendorId)
                ->where('min_quantity', '<=', $quantity)
                ->orderBy('price', 'asc')
                ->orderBy('min_quantity', 'desc')
                ->first();

            if ($anyVendorPrice) {
                return $anyVendorPrice->getPriceInCurrency($currencyCode, $date);
            }
        }

        // Next try to find a general purchase price in requested currency
        $generalPrice = $this->purchasePrices()
            ->active()
            ->whereNull('vendor_id')
            ->where('min_quantity', '<=', $quantity)
            ->where('currency_code', $currencyCode)
            ->orderBy('price', 'asc')
            ->orderBy('min_quantity', 'desc')
            ->first();

        if ($generalPrice) {
            return $generalPrice->price;
        }

        // Try to find any general price and convert
        $anyGeneralPrice = $this->purchasePrices()
            ->active()
            ->whereNull('vendor_id')
            ->where('min_quantity', '<=', $quantity)
            ->orderBy('price', 'asc')
            ->orderBy('min_quantity', 'desc')
            ->first();

        if ($anyGeneralPrice) {
            return $anyGeneralPrice->getPriceInCurrency($currencyCode, $date);
        }

        // If no price found, return the default cost price in requested currency
        return $this->getDefaultPurchasePriceInCurrency($currencyCode, $date);
    }

    /**
     * Get the best sale price for a specific customer and quantity in specified currency.
     *
     * @param int|null $customerId
     * @param float $quantity
     * @param string $currencyCode
     * @param string|null $date
     * @return float
     */
    public function getBestSalePriceInCurrency($customerId = null, $quantity = 1, $currencyCode = null, $date = null)
    {
        $currencyCode = $currencyCode ?? config('app.base_currency', 'USD');
        $date = $date ?? now()->format('Y-m-d');

        // First try to find a customer-specific price for the given quantity in requested currency
        if ($customerId) {
            $customerPrice = $this->salePrices()
                ->active()
                ->where('customer_id', $customerId)
                ->where('min_quantity', '<=', $quantity)
                ->where('currency_code', $currencyCode)
                ->orderBy('price', 'asc')
                ->orderBy('min_quantity', 'desc')
                ->first();

            if ($customerPrice) {
                return $customerPrice->price;
            }

            // Try to find customer-specific price in any currency and convert
            $anyCustomerPrice = $this->salePrices()
                ->active()
                ->where('customer_id', $customerId)
                ->where('min_quantity', '<=', $quantity)
                ->orderBy('price', 'asc')
                ->orderBy('min_quantity', 'desc')
                ->first();

            if ($anyCustomerPrice) {
                return $anyCustomerPrice->getPriceInCurrency($currencyCode, $date);
            }
        }

        // Next try to find a general sale price in requested currency
        $generalPrice = $this->salePrices()
            ->active()
            ->whereNull('customer_id')
            ->where('min_quantity', '<=', $quantity)
            ->where('currency_code', $currencyCode)
            ->orderBy('price', 'asc')
            ->orderBy('min_quantity', 'desc')
            ->first();

        if ($generalPrice) {
            return $generalPrice->price;
        }

        // Try to find any general price and convert
        $anyGeneralPrice = $this->salePrices()
            ->active()
            ->whereNull('customer_id')
            ->where('min_quantity', '<=', $quantity)
            ->orderBy('price', 'asc')
            ->orderBy('min_quantity', 'desc')
            ->first();

        if ($anyGeneralPrice) {
            return $anyGeneralPrice->getPriceInCurrency($currencyCode, $date);
        }

        // If no price found, return the default sale price in requested currency
        return $this->getDefaultSalePriceInCurrency($currencyCode, $date);
    }

    // ===== BOOT METHOD FOR AUTO-SYNC (OPTIONAL) =====

    /**
     * Boot method to automatically sync stock when needed
     */
    protected static function boot()
    {
        parent::boot();

        // Uncomment this to enable auto-sync when item is retrieved
        // Warning: This might impact performance on large datasets
        /*
        static::retrieved(function ($item) {
            if ($item->needs_sync) {
                $item->syncCurrentStock();
            }
        });
        */

        // Auto-sync when ItemStock changes (via model events)
        // This requires ItemStock model to fire events when changed
        // Example in ItemStock model:
        // static::saved(function ($itemStock) {
        //     $item = $itemStock->item;
        //     if ($item && $item->needs_sync) {
        //         $item->syncCurrentStock();
        //     }
        // });
    }

    /**
     * Override toArray to include calculated stock information
     */
    public function toArray()
    {
        $array = parent::toArray();

        // Always include the real current stock information
        $array['real_current_stock'] = $this->calculated_current_stock;
        $array['stock_variance'] = $this->stock_variance;
        $array['needs_sync'] = $this->needs_sync;
        $array['stock_status_text'] = $this->stock_status_text;
        $array['stock_status_color'] = $this->stock_status_color;

        return $array;
    }

    /**
     * Override toJson to include stock information
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }
    /**
     * Increase stock quantity (with auto Item.current_stock update)
     *
     * @param float $quantity
     * @return void
     */
    public function increaseStock($quantity)
    {
        $this->quantity += $quantity;
        $this->save(); // Will trigger model events for auto-sync
    }

    /**
     * Decrease stock quantity (with auto Item.current_stock update)
     *
     * @param float $quantity
     * @return bool
     */
    public function decreaseStock($quantity)
    {
        if ($this->quantity < $quantity) {
            return false;
        }

        $this->quantity -= $quantity;
        $this->save(); // Will trigger model events for auto-sync

        return true;
    }
    /**
     * Get sales taxes for this item
     */
    public function salesTaxes(): BelongsToMany
    {
        return $this->belongsToMany(Tax::class, 'item_taxes', 'item_id', 'tax_id')
                    ->wherePivot('tax_type', 'sales')
                    ->where('is_active', true)
                    ->orderBy('sequence');
    }

    /**
     * Get purchase taxes for this item
     */
    public function purchaseTaxes(): BelongsToMany
    {
        return $this->belongsToMany(Tax::class, 'item_taxes', 'item_id', 'tax_id')
                    ->wherePivot('tax_type', 'purchase')
                    ->where('is_active', true)
                    ->orderBy('sequence');
    }

    /**
     * Get all taxes for this item
     */
    public function taxes(): BelongsToMany
    {
        return $this->belongsToMany(Tax::class, 'item_taxes', 'item_id', 'tax_id')
                    ->withPivot('tax_type')
                    ->where('is_active', true)
                    ->orderBy('sequence');
    }

    /**
     * Add sales tax to item
     */
    public function addSalesTax($taxId)
    {
        return $this->taxes()->attach($taxId, ['tax_type' => 'sales']);
    }

    /**
     * Add purchase tax to item
     */
    public function addPurchaseTax($taxId)
    {
        return $this->taxes()->attach($taxId, ['tax_type' => 'purchase']);
    }

    /**
     * Remove tax from item
     */
    public function removeTax($taxId, $taxType = null)
    {
        $query = $this->taxes();
        
        if ($taxType) {
            $query->wherePivot('tax_type', $taxType);
        }
        
        return $query->detach($taxId);
    }

    /**
     * Get default tax rate for sales (combined rate)
     */
    public function getDefaultSalesTaxRateAttribute()
    {
        return $this->salesTaxes->sum('rate');
    }

    /**
     * Get default tax rate for purchases (combined rate)
     */
    public function getDefaultPurchaseTaxRateAttribute()
    {
        return $this->purchaseTaxes->sum('rate');
    }

    /**
     * Check if item has any inclusive taxes
     */
    public function hasInclusiveTaxes($type = 'sales')
    {
        $taxes = $type === 'sales' ? $this->salesTaxes : $this->purchaseTaxes;
        return $taxes->where('included_in_price', true)->isNotEmpty();
    }
    /**
     * Set new quantity (with auto Item.current_stock update)
     *
     * @param float $newQuantity
     * @return void
     */
    public function setQuantity($newQuantity)
    {
        $this->quantity = $newQuantity;
        $this->save(); // Will trigger model events for auto-sync
    }
}
