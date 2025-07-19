<?php
// app/Http/Controllers/Api/TaxController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tax\Tax;
use App\Models\Tax\TaxGroup;
use App\Models\Item;
use App\Models\Sales\Customer;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TaxController extends Controller
{
    /**
     * Get all tax groups with their taxes
     */
    public function getTaxGroups()
    {
        try {
            $taxGroups = TaxGroup::with(['activeTaxes'])->where('is_active', true)->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $taxGroups
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get tax groups',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all active taxes
     */
    public function getTaxes(Request $request)
    {
        try {
            $query = Tax::with(['taxGroup'])->active();

            // Filter by type if specified
            if ($request->has('type')) {
                if ($request->type === 'sales') {
                    $query->sales();
                } elseif ($request->type === 'purchase') {
                    $query->purchase();
                }
            }

            $taxes = $query->orderBy('sequence')->orderBy('name')->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $taxes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get taxes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new tax
     */
    public function createTax(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:taxes,name',
            'description' => 'nullable|string|max:255',
            'tax_type' => 'required|in:sales,purchase,both',
            'computation_type' => 'required|in:percentage,fixed',
            'rate' => 'required_if:computation_type,percentage|numeric|min:0|max:100',
            'amount' => 'required_if:computation_type,fixed|numeric|min:0',
            'included_in_price' => 'boolean',
            'sequence' => 'nullable|integer|min:1|max:100',
            'tax_group_id' => 'nullable|exists:tax_groups,tax_group_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $tax = Tax::create([
                'name' => $request->name,
                'description' => $request->description,
                'tax_type' => $request->tax_type,
                'computation_type' => $request->computation_type,
                'rate' => $request->computation_type === 'percentage' ? $request->rate : 0,
                'amount' => $request->computation_type === 'fixed' ? $request->amount : 0,
                'included_in_price' => $request->included_in_price ?? false,
                'sequence' => $request->sequence ?? 10,
                'tax_group_id' => $request->tax_group_id,
                'is_active' => true
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Tax created successfully',
                'data' => $tax->load('taxGroup')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create tax',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign taxes to an item
     */
    public function assignItemTaxes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:Item,item_id',
            'sales_tax_ids' => 'nullable|array',
            'sales_tax_ids.*' => 'exists:taxes,tax_id',
            'purchase_tax_ids' => 'nullable|array',
            'purchase_tax_ids.*' => 'exists:taxes,tax_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $item = Item::find($request->item_id);

            // Remove existing tax assignments for this item
            $item->taxes()->detach();

            // Assign sales taxes
            if ($request->has('sales_tax_ids') && is_array($request->sales_tax_ids)) {
                foreach ($request->sales_tax_ids as $taxId) {
                    $item->addSalesTax($taxId);
                }
            }

            // Assign purchase taxes
            if ($request->has('purchase_tax_ids') && is_array($request->purchase_tax_ids)) {
                foreach ($request->purchase_tax_ids as $taxId) {
                    $item->addPurchaseTax($taxId);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Item taxes assigned successfully',
                'data' => $item->load(['salesTaxes', 'purchaseTaxes'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to assign item taxes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign default taxes to a customer
     */
    public function assignCustomerTaxes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:Customer,customer_id',
            'tax_ids' => 'required|array|min:1',
            'tax_ids.*' => 'exists:taxes,tax_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $customer = Customer::find($request->customer_id);

            // Remove existing tax assignments
            $customer->taxes()->detach();

            // Assign new taxes
            $customer->taxes()->attach($request->tax_ids);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Customer taxes assigned successfully',
                'data' => $customer->load('taxes')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to assign customer taxes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get item tax configuration
     */
    public function getItemTaxes($itemId)
    {
        try {
            $item = Item::with(['salesTaxes', 'purchaseTaxes'])->find($itemId);

            if (!$item) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Item not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'item' => $item,
                    'sales_taxes' => $item->salesTaxes,
                    'purchase_taxes' => $item->purchaseTaxes,
                    'default_sales_tax_rate' => $item->default_sales_tax_rate,
                    'default_purchase_tax_rate' => $item->default_purchase_tax_rate
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get item taxes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}