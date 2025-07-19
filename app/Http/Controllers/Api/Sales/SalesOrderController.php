<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SOLine;
use App\Models\Sales\Customer;
use App\Models\Item;
use App\Models\UnitOfMeasure;
use App\Models\CurrencyRate;
use App\Services\TaxCalculationService; // ⭐ ADD THIS
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Facades\Log;

class SalesOrderController extends Controller
{
    protected $taxCalculationService; // ⭐ ADD THIS

    // ⭐ ADD CONSTRUCTOR
    public function __construct(TaxCalculationService $taxCalculationService)
    {
        $this->taxCalculationService = $taxCalculationService;
    }

    /**
     * Generate the next sales order number with format SO-yy-000000
     *
     * @return string
     */
    private function generateSalesOrderNumber()
    {
        $currentYear = date('y'); // Get 2-digit year
        $prefix = "SO-{$currentYear}-";

        // Get the latest sales order number for current year
        $latestSalesOrder = SalesOrder::where('so_number', 'like', $prefix . '%')
            ->orderBy('so_number', 'desc')
            ->lockForUpdate() // ⭐ ADD LOCK TO PREVENT DUPLICATE NUMBERS
            ->first();

        if ($latestSalesOrder) {
            // Extract the sequence number from the latest sales order
            $lastNumber = intval(substr($latestSalesOrder->so_number, -6));
            $nextNumber = $lastNumber + 1;
        } else {
            // First sales order of the year
            $nextNumber = 1;
        }

        // Format with 6 digits, padded with zeros
        return $prefix . sprintf('%06d', $nextNumber);
    }

    /**
     * Get the next sales order number (for preview)
     *
     * @return \Illuminate\Http\Response
     */
    public function getNextSalesOrderNumber()
    {
        return response()->json([
            'next_so_number' => $this->generateSalesOrderNumber()
        ]);
    }

    /**
     * Display a listing of sales orders with search and filters
     */
    public function index(Request $request)
    {
        try {
            $query = SalesOrder::with(['customer', 'salesQuotation', 'deliveries', 'salesInvoices']);

            // Search functionality - includes po_number_customer
            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('so_number', 'like', "%{$search}%")
                        ->orWhere('po_number_customer', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($q2) use ($search) {
                            $q2->where('customer_name', 'like', "%{$search}%") // ⭐ FIXED FIELD NAME
                                ->orWhere('customer_code', 'like', "%{$search}%");
                        });
                });
            }

            // Status filter
            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->status);
            }

            // Customer filter
            if ($request->has('customer_id') && $request->customer_id !== '') {
                $query->where('customer_id', $request->customer_id);
            }

            // Date range filters
            if ($request->has('date_range') && $request->date_range !== 'all') {
                switch ($request->date_range) {
                    case 'today':
                        $query->whereDate('so_date', today());
                        break;
                    case 'week':
                        $query->whereBetween('so_date', [now()->startOfWeek(), now()->endOfWeek()]);
                        break;
                    case 'month':
                        $query->whereMonth('so_date', now()->month)
                            ->whereYear('so_date', now()->year);
                        break;
                }
            }

            // Custom date range
            if ($request->has('start_date') && $request->start_date !== '') {
                $query->where('so_date', '>=', $request->start_date);
            }

            if ($request->has('end_date') && $request->end_date !== '') {
                $query->where('so_date', '<=', $request->end_date);
            }

            // Sorting
            $sortField = $request->get('sort_field', 'so_id');
            $sortDirection = $request->get('sort_direction', 'desc');

            // Map frontend sort fields to database fields
            $sortFieldMap = [
                'so_number' => 'so_number',
                'po_number_customer' => 'po_number_customer',
                'so_date' => 'so_date',
                'expected_delivery' => 'expected_delivery',
                'status' => 'status',
                'total_amount' => 'total_amount'
            ];

            if (array_key_exists($sortField, $sortFieldMap)) {
                $query->orderBy($sortFieldMap[$sortField], $sortDirection);
            } else {
                $query->orderBy('so_id', 'desc');
            }

            // Pagination
            $perPage = $request->get('per_page', 10);
            $orders = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $orders->items(),
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching sales orders: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch sales orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created sales order with AUTOMATIC TAX CALCULATION
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Removed so_number from validation since it's auto-generated
            'po_number_customer' => 'nullable|string|max:100',
            'so_date' => 'required|date',
            'customer_id' => 'required|exists:Customer,customer_id',
            'quotation_id' => 'nullable|exists:SalesQuotation,quotation_id',
            'payment_terms' => 'nullable|string',
            'delivery_terms' => 'nullable|string',
            'expected_delivery' => 'nullable|date',
            'status' => 'required|string|max:50',
            'currency_code' => 'nullable|string|size:3',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:Item,item_id', // ⭐ FIXED TABLE NAME
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.quantity' => 'required|numeric|min:0',
            'lines.*.uom_id' => 'required|exists:UnitOfMeasure,uom_id', // ⭐ FIXED TABLE NAME
            'lines.*.delivery_date' => 'nullable|date|after_or_equal:so_date',
            'lines.*.discount' => 'nullable|numeric|min:0',
            // ⭐ REMOVED TAX VALIDATION - WILL BE CALCULATED AUTOMATICALLY
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Get the customer to check for preferred currency and taxes
            $customer = Customer::with('taxes')->find($request->customer_id); // ⭐ LOAD TAXES

            // Determine currency to use
            $currencyCode = $request->currency_code ?? $customer->preferred_currency ?? config('app.base_currency', 'USD');
            $baseCurrency = config('app.base_currency', 'USD');

            // Get exchange rate
            $exchangeRate = 1.0;

            if ($currencyCode !== $baseCurrency) {
                $rate = CurrencyRate::getCurrentRate($currencyCode, $baseCurrency, $request->so_date);

                if (!$rate) {
                    // Try reverse rate
                    $reverseRate = CurrencyRate::getCurrentRate($baseCurrency, $currencyCode, $request->so_date);
                    if ($reverseRate) {
                        $exchangeRate = 1 / $reverseRate;
                    } else {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'No exchange rate found for the specified currency on the sales date'
                        ], 422);
                    }
                } else {
                    $exchangeRate = $rate;
                }
            }

            // Generate the sales order number automatically
            $soNumber = $this->generateSalesOrderNumber();

            // Create sales order
            $salesOrder = SalesOrder::create([
                'so_number' => $soNumber,
                'po_number_customer' => $request->po_number_customer,
                'so_date' => $request->so_date,
                'customer_id' => $request->customer_id,
                'quotation_id' => $request->quotation_id,
                'payment_terms' => $request->payment_terms,
                'delivery_terms' => $request->delivery_terms,
                'expected_delivery' => $request->expected_delivery,
                'status' => $request->status,
                'total_amount' => 0,
                'tax_amount' => 0,
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'base_currency' => $baseCurrency,
                'base_currency_total' => 0,
                'base_currency_tax' => 0
            ]);

            // ⭐ CALCULATE TAXES AUTOMATICALLY USING TAX SERVICE
            $orderTaxCalculation = $this->taxCalculationService->calculateOrderTaxes(
                $request->lines,
                $request->customer_id,
                null,
                'sales'
            );

            $totalAmount = 0;
            $taxAmount = 0;

            // Create order lines with calculated taxes
            foreach ($orderTaxCalculation['lines'] as $lineData) {
                $item = Item::with('salesTaxes')->find($lineData['item_id']);
                $unitPrice = $lineData['unit_price'] ?? $item->sale_price ?? 0;
                $quantity = $lineData['quantity'];
                $discount = $lineData['discount'] ?? 0;
                $deliveryDate = $lineData['delivery_date'] ?? null;

                $taxCalc = $lineData['tax_calculation']; // ⭐ GET TAX CALCULATION RESULT

                $soLine = SOLine::create([
                    'so_id' => $salesOrder->so_id,
                    'item_id' => $lineData['item_id'],
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'uom_id' => $lineData['uom_id'],
                    'delivery_date' => $deliveryDate,
                    'discount' => $discount,
                    'tax' => $taxCalc['total_tax_amount'], // ⭐ AUTO CALCULATED TAX
                    'tax_rate' => $taxCalc['combined_tax_rate'] ?? 0, // ⭐ NEW FIELD
                    'applied_taxes' => $taxCalc['tax_details'] ?? [], // ⭐ NEW FIELD
                    'subtotal_before_tax' => $taxCalc['subtotal_after_discount'] ?? ($unitPrice * $quantity - $discount), // ⭐ NEW FIELD
                    'tax_inclusive_amount' => $taxCalc['price_includes_tax'] ? $taxCalc['line_total'] : 0, // ⭐ NEW FIELD
                    'subtotal' => $taxCalc['subtotal'] ?? ($unitPrice * $quantity),
                    'total' => $taxCalc['line_total'] ?? ($unitPrice * $quantity - $discount + $taxCalc['total_tax_amount']),
                    // ⭐ BASE CURRENCY CALCULATIONS
                    'base_currency_unit_price' => $unitPrice * $exchangeRate,
                    'base_currency_subtotal' => ($taxCalc['subtotal'] ?? ($unitPrice * $quantity)) * $exchangeRate,
                    'base_currency_discount' => $discount * $exchangeRate,
                    'base_currency_tax' => $taxCalc['total_tax_amount'] * $exchangeRate,
                    'base_currency_total' => ($taxCalc['line_total'] ?? ($unitPrice * $quantity - $discount + $taxCalc['total_tax_amount'])) * $exchangeRate
                ]);

                $totalAmount += $taxCalc['line_total'] ?? ($unitPrice * $quantity - $discount + $taxCalc['total_tax_amount']);
                $taxAmount += $taxCalc['total_tax_amount'];
            }

            // Update order totals
            $salesOrder->update([
                'total_amount' => $totalAmount,
                'tax_amount' => $taxAmount,
                'base_currency_total' => $totalAmount * $exchangeRate,
                'base_currency_tax' => $taxAmount * $exchangeRate
            ]);

            DB::commit();

            Log::info('Sales order created with automatic tax calculation', [
                'so_number' => $soNumber,
                'customer_id' => $request->customer_id,
                'total_amount' => $totalAmount,
                'tax_amount' => $taxAmount
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Sales order created successfully with automatic tax calculation', // ⭐ UPDATED MESSAGE
                'data' => $salesOrder->load(['customer', 'salesOrderLines.item']),
                'tax_summary' => $orderTaxCalculation['tax_summary'] ?? [] // ⭐ ADD TAX SUMMARY
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating sales order: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create sales order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified sales order
     */
    public function show($id)
    {
        try {
            $order = SalesOrder::with([
                'customer',
                'salesQuotation',
                'salesOrderLines.item',
                'salesOrderLines.unitOfMeasure',
                'salesOrderLines.deliveryLines',
                'deliveries',
                'salesInvoices'
            ])->find($id);

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sales order not found'
                ], 404);
            }

            // ⭐ ADD TAX BREAKDOWN CALCULATION
            $taxBreakdown = [];
            foreach ($order->salesOrderLines as $line) {
                if ($line->applied_taxes && is_array($line->applied_taxes)) {
                    foreach ($line->applied_taxes as $tax) {
                        $taxName = $tax['tax_name'] ?? 'Unknown Tax';
                        if (!isset($taxBreakdown[$taxName])) {
                            $taxBreakdown[$taxName] = [
                                'tax_name' => $taxName,
                                'tax_rate' => $tax['tax_rate'] ?? 0,
                                'base_amount' => 0,
                                'tax_amount' => 0
                            ];
                        }
                        $taxBreakdown[$taxName]['base_amount'] += $tax['base_amount'] ?? 0;
                        $taxBreakdown[$taxName]['tax_amount'] += $tax['tax_amount'] ?? 0;
                    }
                }
            }
            $order->tax_breakdown = array_values($taxBreakdown); // ⭐ ADD TAX BREAKDOWN TO RESPONSE

            return response()->json([
                'status' => 'success',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching sales order: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch sales order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified sales order with AUTOMATIC TAX RECALCULATION
     */
    public function update(Request $request, $id)
    {
        $order = SalesOrder::find($id);

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sales order not found'
            ], 404);
        }

        // Check if order can be updated
        if (in_array($order->status, ['Delivered', 'Invoiced', 'Closed'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot update a ' . $order->status . ' sales order'
            ], 400);
        }

        $validatorRules = [
            // so_number should not be updated, so removed from validation
            'po_number_customer' => 'nullable|string|max:100',
            'so_date' => 'required|date',
            'customer_id' => 'required|exists:Customer,customer_id',
            'quotation_id' => 'nullable|exists:SalesQuotation,quotation_id',
            'payment_terms' => 'nullable|string',
            'delivery_terms' => 'nullable|string',
            'expected_delivery' => 'nullable|date',
            'status' => 'required|string|max:50',
            'currency_code' => 'nullable|string|size:3',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:Item,item_id', // ⭐ FIXED TABLE NAME
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.quantity' => 'required|numeric|min:0',
            'lines.*.uom_id' => 'required|exists:UnitOfMeasure,uom_id', // ⭐ FIXED TABLE NAME
            'lines.*.delivery_date' => 'nullable|date|after_or_equal:so_date',
            'lines.*.discount' => 'nullable|numeric|min:0',
            // ⭐ REMOVED TAX VALIDATION - WILL BE CALCULATED AUTOMATICALLY
        ];

        $validator = Validator::make($request->all(), $validatorRules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Get customer and currency info
            $customer = Customer::with('taxes')->find($request->customer_id); // ⭐ LOAD TAXES
            $currencyCode = $request->currency_code ?? $customer->preferred_currency ?? config('app.base_currency', 'USD');
            $baseCurrency = config('app.base_currency', 'USD');

            // Get exchange rate
            $exchangeRate = 1.0;

            if ($currencyCode !== $baseCurrency) {
                $rate = CurrencyRate::getCurrentRate($currencyCode, $baseCurrency, $request->so_date);

                if (!$rate) {
                    $reverseRate = CurrencyRate::getCurrentRate($baseCurrency, $currencyCode, $request->so_date);
                    if ($reverseRate) {
                        $exchangeRate = 1 / $reverseRate;
                    } else {
                        DB::rollBack();
                        return response()->json([
                            'message' => 'No exchange rate found for the specified currency on the sales date'
                        ], 422);
                    }
                } else {
                    $exchangeRate = $rate;
                }
            }

            // Update main order fields (excluding so_number)
            $order->update([
                'po_number_customer' => $request->po_number_customer,
                'so_date' => $request->so_date,
                'customer_id' => $request->customer_id,
                'quotation_id' => $request->quotation_id,
                'payment_terms' => $request->payment_terms,
                'delivery_terms' => $request->delivery_terms,
                'expected_delivery' => $request->expected_delivery,
                'status' => $request->status,
                'currency_code' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'base_currency' => $baseCurrency
            ]);

            // Delete existing lines and create new ones
            $order->salesOrderLines()->delete();

            // ⭐ RECALCULATE TAXES AUTOMATICALLY USING TAX SERVICE
            $orderTaxCalculation = $this->taxCalculationService->calculateOrderTaxes(
                $request->lines,
                $request->customer_id,
                null,
                'sales'
            );

            $totalAmount = 0;
            $taxAmount = 0;

            // Create new lines with calculated taxes
            foreach ($orderTaxCalculation['lines'] as $lineData) {
                $item = Item::with('salesTaxes')->find($lineData['item_id']);
                $unitPrice = $lineData['unit_price'] ?? $item->sale_price ?? 0;
                $quantity = $lineData['quantity'];
                $discount = $lineData['discount'] ?? 0;
                $deliveryDate = $lineData['delivery_date'] ?? null;

                $taxCalc = $lineData['tax_calculation']; // ⭐ GET TAX CALCULATION RESULT

                SOLine::create([
                    'so_id' => $order->so_id,
                    'item_id' => $lineData['item_id'],
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'uom_id' => $lineData['uom_id'],
                    'delivery_date' => $deliveryDate,
                    'discount' => $discount,
                    'tax' => $taxCalc['total_tax_amount'], // ⭐ AUTO CALCULATED TAX
                    'tax_rate' => $taxCalc['combined_tax_rate'] ?? 0, // ⭐ NEW FIELD
                    'applied_taxes' => $taxCalc['tax_details'] ?? [], // ⭐ NEW FIELD
                    'subtotal_before_tax' => $taxCalc['subtotal_after_discount'] ?? ($unitPrice * $quantity - $discount), // ⭐ NEW FIELD
                    'tax_inclusive_amount' => $taxCalc['price_includes_tax'] ? $taxCalc['line_total'] : 0, // ⭐ NEW FIELD
                    'subtotal' => $taxCalc['subtotal'] ?? ($unitPrice * $quantity),
                    'total' => $taxCalc['line_total'] ?? ($unitPrice * $quantity - $discount + $taxCalc['total_tax_amount']),
                    // ⭐ BASE CURRENCY CALCULATIONS
                    'base_currency_unit_price' => $unitPrice * $exchangeRate,
                    'base_currency_subtotal' => ($taxCalc['subtotal'] ?? ($unitPrice * $quantity)) * $exchangeRate,
                    'base_currency_discount' => $discount * $exchangeRate,
                    'base_currency_tax' => $taxCalc['total_tax_amount'] * $exchangeRate,
                    'base_currency_total' => ($taxCalc['line_total'] ?? ($unitPrice * $quantity - $discount + $taxCalc['total_tax_amount'])) * $exchangeRate
                ]);

                $totalAmount += $taxCalc['line_total'] ?? ($unitPrice * $quantity - $discount + $taxCalc['total_tax_amount']);
                $taxAmount += $taxCalc['total_tax_amount'];
            }

            // Update order totals
            $order->update([
                'total_amount' => $totalAmount,
                'tax_amount' => $taxAmount,
                'base_currency_total' => $totalAmount * $exchangeRate,
                'base_currency_tax' => $taxAmount * $exchangeRate
            ]);

            DB::commit();

            Log::info('Sales order updated with automatic tax recalculation', [
                'so_id' => $order->so_id,
                'so_number' => $order->so_number,
                'total_amount' => $totalAmount,
                'tax_amount' => $taxAmount
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Sales order updated successfully with automatic tax recalculation', // ⭐ UPDATED MESSAGE
                'data' => $order->load(['customer', 'salesOrderLines.item']),
                'tax_summary' => $orderTaxCalculation['tax_summary'] ?? [] // ⭐ ADD TAX SUMMARY
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating sales order: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update sales order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ⭐ ADD NEW METHODS FOR TAX PREVIEW AND CONFIGURATION

    /**
     * Preview tax calculation for line items before creating/updating order
     */
    public function previewTaxCalculation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:Customer,customer_id',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:Item,item_id',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.discount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Calculate taxes for preview
            $orderTaxCalculation = $this->taxCalculationService->calculateOrderTaxes(
                $request->lines,
                $request->customer_id,
                null,
                'sales'
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Tax calculation preview generated successfully',
                'data' => [
                    'lines' => $orderTaxCalculation['lines'],
                    'order_subtotal' => $orderTaxCalculation['order_subtotal'],
                    'order_tax_amount' => $orderTaxCalculation['order_tax_amount'],
                    'order_total' => $orderTaxCalculation['order_total'],
                    'tax_summary' => $orderTaxCalculation['tax_summary']
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error calculating tax preview: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to calculate tax preview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get applicable taxes for an item and customer combination
     */
    public function getApplicableTaxes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|exists:Item,item_id',
            'customer_id' => 'required|exists:Customer,customer_id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $taxes = $this->taxCalculationService->getApplicableTaxes(
                $request->item_id,
                $request->customer_id,
                null,
                'sales'
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'item_id' => $request->item_id,
                    'customer_id' => $request->customer_id,
                    'applicable_taxes' => $taxes,
                    'combined_rate' => $taxes->sum('rate')
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting applicable taxes: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get applicable taxes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ⭐ KEEP ALL EXISTING METHODS (destroy, downloadTemplate, importFromExcel, exportToExcel, print, getStatistics)
    // The rest of your existing methods remain unchanged...

    /**
     * Remove the specified sales order
     */
    public function destroy($id)
    {
        try {
            $order = SalesOrder::with(['deliveries', 'salesInvoices'])->find($id);

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sales order not found'
                ], 404);
            }

            // Check if order can be deleted
            if ($order->deliveries->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete sales order that has deliveries'
                ], 400);
            }

            if ($order->salesInvoices->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete sales order that has invoices'
                ], 400);
            }

            DB::beginTransaction();

            // Delete order lines first
            $order->salesOrderLines()->delete();

            // Delete the order
            $order->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Sales order deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting sales order: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete sales order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download Excel template for sales order import
     */
    public function downloadTemplate()
    {
        try {
            $spreadsheet = new Spreadsheet();

            // ===== SHEET 1: SALES ORDER HEADER =====
            $headerSheet = $spreadsheet->getActiveSheet();
            $headerSheet->setTitle('Sales Orders');

            // Header columns for Sales Order (removed SO Number from template since it's auto-generated)
            $headers = [
                'A1' => 'Customer Code*',
                'B1' => 'Customer PO Number',
                'C1' => 'SO Date*',
                'D1' => 'Payment Terms',
                'E1' => 'Delivery Terms',
                'F1' => 'Expected Delivery',
                'G1' => 'Status*',
                'H1' => 'Currency Code',
                'I1' => 'Notes'
            ];

            // Apply headers
            foreach ($headers as $cell => $value) {
                $headerSheet->setCellValue($cell, $value);
            }

            // Style header row
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '366092']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ];

            $headerSheet->getStyle('A1:I1')->applyFromArray($headerStyle);

            // Set column widths
            $columnWidths = ['A' => 15, 'B' => 15, 'C' => 12, 'D' => 15, 'E' => 15, 'F' => 15, 'G' => 12, 'H' => 12, 'I' => 25];
            foreach ($columnWidths as $column => $width) {
                $headerSheet->getColumnDimension($column)->setWidth($width);
            }

            // Add sample data
            $sampleData = [
                ['CUST001', 'PO-CUSTOMER-001', '2024-01-15', 'Net 30', 'FOB Destination', '2024-02-15', 'Draft', 'USD', 'Sample sales order 1'],
                ['CUST002', 'PO-CUSTOMER-002', '2024-01-16', 'Net 60', 'FOB Origin', '2024-02-20', 'Confirmed', 'EUR', 'Sample sales order 2']
            ];

            $row = 2;
            foreach ($sampleData as $data) {
                $col = 'A';
                foreach ($data as $value) {
                    $headerSheet->setCellValue($col . $row, $value);
                    $col++;
                }
                $row++;
            }

            // ===== SHEET 2: SALES ORDER LINES =====
            $linesSheet = $spreadsheet->createSheet();
            $linesSheet->setTitle('Sales Order Lines');

            $lineHeaders = [
                'A1' => 'Customer Code*',
                'B1' => 'Item Code*',
                'C1' => 'Quantity*',
                'D1' => 'UOM Code*',
                'E1' => 'Unit Price',
                'F1' => 'Delivery Date',
                'G1' => 'Discount',
                'H1' => 'Notes' // ⭐ REMOVED TAX COLUMN - WILL BE AUTO CALCULATED
            ];

            foreach ($lineHeaders as $cell => $value) {
                $linesSheet->setCellValue($cell, $value);
            }

            $linesSheet->getStyle('A1:H1')->applyFromArray($headerStyle);

            // Set column widths for lines sheet
            $lineColumnWidths = ['A' => 15, 'B' => 15, 'C' => 10, 'D' => 10, 'E' => 12, 'F' => 12, 'G' => 10, 'H' => 25];
            foreach ($lineColumnWidths as $column => $width) {
                $linesSheet->getColumnDimension($column)->setWidth($width);
            }

            // Add sample line data
            $sampleLineData = [
                ['CUST001', 'ITEM001', 10, 'PCS', 100.00, '2024-02-01', 0, 'Line 1 - Tax will be calculated automatically'],
                ['CUST001', 'ITEM002', 5, 'KG', 50.00, '2024-02-05', 5.00, 'Line 2 - Tax will be calculated automatically'],
                ['CUST002', 'ITEM003', 20, 'PCS', 75.00, '2024-02-10', 0, 'Line 3 - Tax will be calculated automatically']
            ];

            $row = 2;
            foreach ($sampleLineData as $data) {
                $col = 'A';
                foreach ($data as $value) {
                    $linesSheet->setCellValue($col . $row, $value);
                    $col++;
                }
                $row++;
            }

            // ===== SHEET 3: INSTRUCTIONS =====
            $instructionSheet = $spreadsheet->createSheet();
            $instructionSheet->setTitle('Instructions');

            $instructions = [
                'SALES ORDER IMPORT INSTRUCTIONS',
                '',
                '1. GENERAL RULES:',
                '   - Fields marked with * are required',
                '   - Sales Order numbers will be auto-generated (SO-yy-000000 format)',
                '   - TAX WILL BE CALCULATED AUTOMATICALLY based on item and customer configuration', // ⭐ NEW INSTRUCTION
                '   - Use the exact codes from Reference Data sheet',
                '   - Date format: YYYY-MM-DD (e.g., 2024-01-15)',
                '   - Decimal numbers use dot (.) as separator',
                '',
                '2. SALES ORDERS SHEET:',
                '   - Customer Code: Must exist in system',
                '   - Customer PO Number: Optional field for customer\'s purchase order reference',
                '   - Status: Draft, Confirmed, Processing, Shipped, Delivered, Invoiced, Closed',
                '   - Currency Code: Use standard 3-letter codes (USD, EUR, etc.)',
                '',
                '3. SALES ORDER LINES SHEET:',
                '   - Customer Code: Must match exactly with Customer Code in Sales Orders sheet',
                '   - Item Code: Must exist and be sellable',
                '   - UOM Code: Must exist in system',
                '   - Unit Price: If empty, system will use default sale price',
                '   - Delivery Date: Optional, format YYYY-MM-DD (must be after SO Date)',
                '   - Discount: Optional, use 0 if not applicable',
                '   - TAX: DO NOT ENTER - System will calculate automatically based on:',
                '     * Item tax configuration (if configured)',
                '     * Customer default taxes (if item has no tax configuration)',
                '',
                '4. IMPORT PROCESS:',
                '   - System will auto-generate SO Numbers (SO-yy-000000 format)',
                '   - System will calculate taxes automatically for each line',
                '   - System will first create Sales Orders from "Sales Orders" sheet',
                '   - Then add lines from "Sales Order Lines" sheet with calculated taxes',
                '   - Lines are matched to orders by Customer Code',
                '',
                '5. ERROR HANDLING:',
                '   - Invalid data will be logged with row number',
                '   - Import will continue for other valid rows',
                '   - Download error report after import for details'
            ];

            $row = 1;
            foreach ($instructions as $instruction) {
                $instructionSheet->setCellValue('A' . $row, $instruction);
                if ($row == 1) {
                    $instructionSheet->getStyle('A' . $row)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '366092']]
                    ]);
                } elseif (strpos($instruction, ':') !== false && !empty(trim($instruction))) {
                    $instructionSheet->getStyle('A' . $row)->applyFromArray([
                        'font' => ['bold' => true]
                    ]);
                }
                $row++;
            }
            $instructionSheet->getColumnDimension('A')->setWidth(80);

            // ===== SHEET 4: REFERENCE DATA =====
            $refSheet = $spreadsheet->createSheet();
            $refSheet->setTitle('Reference Data');

            // Status values
            $refSheet->setCellValue('A1', 'Status Values');
            $refSheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true]]);
            $statuses = ['Draft', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Invoiced', 'Closed'];
            $row = 2;
            foreach ($statuses as $status) {
                $refSheet->setCellValue('A' . $row, $status);
                $row++;
            }

            // Currency codes
            $refSheet->setCellValue('C1', 'Currency Codes');
            $refSheet->getStyle('C1')->applyFromArray(['font' => ['bold' => true]]);
            $currencies = ['USD', 'EUR', 'IDR', 'SGD', 'JPY', 'GBP', 'AUD'];
            $row = 2;
            foreach ($currencies as $currency) {
                $refSheet->setCellValue('C' . $row, $currency);
                $row++;
            }

            // Set active sheet back to first sheet
            $spreadsheet->setActiveSheetIndex(0);

            // Generate filename and save
            $filename = 'sales_order_import_template_' . date('Y-m-d_H-i-s') . '.xlsx';
            $tempPath = storage_path('app/temp/' . $filename);

            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($tempPath);

            return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Error generating template: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import sales orders from Excel file with AUTOMATIC TAX CALCULATION
     */
    public function importFromExcel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
            'update_existing' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $file = $request->file('file');
            $updateExisting = $request->get('update_existing', false);

            $spreadsheet = IOFactory::load($file->getPathname());

            // Get Sales Orders sheet
            $headerSheet = $spreadsheet->getSheetByName('Sales Orders');
            if (!$headerSheet) {
                return response()->json(['message' => 'Sales Orders sheet not found'], 422);
            }

            // Get Sales Order Lines sheet
            $linesSheet = $spreadsheet->getSheetByName('Sales Order Lines');
            if (!$linesSheet) {
                return response()->json(['message' => 'Sales Order Lines sheet not found'], 422);
            }

            $headerHighestRow = $headerSheet->getHighestRow();
            $linesHighestRow = $linesSheet->getHighestRow();

            if ($headerHighestRow < 2) {
                return response()->json(['message' => 'No sales order data found'], 422);
            }

            $successCount = 0;
            $errorCount = 0;
            $errors = [];
            $createdOrders = [];

            DB::beginTransaction();

            // Process Sales Orders (Headers)
            for ($row = 2; $row <= $headerHighestRow; $row++) {
                try {
                    $customerCode = trim($headerSheet->getCell('A' . $row)->getValue() ?? '');
                    $poNumberCustomer = trim($headerSheet->getCell('B' . $row)->getValue() ?? '');
                    $soDate = $headerSheet->getCell('C' . $row)->getFormattedValue();
                    $paymentTerms = trim($headerSheet->getCell('D' . $row)->getValue() ?? '');
                    $deliveryTerms = trim($headerSheet->getCell('E' . $row)->getValue() ?? '');
                    $expectedDelivery = $headerSheet->getCell('F' . $row)->getFormattedValue();
                    $status = trim($headerSheet->getCell('G' . $row)->getValue() ?? 'Draft');
                    $currencyCode = trim($headerSheet->getCell('H' . $row)->getValue() ?? 'USD');

                    // Skip empty rows
                    if (empty($customerCode)) {
                        continue;
                    }

                    // Validate required fields
                    if (empty($customerCode) || empty($soDate)) {
                        $errors[] = "Row {$row}: Missing required fields (Customer Code or SO Date)";
                        $errorCount++;
                        continue;
                    }

                    // Find customer
                    $customer = Customer::where('customer_code', $customerCode)->first();
                    if (!$customer) {
                        $errors[] = "Row {$row}: Customer with code '{$customerCode}' not found";
                        $errorCount++;
                        continue;
                    }

                    // Validate and convert dates
                    try {
                        $soDateFormatted = date('Y-m-d', strtotime($soDate));
                        $expectedDeliveryFormatted = !empty($expectedDelivery) ? date('Y-m-d', strtotime($expectedDelivery)) : null;
                    } catch (\Exception $e) {
                        $errors[] = "Row {$row}: Invalid date format";
                        $errorCount++;
                        continue;
                    }

                    // Get exchange rate
                    $baseCurrency = config('app.base_currency', 'USD');
                    $exchangeRate = 1.0;

                    if ($currencyCode !== $baseCurrency) {
                        $rate = CurrencyRate::getCurrentRate($currencyCode, $baseCurrency, $soDateFormatted);
                        if (!$rate) {
                            $errors[] = "Row {$row}: No exchange rate found for {$currencyCode} to {$baseCurrency}";
                            $errorCount++;
                            continue;
                        }
                        $exchangeRate = $rate;
                    }

                    // Generate SO Number automatically
                    $soNumber = $this->generateSalesOrderNumber();

                    // Create Sales Order
                    $salesOrderData = [
                        'so_number' => $soNumber,
                        'po_number_customer' => $poNumberCustomer,
                        'so_date' => $soDateFormatted,
                        'customer_id' => $customer->customer_id,
                        'payment_terms' => $paymentTerms,
                        'delivery_terms' => $deliveryTerms,
                        'expected_delivery' => $expectedDeliveryFormatted,
                        'status' => $status,
                        'currency_code' => $currencyCode,
                        'exchange_rate' => $exchangeRate,
                        'base_currency' => $baseCurrency,
                        'total_amount' => 0,
                        'tax_amount' => 0,
                        'base_currency_total' => 0,
                        'base_currency_tax' => 0
                    ];

                    $salesOrder = SalesOrder::create($salesOrderData);
                    $createdOrders[$customerCode] = $salesOrder;
                    $successCount++;
                } catch (\Exception $e) {
                    $errors[] = "Row {$row}: " . $e->getMessage();
                    $errorCount++;
                }
            }

            // Process Sales Order Lines with AUTOMATIC TAX CALCULATION
            if ($linesHighestRow >= 2) {
                // Group lines by customer for batch tax calculation
                $linesByCustomer = [];
                for ($row = 2; $row <= $linesHighestRow; $row++) {
                    $customerCode = trim($linesSheet->getCell('A' . $row)->getValue() ?? '');
                    $itemCode = trim($linesSheet->getCell('B' . $row)->getValue() ?? '');
                    $quantity = $linesSheet->getCell('C' . $row)->getValue();
                    $uomCode = trim($linesSheet->getCell('D' . $row)->getValue() ?? '');
                    $unitPrice = $linesSheet->getCell('E' . $row)->getValue();
                    $deliveryDate = $linesSheet->getCell('F' . $row)->getFormattedValue();
                    $discount = $linesSheet->getCell('G' . $row)->getValue() ?? 0;

                    if (!empty($customerCode) && !empty($itemCode)) {
                        if (!isset($linesByCustomer[$customerCode])) {
                            $linesByCustomer[$customerCode] = [];
                        }
                        $linesByCustomer[$customerCode][] = [
                            'row' => $row,
                            'item_code' => $itemCode,
                            'quantity' => $quantity,
                            'uom_code' => $uomCode,
                            'unit_price' => $unitPrice,
                            'delivery_date' => $deliveryDate,
                            'discount' => $discount
                        ];
                    }
                }

                // Process each customer's lines with tax calculation
                foreach ($linesByCustomer as $customerCode => $customerLines) {
                    if (!isset($createdOrders[$customerCode])) {
                        continue;
                    }

                    $salesOrder = $createdOrders[$customerCode];
                    $validLines = [];

                    // Validate all lines first
                    foreach ($customerLines as $lineData) {
                        $row = $lineData['row'];
                        try {
                            // Find item
                            $item = Item::where('item_code', $lineData['item_code'])->first();
                            if (!$item) {
                                $errors[] = "Line Row {$row}: Item with code '{$lineData['item_code']}' not found";
                                $errorCount++;
                                continue;
                            }

                            // Find UOM
                            $uom = UnitOfMeasure::where('uom_code', $lineData['uom_code'])
                                                ->orWhere('uom_name', $lineData['uom_code'])
                                                ->first();
                            if (!$uom) {
                                $errors[] = "Line Row {$row}: UOM with code '{$lineData['uom_code']}' not found";
                                $errorCount++;
                                continue;
                            }

                            $validLines[] = [
                                'item_id' => $item->item_id,
                                'quantity' => $lineData['quantity'],
                                'unit_price' => !empty($lineData['unit_price']) ? $lineData['unit_price'] : ($item->sale_price ?? 0),
                                'uom_id' => $uom->uom_id,
                                'delivery_date' => $lineData['delivery_date'],
                                'discount' => $lineData['discount'],
                                'row' => $row
                            ];
                        } catch (\Exception $e) {
                            $errors[] = "Line Row {$row}: " . $e->getMessage();
                            $errorCount++;
                        }
                    }

                    // Calculate taxes for valid lines using tax service
                    if (!empty($validLines)) {
                        try {
                            $orderTaxCalculation = $this->taxCalculationService->calculateOrderTaxes(
                                $validLines,
                                $salesOrder->customer_id,
                                null,
                                'sales'
                            );

                            // Create order lines with calculated taxes
                            foreach ($orderTaxCalculation['lines'] as $lineData) {
                                $taxCalc = $lineData['tax_calculation'];

                                // Format delivery date
                                $deliveryDateFormatted = null;
                                if (!empty($lineData['delivery_date'])) {
                                    try {
                                        $deliveryDateFormatted = date('Y-m-d', strtotime($lineData['delivery_date']));
                                        if ($deliveryDateFormatted < $salesOrder->so_date) {
                                            $errors[] = "Line Row {$lineData['row']}: Delivery date cannot be before order date";
                                            $errorCount++;
                                            continue;
                                        }
                                    } catch (\Exception $e) {
                                        $errors[] = "Line Row {$lineData['row']}: Invalid delivery date format";
                                        $errorCount++;
                                        continue;
                                    }
                                }

                                SOLine::create([
                                    'so_id' => $salesOrder->so_id,
                                    'item_id' => $lineData['item_id'],
                                    'unit_price' => $lineData['unit_price'],
                                    'quantity' => $lineData['quantity'],
                                    'uom_id' => $lineData['uom_id'],
                                    'delivery_date' => $deliveryDateFormatted,
                                    'discount' => $lineData['discount'],
                                    'tax' => $taxCalc['total_tax_amount'], // ⭐ AUTO CALCULATED TAX
                                    'tax_rate' => $taxCalc['combined_tax_rate'] ?? 0,
                                    'applied_taxes' => $taxCalc['tax_details'] ?? [],
                                    'subtotal_before_tax' => $taxCalc['subtotal_after_discount'] ?? 0,
                                    'tax_inclusive_amount' => $taxCalc['price_includes_tax'] ? $taxCalc['line_total'] : 0,
                                    'subtotal' => $taxCalc['subtotal'] ?? 0,
                                    'total' => $taxCalc['line_total'] ?? 0
                                ]);
                            }

                            // Update order totals
                            $totalAmount = $orderTaxCalculation['order_total'] ?? 0;
                            $taxAmount = $orderTaxCalculation['order_tax_amount'] ?? 0;

                            $salesOrder->update([
                                'total_amount' => $totalAmount,
                                'tax_amount' => $taxAmount,
                                'base_currency_total' => $totalAmount * $salesOrder->exchange_rate,
                                'base_currency_tax' => $taxAmount * $salesOrder->exchange_rate
                            ]);
                        } catch (\Exception $e) {
                            $errors[] = "Error calculating taxes for customer {$customerCode}: " . $e->getMessage();
                            $errorCount++;
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Import completed with automatic tax calculation', // ⭐ UPDATED MESSAGE
                'summary' => [
                    'total_processed' => $successCount + $errorCount,
                    'successful' => $successCount,
                    'failed' => $errorCount,
                    'errors' => $errors
                ]
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Import failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Import failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export sales orders to Excel
     */
    public function exportToExcel(Request $request)
    {
        try {
            $query = SalesOrder::with(['customer', 'salesOrderLines.item', 'salesOrderLines.unitOfMeasure']);

            // Apply filters if provided
            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->status);
            }

            if ($request->has('customer_id') && $request->customer_id !== '') {
                $query->where('customer_id', $request->customer_id);
            }

            if ($request->has('dateFrom') && $request->dateFrom !== '') {
                $query->where('so_date', '>=', $request->dateFrom);
            }

            if ($request->has('dateTo') && $request->dateTo !== '') {
                $query->where('so_date', '<=', $request->dateTo);
            }

            $salesOrders = $query->get();

            $spreadsheet = new Spreadsheet();

            // ===== SHEET 1: SALES ORDERS =====
            $orderSheet = $spreadsheet->getActiveSheet();
            $orderSheet->setTitle('Sales Orders');

            // Headers
            $headers = [
                'A1' => 'SO Number',
                'B1' => 'Customer PO Number',
                'C1' => 'SO Date',
                'D1' => 'Customer Code',
                'E1' => 'Customer Name',
                'F1' => 'Payment Terms',
                'G1' => 'Delivery Terms',
                'H1' => 'Expected Delivery',
                'I1' => 'Status',
                'J1' => 'Currency',
                'K1' => 'Total Amount',
                'L1' => 'Tax Amount'
            ];

            foreach ($headers as $cell => $value) {
                $orderSheet->setCellValue($cell, $value);
            }

            // Style headers
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '366092']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ];
            $orderSheet->getStyle('A1:L1')->applyFromArray($headerStyle);

            // Add data
            $row = 2;
            foreach ($salesOrders as $order) {
                $orderSheet->setCellValue('A' . $row, $order->so_number);
                $orderSheet->setCellValue('B' . $row, $order->po_number_customer ?? '');
                $orderSheet->setCellValue('C' . $row, $order->so_date->format('Y-m-d'));
                $orderSheet->setCellValue('D' . $row, $order->customer->customer_code ?? '');
                $orderSheet->setCellValue('E' . $row, $order->customer->customer_name ?? ''); // ⭐ FIXED FIELD NAME
                $orderSheet->setCellValue('F' . $row, $order->payment_terms);
                $orderSheet->setCellValue('G' . $row, $order->delivery_terms);
                $orderSheet->setCellValue('H' . $row, $order->expected_delivery ? $order->expected_delivery->format('Y-m-d') : '');
                $orderSheet->setCellValue('I' . $row, $order->status);
                $orderSheet->setCellValue('J' . $row, $order->currency_code ?? 'USD');
                $orderSheet->setCellValue('K' . $row, $order->total_amount);
                $orderSheet->setCellValue('L' . $row, $order->tax_amount);
                $row++;
            }

            // Auto-size columns
            foreach (range('A', 'L') as $column) {
                $orderSheet->getColumnDimension($column)->setAutoSize(true);
            }

            // ===== SHEET 2: SALES ORDER LINES =====
            $linesSheet = $spreadsheet->createSheet();
            $linesSheet->setTitle('Sales Order Lines');

            $lineHeaders = [
                'A1' => 'SO Number',
                'B1' => 'Customer PO Number',
                'C1' => 'Item Code',
                'D1' => 'Item Name',
                'E1' => 'Quantity',
                'F1' => 'UOM',
                'G1' => 'Unit Price',
                'H1' => 'Delivery Date',
                'I1' => 'Discount',
                'J1' => 'Subtotal',
                'K1' => 'Tax Rate (%)', // ⭐ ADDED TAX RATE
                'L1' => 'Tax Amount', // ⭐ RENAMED FROM 'Tax'
                'M1' => 'Total'
            ];

            foreach ($lineHeaders as $cell => $value) {
                $linesSheet->setCellValue($cell, $value);
            }

            $linesSheet->getStyle('A1:M1')->applyFromArray($headerStyle);

            $row = 2;
            foreach ($salesOrders as $order) {
                foreach ($order->salesOrderLines as $line) {
                    $linesSheet->setCellValue('A' . $row, $order->so_number);
                    $linesSheet->setCellValue('B' . $row, $order->po_number_customer ?? '');
                    $linesSheet->setCellValue('C' . $row, $line->item->item_code);
                    $linesSheet->setCellValue('D' . $row, $line->item->item_name ?? ''); // ⭐ FIXED FIELD NAME
                    $linesSheet->setCellValue('E' . $row, $line->quantity);
                    $linesSheet->setCellValue('F' . $row, $line->unitOfMeasure->uom_code ?? ''); // ⭐ FIXED FIELD NAME
                    $linesSheet->setCellValue('G' . $row, $line->unit_price);
                    $linesSheet->setCellValue('H' . $row, $line->delivery_date ? \Carbon\Carbon::parse($line->delivery_date)->format('Y-m-d') : '');
                    $linesSheet->setCellValue('I' . $row, $line->discount);
                    $linesSheet->setCellValue('J' . $row, $line->subtotal);
                    $linesSheet->setCellValue('K' . $row, $line->tax_rate ?? 0); // ⭐ ADDED TAX RATE
                    $linesSheet->setCellValue('L' . $row, $line->tax);
                    $linesSheet->setCellValue('M' . $row, $line->total);
                    $row++;
                }
            }

            // Auto-size columns
            foreach (range('A', 'M') as $column) {
                $linesSheet->getColumnDimension($column)->setAutoSize(true);
            }

            // Set active sheet
            $spreadsheet->setActiveSheetIndex(0);

            // Generate filename and save
            $filename = 'sales_orders_export_' . date('Y-m-d_H-i-s') . '.xlsx';
            $tempPath = storage_path('app/temp/' . $filename);

            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($tempPath);

            return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Export failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Export failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Print sales order
     */
    public function print($id)
    {
        try {
            $order = SalesOrder::with([
                'customer',
                'salesOrderLines.item',
                'salesOrderLines.unitOfMeasure'
            ])->find($id);

            if (!$order) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sales order not found'
                ], 404);
            }

            // Return view for printing
            return view('sales.orders.print', compact('order'));
        } catch (\Exception $e) {
            Log::error('Error printing sales order: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to print sales order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales order statistics
     */
    public function getStatistics(Request $request)
    {
        try {
            $query = SalesOrder::query();

            // Date range filter
            if ($request->has('date_from') && $request->date_from !== '') {
                $query->where('so_date', '>=', $request->date_from);
            }

            if ($request->has('date_to') && $request->date_to !== '') {
                $query->where('so_date', '<=', $request->date_to);
            }

            $statistics = [
                'total_orders' => $query->count(),
                'total_amount' => $query->sum('total_amount'),
                'total_tax_amount' => $query->sum('tax_amount'), // ⭐ ADDED TAX STATISTICS
                'by_status' => $query->groupBy('status')
                    ->selectRaw('status, count(*) as count, sum(total_amount) as total')
                    ->get(),
                'by_currency' => $query->groupBy('currency_code')
                    ->selectRaw('currency_code, count(*) as count, sum(total_amount) as total')
                    ->get(),
                'recent_orders' => SalesOrder::with('customer')
                    ->orderBy('so_date', 'desc')
                    ->limit(5)
                    ->get()
            ];

            return response()->json([
                'status' => 'success',
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting statistics: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}