<?php
// app/Services/TaxCalculationService.php

namespace App\Services;

use App\Models\Tax\Tax;
use App\Models\Item;
use App\Models\Sales\Customer;
use App\Models\Vendor;
use Illuminate\Support\Collection;

class TaxCalculationService
{
    /**
     * Get applicable taxes for an item and customer/vendor combination
     */
    public function getApplicableTaxes($itemId, $customerId = null, $vendorId = null, $type = 'sales')
    {
        $taxes = collect();

        // 1. Get taxes from item configuration
        $item = Item::with(['salesTaxes', 'purchaseTaxes'])->find($itemId);
        if ($item) {
            if ($type === 'sales' && $item->salesTaxes) {
                $taxes = $taxes->merge($item->salesTaxes);
            } elseif ($type === 'purchase' && $item->purchaseTaxes) {
                $taxes = $taxes->merge($item->purchaseTaxes);
            }
        }

        // 2. If no item taxes found, get default taxes from customer/vendor
        if ($taxes->isEmpty()) {
            if ($type === 'sales' && $customerId) {
                $customer = Customer::with('taxes')->find($customerId);
                if ($customer && $customer->taxes) {
                    $taxes = $taxes->merge($customer->taxes->where('tax_type', 'sales'));
                }
            } elseif ($type === 'purchase' && $vendorId) {
                $vendor = Vendor::with('taxes')->find($vendorId);
                if ($vendor && $vendor->taxes) {
                    $taxes = $taxes->merge($vendor->taxes->where('tax_type', 'purchase'));
                }
            }
        }

        // 3. Filter active taxes and sort by sequence
        return $taxes->where('is_active', true)
                    ->sortBy('sequence')
                    ->values();
    }

    /**
     * Calculate taxes for a line item
     */
    public function calculateLineTaxes($unitPrice, $quantity, $discount = 0, $taxes = null, $itemId = null, $customerId = null, $vendorId = null, $type = 'sales')
    {
        // Get applicable taxes if not provided
        if ($taxes === null) {
            $taxes = $this->getApplicableTaxes($itemId, $customerId, $vendorId, $type);
        }

        // If taxes is not a collection, make it one
        if (!$taxes instanceof Collection) {
            $taxes = collect($taxes);
        }

        $subtotal = $unitPrice * $quantity;
        $subtotalAfterDiscount = $subtotal - $discount;
        
        $taxDetails = [];
        $totalTaxAmount = 0;
        $baseAmount = $subtotalAfterDiscount;

        // Sort taxes by sequence for proper calculation order
        $sortedTaxes = $taxes->sortBy('sequence');

        foreach ($sortedTaxes as $tax) {
            $taxAmount = $tax->calculateTaxAmount($baseAmount, $tax->included_in_price);
            
            $taxDetails[] = [
                'tax_id' => $tax->tax_id,
                'tax_name' => $tax->name,
                'tax_rate' => $tax->rate,
                'computation_type' => $tax->computation_type,
                'base_amount' => $baseAmount,
                'tax_amount' => $taxAmount,
                'included_in_price' => $tax->included_in_price
            ];

            $totalTaxAmount += $taxAmount;

            // If tax is not included in price, add it to base for next tax calculation
            if (!$tax->included_in_price) {
                $baseAmount += $taxAmount;
            }
        }

        // Calculate combined tax rate
        $combinedTaxRate = $subtotalAfterDiscount > 0 ? ($totalTaxAmount / $subtotalAfterDiscount) * 100 : 0;

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'subtotal_after_discount' => $subtotalAfterDiscount,
            'tax_details' => $taxDetails,
            'total_tax_amount' => $totalTaxAmount,
            'combined_tax_rate' => $combinedTaxRate,
            'line_total' => $subtotalAfterDiscount + $totalTaxAmount,
            'price_includes_tax' => $taxes->where('included_in_price', true)->isNotEmpty()
        ];
    }

    /**
     * Calculate taxes for multiple line items (order level)
     */
    public function calculateOrderTaxes($lines, $customerId = null, $vendorId = null, $type = 'sales')
    {
        $orderTaxSummary = [];
        $orderSubtotal = 0;
        $orderTotalTax = 0;
        $orderTotal = 0;

        foreach ($lines as $line) {
            $itemId = $line['item_id'] ?? null;
            $unitPrice = $line['unit_price'] ?? 0;
            $quantity = $line['quantity'] ?? 1;
            $discount = $line['discount'] ?? 0;

            $lineTaxCalc = $this->calculateLineTaxes(
                $unitPrice, 
                $quantity, 
                $discount, 
                null, 
                $itemId, 
                $customerId, 
                $vendorId, 
                $type
            );

            // Add line to order totals
            $orderSubtotal += $lineTaxCalc['subtotal_after_discount'];
            $orderTotalTax += $lineTaxCalc['total_tax_amount'];
            $orderTotal += $lineTaxCalc['line_total'];

            // Aggregate tax details for order summary
            foreach ($lineTaxCalc['tax_details'] as $taxDetail) {
                $taxId = $taxDetail['tax_id'];
                
                if (!isset($orderTaxSummary[$taxId])) {
                    $orderTaxSummary[$taxId] = [
                        'tax_id' => $taxId,
                        'tax_name' => $taxDetail['tax_name'],
                        'tax_rate' => $taxDetail['tax_rate'],
                        'computation_type' => $taxDetail['computation_type'],
                        'base_amount' => 0,
                        'tax_amount' => 0
                    ];
                }

                $orderTaxSummary[$taxId]['base_amount'] += $taxDetail['base_amount'];
                $orderTaxSummary[$taxId]['tax_amount'] += $taxDetail['tax_amount'];
            }

            // Add calculated values to line for return
            $line['tax_calculation'] = $lineTaxCalc;
        }

        return [
            'lines' => $lines,
            'order_subtotal' => $orderSubtotal,
            'order_tax_amount' => $orderTotalTax,
            'order_total' => $orderTotal,
            'tax_summary' => array_values($orderTaxSummary)
        ];
    }

    /**
     * Get tax breakdown for display purposes
     */
    public function getTaxBreakdown($taxDetails)
    {
        $breakdown = [];
        $groupedTaxes = collect($taxDetails)->groupBy('tax_name');

        foreach ($groupedTaxes as $taxName => $taxes) {
            $totalBase = $taxes->sum('base_amount');
            $totalTax = $taxes->sum('tax_amount');
            $avgRate = $taxes->avg('tax_rate');

            $breakdown[] = [
                'tax_name' => $taxName,
                'base_amount' => $totalBase,
                'tax_rate' => $avgRate,
                'tax_amount' => $totalTax
            ];
        }

        return $breakdown;
    }

    /**
     * Calculate price excluding tax when price includes tax
     */
    public function calculatePriceExcludingTax($includingPrice, $taxes)
    {
        if (!$taxes instanceof Collection) {
            $taxes = collect($taxes);
        }

        $excludingPrice = $includingPrice;
        $totalTaxRate = 0;

        // Calculate combined tax rate for included taxes
        foreach ($taxes->where('included_in_price', true) as $tax) {
            if ($tax->computation_type === 'percentage') {
                $totalTaxRate += $tax->rate;
            } else {
                // For fixed amount taxes, subtract directly
                $excludingPrice -= $tax->amount;
            }
        }

        // Calculate price excluding percentage taxes
        if ($totalTaxRate > 0) {
            $excludingPrice = $excludingPrice / (1 + ($totalTaxRate / 100));
        }

        return $excludingPrice;
    }

    /**
     * Convert tax-inclusive price to tax-exclusive with separate tax amount
     */
    public function splitInclusivePrice($inclusivePrice, $taxes)
    {
        $exclusivePrice = $this->calculatePriceExcludingTax($inclusivePrice, $taxes);
        $taxAmount = $inclusivePrice - $exclusivePrice;

        return [
            'exclusive_price' => $exclusivePrice,
            'tax_amount' => $taxAmount,
            'inclusive_price' => $inclusivePrice
        ];
    }
}