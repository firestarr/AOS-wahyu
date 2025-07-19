<!-- src/views/sales/SalesOrderForm.vue -->
<template>
    <div class="order-form">
        <div class="page-header">
            <h1>{{ isEditMode ? "Edit Order" : "Create New Order" }}</h1>
            <div class="page-actions">
                <button class="btn btn-secondary" @click="goBack">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button
                    class="btn btn-primary"
                    @click="saveOrder"
                    :disabled="isSubmitting"
                >
                    <i class="fas fa-save"></i>
                    {{ isSubmitting ? "Saving..." : "Save" }}
                </button>
            </div>
        </div>

        <div v-if="error" class="alert alert-danger">
            {{ error }}
        </div>

        <div class="form-container">
            <div class="form-card">
                <div class="card-header">
                    <h2>Order Information</h2>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <!-- Sales Order Number - Show readonly in edit mode -->
                        <div class="form-group" v-if="isEditMode">
                            <label for="so_number">Order Number*</label>
                            <input
                                type="text"
                                id="so_number"
                                v-model="form.so_number"
                                required
                                readonly
                                class="form-control readonly"
                            />
                            <small class="text-muted">
                                Auto-generated order number (cannot be changed)
                            </small>
                        </div>

                        <!-- Show next number preview in create mode -->
                        <div class="form-group" v-if="!isEditMode">
                            <label for="so_number">Order Number*</label>
                            <div class="form-control-static">
                                <span class="badge badge-info">{{ nextSoNumber || 'Loading...' }}</span>
                            </div>
                            <small class="text-muted">
                                Auto-generated number (will be assigned when saved)
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="po_number_customer">Customer PO Number</label>
                            <input
                                type="text"
                                id="po_number_customer"
                                v-model="form.po_number_customer"
                                placeholder="Enter customer's PO number"
                                maxlength="100"
                            />
                            <small class="text-muted">
                                Customer's Purchase Order number (optional)
                            </small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="so_date">Order Date*</label>
                            <input
                                type="date"
                                id="so_date"
                                v-model="form.so_date"
                                required
                            />
                        </div>

                        <div class="form-group">
                            <label for="expected_delivery">Expected Delivery</label>
                            <input
                                type="date"
                                id="expected_delivery"
                                v-model="form.expected_delivery"
                            />
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="customer_id">Customer*</label>
                            <div class="dropdown-container">
                                <input
                                    type="text"
                                    id="customer_search"
                                    v-model="customerSearch"
                                    class="form-control"
                                    :class="{ 'is-invalid': !form.customer_id && customerSearch }"
                                    placeholder="Search for a customer..."
                                    @focus="showCustomerDropdown = true"
                                    @input="showCustomerDropdown = true"
                                />
                                <div v-if="showCustomerDropdown" class="dropdown-menu">
                                    <div
                                        v-for="customer in getFilteredCustomers(customerSearch)"
                                        :key="customer.customer_id"
                                        class="dropdown-item"
                                        @click="selectCustomer(customer)"
                                    >
                                        <div class="customer-info">
                                            <div class="customer-name">{{ customer.name }}</div>
                                            <small class="customer-code">{{ customer.customer_code }}</small>
                                        </div>
                                    </div>
                                    <div
                                        v-if="getFilteredCustomers(customerSearch).length === 0"
                                        class="dropdown-item text-muted"
                                    >
                                        No customers found
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="currency_code">Currency*</label>
                            <select
                                id="currency_code"
                                v-model="form.currency_code"
                                required
                            >
                                <option value="">Select Currency</option>
                                <option value="IDR">IDR - Indonesian Rupiah</option>
                                <option value="USD">USD - US Dollar</option>
                                <option value="EUR">EUR - Euro</option>
                                <option value="SGD">SGD - Singapore Dollar</option>
                                <option value="MYR">MYR - Malaysian Ringgit</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="payment_terms">Payment Terms</label>
                            <input
                                type="text"
                                id="payment_terms"
                                v-model="form.payment_terms"
                                placeholder="e.g., Net 30 days"
                            />
                        </div>

                        <div class="form-group">
                            <label for="delivery_terms">Delivery Terms</label>
                            <input
                                type="text"
                                id="delivery_terms"
                                v-model="form.delivery_terms"
                                placeholder="e.g., FOB, CIF, etc."
                            />
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status">Status*</label>
                        <select
                            id="status"
                            v-model="form.status"
                            required
                        >
                            <option value="draft">Draft</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="invoiced">Invoiced</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="form-card">
                <div class="card-header">
                    <h2>Order Items</h2>
                    <button
                        type="button"
                        class="btn btn-primary"
                        @click="addLine"
                    >
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
                <div class="card-body">
                    <div v-if="form.lines.length === 0" class="empty-lines">
                        <p>No items added yet. Click "Add Item" to start.</p>
                    </div>
                    
                    <div v-else class="order-lines">
                        <div v-if="form.currency_code" class="order-currency-info">
                            <i class="fas fa-info-circle"></i>
                            All prices are displayed in {{ form.currency_code }}
                        </div>

                        <div class="line-headers">
                            <div>Item</div>
                            <div>Unit Price</div>
                            <div>Quantity</div>
                            <div>UOM</div>
                            <div>Delivery Date</div>
                            <div>Discount</div>
                            <div>Tax Rate (%)</div>
                            <div>Tax Amount</div>
                            <div>Subtotal</div>
                            <div>Total</div>
                            <div>Action</div>
                        </div>

                        <div
                            v-for="(line, index) in form.lines"
                            :key="index"
                            class="order-line"
                        >
                            <!-- ITEM DROPDOWN - FIXED VERSION -->
                            <div class="line-item" data-label="Item" :data-line-index="index">
                                <div class="item-code" v-if="line.item_code" style="font-weight: bold; margin-bottom: 0.25rem;">
                                    {{ line.item_code }}
                                </div>
                                <div class="dropdown-container">
                                    <input
                                        type="text"
                                        v-model="line.itemSearch"
                                        class="form-control"
                                        placeholder="Search for an item..."
                                        @focus="showItemDropdown(index)"
                                        @input="handleItemSearch(index, $event)"
                                        @click="showItemDropdown(index)"
                                        autocomplete="off"
                                    />
                                    <div v-if="line.showDropdown" class="dropdown-menu">
                                        <!-- Show filtered items -->
                                        <div v-if="getFilteredItems(line.itemSearch || '').length > 0">
                                            <div
                                                v-for="item in getFilteredItems(line.itemSearch || '')"
                                                :key="item.item_id"
                                                @mousedown.prevent="selectItem(item, index)"
                                                class="dropdown-item"
                                                style="cursor: pointer;"
                                            >
                                                <div class="item-display">
                                                    <strong>{{ item.item_code }}</strong> - {{ item.name }}
                                                    <small v-if="item.description" class="text-muted d-block">{{ item.description }}</small>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Show "no items found" if search term exists but no results -->
                                        <div v-else-if="line.itemSearch && line.itemSearch.trim().length > 0" class="dropdown-item text-muted">
                                            No items found for "{{ line.itemSearch }}"
                                        </div>
                                        <!-- Show sample items when no search term -->
                                        <div v-else>
                                            <div class="dropdown-item text-muted">
                                                Type to search items... ({{ sellableItems.length }} items available)
                                            </div>
                                            <div
                                                v-for="item in sellableItems.slice(0, 10)"
                                                :key="'sample-' + item.item_id"
                                                @mousedown.prevent="selectItem(item, index)"
                                                class="dropdown-item"
                                                style="cursor: pointer;"
                                            >
                                                <div class="item-display">
                                                    <strong>{{ item.item_code }}</strong> - {{ item.name }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="line-item" data-label="Unit Price">
                                <input
                                    type="number"
                                    v-model.number="line.unit_price"
                                    min="0"
                                    step="0.01"
                                    @input="calculateLineTaxes(index)"
                                    placeholder="0.00"
                                />
                            </div>

                            <div class="line-item" data-label="Quantity">
                                <input
                                    type="number"
                                    v-model.number="line.quantity"
                                    min="0.0001"
                                    step="0.0001"
                                    @input="calculateLineTaxes(index)"
                                    placeholder="1"
                                />
                            </div>

                            <div class="line-item" data-label="UOM">
                                <select v-model="line.uom_id">
                                    <option value="">Select UOM</option>
                                    <option
                                        v-for="uom in unitOfMeasures"
                                        :key="uom.uom_id"
                                        :value="uom.uom_id"
                                    >
                                        {{ uom.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="line-item" data-label="Delivery Date">
                                <input
                                    type="date"
                                    v-model="line.delivery_date"
                                    class="form-control"
                                    :min="form.so_date"
                                />
                                <small class="text-muted" v-if="line.delivery_date && line.delivery_date < form.so_date">
                                    Warning: Delivery date is before order date
                                </small>
                            </div>

                            <div class="line-item" data-label="Discount">
                                <input
                                    type="number"
                                    v-model.number="line.discount"
                                    min="0"
                                    step="0.01"
                                    @input="calculateLineTaxes(index)"
                                    placeholder="0.00"
                                />
                            </div>

                            <div class="line-item" data-label="Tax Rate">
                                <span class="tax-rate-display">
                                    {{ line.tax_rate ? line.tax_rate.toFixed(2) : '0.00' }}%
                                </span>
                                <small class="text-muted d-block" v-if="line.applied_taxes && line.applied_taxes.length > 0">
                                    Auto-calculated
                                </small>
                            </div>

                            <div class="line-item" data-label="Tax Amount">
                                <span class="tax-amount-display">
                                    {{ formatCurrency(line.tax || 0) }}
                                </span>
                                <div v-if="line.applied_taxes && line.applied_taxes.length > 0" class="tax-breakdown">
                                    <small v-for="tax in line.applied_taxes" :key="tax.tax_id" class="tax-detail">
                                        {{ tax.tax_name }}: {{ tax.tax_rate }}%
                                    </small>
                                </div>
                            </div>

                            <div
                                class="line-item subtotal"
                                data-label="Subtotal"
                            >
                                {{ formatCurrency(line.subtotal || 0) }}
                            </div>

                            <div class="line-item total" data-label="Total">
                                {{ formatCurrency(line.total || 0) }}
                            </div>

                            <div class="line-item actions">
                                <button
                                    type="button"
                                    class="btn-icon delete"
                                    title="Delete Item"
                                    @click="removeLine(index)"
                                >
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Tax Summary Section -->
                        <div v-if="taxSummary && taxSummary.length > 0" class="tax-summary-section">
                            <h4>Tax Summary</h4>
                            <div class="tax-summary-grid">
                                <div v-for="taxGroup in taxSummary" :key="taxGroup.tax_name" class="tax-group">
                                    <span class="tax-name">{{ taxGroup.tax_name }} ({{ taxGroup.tax_rate }}%)</span>
                                    <span class="tax-amount">{{ formatCurrency(taxGroup.total_amount) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="order-totals">
                            <div class="total-row">
                                <div class="total-label">Subtotal:</div>
                                <div class="total-value">
                                    {{ formatCurrency(calculateSubtotal()) }}
                                </div>
                            </div>
                            <div class="total-row">
                                <div class="total-label">Total Discount:</div>
                                <div class="total-value">
                                    {{ formatCurrency(calculateTotalDiscount()) }}
                                </div>
                            </div>
                            <div class="total-row">
                                <div class="total-label">Total Tax:</div>
                                <div class="total-value">
                                    {{ formatCurrency(calculateTotalTax()) }}
                                </div>
                            </div>
                            <div class="total-row grand-total">
                                <div class="total-label">Total:</div>
                                <div class="total-value">
                                    {{ formatCurrency(calculateGrandTotal()) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import axios from "axios";

export default {
    name: "SalesOrderForm",
    setup() {
        const router = useRouter();
        const route = useRoute();

        const safeParseFloat = (value) => {
            if (value === null || value === undefined || value === '') return 0;
            const parsed = parseFloat(value);
            return isNaN(parsed) ? 0 : parsed;
        };

        // Reactive state
        const form = ref({
            so_number: "",
            po_number_customer: "",
            so_date: new Date().toISOString().substr(0, 10),
            customer_id: "",
            payment_terms: "",
            delivery_terms: "",
            expected_delivery: "",
            status: "draft",
            currency_code: "IDR",
            lines: [],
        });

        const customers = ref([]);
        const items = ref([]);
        const unitOfMeasures = ref([]);
        const isLoading = ref(false);
        const isSubmitting = ref(false);
        const error = ref("");
        const nextSoNumber = ref('');
        const taxSummary = ref([]);

        // Customer dropdown state
        const selectedCustomer = ref(null);
        const customerSearch = ref("");
        const showCustomerDropdown = ref(false);

        // Computed
        const isEditMode = computed(() => !!route.params.id);
        const sellableItems = computed(() => {
            return items.value.filter(item => item.is_sellable
            );
        });

        // Customer dropdown methods
        const getFilteredCustomers = (searchTerm) => {
            if (!searchTerm) return customers.value.slice(0, 10);
            
            const filtered = customers.value.filter(customer => 
                customer.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                customer.customer_code.toLowerCase().includes(searchTerm.toLowerCase())
            );
            
            return filtered.slice(0, 10);
        };

        const selectCustomer = (customer) => {
            selectedCustomer.value = customer;
            form.value.customer_id = customer.customer_id;
            customerSearch.value = customer.name;
            showCustomerDropdown.value = false;
            
            // Set preferred currency if available
            if (customer.preferred_currency) {
                form.value.currency_code = customer.preferred_currency;
            }
            
            console.log('Selected customer:', customer.name);
        };

        // Item dropdown methods
        const getFilteredItems = (searchTerm) => {
            if (!searchTerm) return sellableItems.value.slice(0, 10);
            
            const filtered = sellableItems.value.filter(item => 
                item.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                item.item_code.toLowerCase().includes(searchTerm.toLowerCase())
            );
            
            console.log(`🔍 Search "${searchTerm}" found ${filtered.length} items`);
            return filtered.slice(0, 50);
        };

        const showItemDropdown = (index) => {
            console.log('👆 Show dropdown for line', index);

            form.value.lines.forEach((line, i) => {
                if (i !== index) {
                    line.showDropdown = false;
                }
            });

            if (form.value.lines[index]) {
                form.value.lines[index].showDropdown = true;
                console.log('📋 Available sellable items:', sellableItems.value.length);
            }
        };

        const handleItemSearch = (index, event) => {
            const searchTerm = event.target.value;
            console.log('🔍 Search term changed:', searchTerm, 'for line', index);

            if (form.value.lines[index]) {
                form.value.lines[index].showDropdown = true;
            }
        };

        const selectItem = async (item, lineIndex) => {
            console.log('✅ Selecting item:', item.item_code, 'for line', lineIndex);

            const line = form.value.lines[lineIndex];

            line.item_id = item.item_id;
            line.item_code = item.item_code;
            line.itemSearch = `${item.item_code} - ${item.name}`;
            line.showDropdown = false;

            if (item.uom_id) {
                line.uom_id = item.uom_id;
                console.log('🏷️ UOM set from item.uom_id:', item.uom_id);
            }

            let defaultDeliveryDate = '';
            if (form.value.expected_delivery) {
                defaultDeliveryDate = form.value.expected_delivery;
            } else {
                const soDate = new Date(form.value.so_date);
                soDate.setDate(soDate.getDate() + 7);
                defaultDeliveryDate = soDate.toISOString().substr(0, 10);
            }
            line.delivery_date = defaultDeliveryDate;

            // Set unit price with fallback
            const unitPrice = item.sale_price || item.selling_price || item.unit_price || 0;
            line.unit_price = unitPrice;
            
            if (!line.quantity || line.quantity <= 0) {
                line.quantity = 1;
            }

            // Calculate taxes automatically
            await calculateLineTaxes(lineIndex);
        };

        // Tax calculation function - NEW
        const calculateLineTaxes = async (index) => {
            const line = form.value.lines[index];
            
            if (!line.item_id || !form.value.customer_id || !line.unit_price || !line.quantity) {
                // Reset tax calculations if required data is missing
                line.tax = 0;
                line.tax_rate = 0;
                line.applied_taxes = [];
                calculateLineTotals(index);
                return;
            }

            try {
                // Call backend API to get applicable taxes
                const response = await axios.get('/sales/orders/applicable-taxes', {
                    params: {
                        item_id: line.item_id,
                        customer_id: form.value.customer_id
                    }
                });

                if (response.data.status === 'success') {
                    const taxes = response.data.data.applicable_taxes;
                    
                    // Calculate tax amounts using the same logic as backend
                    const unitPrice = safeParseFloat(line.unit_price);
                    const quantity = safeParseFloat(line.quantity);
                    const discount = safeParseFloat(line.discount);
                    
                    const subtotal = unitPrice * quantity;
                    const subtotalAfterDiscount = subtotal - discount;
                    
                    let totalTaxAmount = 0;
                    let baseAmount = subtotalAfterDiscount;
                    const taxDetails = [];

                    // Sort taxes by sequence
                    const sortedTaxes = taxes.sort((a, b) => (a.sequence || 0) - (b.sequence || 0));

                    for (const tax of sortedTaxes) {
                        const taxRate = tax.rate || 0;
                        let taxAmount = 0;

                        if (tax.computation_type === 'percentage') {
                            taxAmount = (baseAmount * taxRate) / 100;
                        } else if (tax.computation_type === 'fixed') {
                            taxAmount = taxRate;
                        }

                        taxDetails.push({
                            tax_id: tax.tax_id,
                            tax_name: tax.name,
                            tax_rate: taxRate,
                            tax_amount: taxAmount,
                            included_in_price: tax.included_in_price || false
                        });

                        totalTaxAmount += taxAmount;

                        // If tax is not included in price, add it to base for next tax calculation
                        if (!tax.included_in_price) {
                            baseAmount += taxAmount;
                        }
                    }

                    // Update line with calculated tax data
                    line.tax = totalTaxAmount;
                    line.tax_rate = subtotalAfterDiscount > 0 ? (totalTaxAmount / subtotalAfterDiscount) * 100 : 0;
                    line.applied_taxes = taxDetails;
                }
            } catch (error) {
                console.error('Error calculating taxes:', error);
                // Fallback to manual tax calculation
                line.tax = safeParseFloat(line.tax) || 0;
                line.tax_rate = 0;
                line.applied_taxes = [];
            }

            calculateLineTotals(index);
        };

        // Line operations
        const addLine = () => {
            let defaultDeliveryDate = '';
            if (form.value.expected_delivery) {
                defaultDeliveryDate = form.value.expected_delivery;
            } else {
                const soDate = new Date(form.value.so_date);
                soDate.setDate(soDate.getDate() + 7);
                defaultDeliveryDate = soDate.toISOString().substr(0, 10);
            }

            form.value.lines.push({
                item_id: "",
                item_code: "",
                itemSearch: "",
                showDropdown: false,
                unit_price: 0,
                quantity: 1,
                uom_id: "",
                delivery_date: defaultDeliveryDate,
                discount: 0,
                tax: 0,
                tax_rate: 0,
                applied_taxes: [],
                subtotal: 0,
                total: 0,
            });

            console.log('➕ Added new line, total lines:', form.value.lines.length);
        };

        const removeLine = (index) => {
            form.value.lines.splice(index, 1);
            console.log('➖ Removed line', index, ', total lines:', form.value.lines.length);
        };

        const calculateLineTotals = (index) => {
            const line = form.value.lines[index];

            const unitPrice = safeParseFloat(line.unit_price);
            const quantity = safeParseFloat(line.quantity);
            const discount = safeParseFloat(line.discount);
            const tax = safeParseFloat(line.tax);

            line.subtotal = unitPrice * quantity;
            line.total = line.subtotal - discount + tax;

            line.subtotal = Math.max(0, line.subtotal);
            line.total = Math.max(0, line.total);
        };

        // Calculate totals
        const calculateSubtotal = () => {
            return form.value.lines.reduce((sum, line) => {
                return sum + safeParseFloat(line.subtotal);
            }, 0);
        };

        const calculateTotalDiscount = () => {
            return form.value.lines.reduce((sum, line) => {
                return sum + safeParseFloat(line.discount);
            }, 0);
        };

        const calculateTotalTax = () => {
            return form.value.lines.reduce((sum, line) => {
                return sum + safeParseFloat(line.tax);
            }, 0);
        };

        const calculateGrandTotal = () => {
            return form.value.lines.reduce((sum, line) => {
                return sum + safeParseFloat(line.total);
            }, 0);
        };

        // Format currency
        const formatCurrency = (value) => {
            const safeValue = safeParseFloat(value);
            const currencyCode = form.value.currency_code || "IDR";

            try {
                return new Intl.NumberFormat("id-ID", {
                    style: "currency",
                    currency: currencyCode,
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(safeValue);
            } catch (error) {
                console.error('Currency formatting error:', error);
                return `${currencyCode} ${safeValue.toFixed(2)}`;
            }
        };

        // Data loading
        const loadReferenceData = async () => {
            try {
                isLoading.value = true;
                console.log('🔄 Loading reference data...');

                const [customersRes, itemsRes, uomRes] = await Promise.all([
                    axios.get("/customers"),
                    axios.get("/items"),
                    axios.get("/unit-of-measures"),
                ]);

                customers.value = customersRes.data.data || [];
                items.value = itemsRes.data.data || [];
                unitOfMeasures.value = uomRes.data.data || [];

                console.log('✅ Reference data loaded');
                
                if (!isEditMode.value) {
                    try {
                        const nextNumberRes = await axios.get("/orders/next-number");
                        nextSoNumber.value = nextNumberRes.data.next_so_number;
                        console.log('📄 Next SO number:', nextSoNumber.value);
                    } catch (err) {
                        console.warn('Could not fetch next SO number:', err);
                    }
                }
            } catch (err) {
                console.error("Error loading reference data:", err);
                error.value = "Error loading reference data.";
            } finally {
                isLoading.value = false;
            }
        };

        const loadOrder = async () => {
            if (!isEditMode.value) return;

            try {
                isLoading.value = true;
                console.log('🔄 Loading order:', route.params.id);

                const response = await axios.get(`/sales/orders/${route.params.id}`);
                const orderData = response.data.data;

                form.value.so_number = orderData.soNumber;
                form.value.po_number_customer = orderData.poNumberCustomer || '';
                form.value.so_date = orderData.soDate ? orderData.soDate.substr(0, 10) : '';
                form.value.customer_id = orderData.customerId;
                form.value.payment_terms = orderData.paymentTerms || '';
                form.value.delivery_terms = orderData.deliveryTerms || '';
                form.value.expected_delivery = orderData.expectedDelivery ? orderData.expectedDelivery.substr(0, 10) : '';
                form.value.status = orderData.status;
                form.value.currency_code = orderData.currencyCode || 'IDR';

                if (orderData.salesOrderLines && orderData.salesOrderLines.length > 0) {
                    form.value.lines = orderData.salesOrderLines.map(line => {
                        const selectedItem = items.value.find(item => item.item_id === line.itemId);
                        return {
                            item_id: line.itemId,
                            item_code: selectedItem ? selectedItem.item_code : '',
                            itemSearch: selectedItem ? `${selectedItem.item_code} - ${selectedItem.name}` : '',
                            showDropdown: false,
                            unit_price: safeParseFloat(line.unitPrice),
                            quantity: safeParseFloat(line.quantity) || 1,
                            uom_id: line.uomId,
                            delivery_date: line.deliveryDate ? line.deliveryDate.substr(0, 10) : '',
                            discount: safeParseFloat(line.discount),
                            tax: safeParseFloat(line.tax),
                            tax_rate: safeParseFloat(line.taxRate),
                            applied_taxes: line.appliedTaxes || [],
                            subtotal: safeParseFloat(line.subtotal),
                            total: safeParseFloat(line.total),
                        };
                    });

                    form.value.lines.forEach((_, index) => {
                        calculateLineTotals(index);
                    });
                }

                if (form.value.customer_id) {
                    selectedCustomer.value = customers.value.find(
                        c => c.customer_id === form.value.customer_id
                    );
                    if (selectedCustomer.value) {
                        customerSearch.value = selectedCustomer.value.name;
                    }
                }

                console.log('✅ Order loaded successfully');
            } catch (err) {
                console.error("Error loading order:", err);
                error.value = "Error loading order.";
            } finally {
                isLoading.value = false;
            }
        };

        // Navigation
        const goBack = () => {
            router.push("/sales/orders");
        };

        // Save order with automatic tax calculation
        const saveOrder = async () => {
            if (
                !form.value.so_date ||
                !form.value.customer_id ||
                !form.value.currency_code
            ) {
                error.value = "Please fill in all required fields.";
                return;
            }

            if (form.value.lines.length === 0) {
                error.value = "Order must have at least 1 item.";
                return;
            }

            for (let i = 0; i < form.value.lines.length; i++) {
                const line = form.value.lines[i];
                if (!line.item_id || !line.quantity || !line.uom_id) {
                    error.value = `Line ${i + 1}: Please fill in all required fields.`;
                    return;
                }
            }

            isSubmitting.value = true;
            error.value = "";

            try {
                const orderData = {
                    so_date: form.value.so_date,
                    po_number_customer: form.value.po_number_customer,
                    customer_id: form.value.customer_id,
                    payment_terms: form.value.payment_terms,
                    delivery_terms: form.value.delivery_terms,
                    expected_delivery: form.value.expected_delivery,
                    status: form.value.status,
                    currency_code: form.value.currency_code,
                    lines: form.value.lines.map(line => ({
                        item_id: line.item_id,
                        unit_price: line.unit_price,
                        quantity: line.quantity,
                        uom_id: line.uom_id,
                        delivery_date: line.delivery_date,
                        discount: line.discount,
                        // Tax will be calculated automatically by backend
                    }))
                };

                let response;
                if (isEditMode.value) {
                    response = await axios.put(`/sales/orders/${route.params.id}`, orderData);
                } else {
                    response = await axios.post("/sales/orders", orderData);
                }

                if (response.data.status === 'success') {
                    console.log('✅ Order saved successfully');
                    
                    // Update tax summary if provided
                    if (response.data.tax_summary) {
                        taxSummary.value = response.data.tax_summary;
                    }
                    
                    router.push("/sales/orders");
                } else {
                    throw new Error(response.data.message || 'Unknown error occurred');
                }
            } catch (err) {
                console.error("Error saving order:", err);
                if (err.response?.data?.errors) {
                    const errors = err.response.data.errors;
                    const errorMessages = Object.values(errors).flat();
                    error.value = errorMessages.join(", ");
                } else {
                    error.value = err.response?.data?.message || "Error saving order.";
                }
            } finally {
                isSubmitting.value = false;
            }
        };

        // Setup click outside handler
        const setupClickOutsideHandler = () => {
            document.addEventListener('click', (e) => {
                // Handle customer dropdown
                if (!e.target.closest('.dropdown-container') || e.target.closest('#customer_search')) {
                    const customerContainer = e.target.closest('.dropdown-container');
                    if (!customerContainer || customerContainer.querySelector('#customer_search')) {
                        if (!e.target.closest('.dropdown-menu')) {
                            showCustomerDropdown.value = false;
                        }
                    }
                }

                // Handle item dropdowns
                form.value.lines.forEach((line, index) => {
                    const lineContainer = e.target.closest(`[data-line-index="${index}"]`);
                    if (!lineContainer && !e.target.closest('.dropdown-menu')) {
                        line.showDropdown = false;
                    }
                });
            });
        };

        // Component initialization
        onMounted(async () => {
            console.log('🚀 Component mounted, initializing...');

            setupClickOutsideHandler();

            try {
                await loadReferenceData();
                await loadOrder();
                console.log('✅ Component initialization complete');
            } catch (error) {
                console.error('❌ Error during initialization:', error);
            }
        });

        return {
            form,
            customers,
            items,
            unitOfMeasures,
            isLoading,
            isSubmitting,
            error,
            isEditMode,
            sellableItems,
            selectedCustomer,
            customerSearch,
            showCustomerDropdown,
            nextSoNumber,
            taxSummary,
            safeParseFloat,
            getFilteredCustomers,
            selectCustomer,
            getFilteredItems,
            showItemDropdown,
            handleItemSearch,
            selectItem,
            addLine,
            removeLine,
            calculateLineTaxes,
            calculateLineTotals,
            calculateSubtotal,
            calculateTotalDiscount,
            calculateTotalTax,
            calculateGrandTotal,
            formatCurrency,
            goBack,
            saveOrder,
        };
    },
};
</script>

<style scoped>
.order-form {
    padding: 1rem 0;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.page-header h1 {
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
    color: #1e293b;
}

.page-actions {
    display: flex;
    gap: 0.75rem;
}

.alert {
    padding: 1rem;
    border-radius: 0.375rem;
    margin-bottom: 1.5rem;
}

.alert-danger {
    background-color: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.form-container {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form-card {
    background-color: white;
    border-radius: 0.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    background-color: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
}

.card-header h2 {
    font-size: 1.125rem;
    font-weight: 600;
    margin: 0;
    color: #1e293b;
}

.card-body {
    padding: 1.5rem;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #334155;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.625rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
    font-size: 0.875rem;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: 2px solid #2563eb;
    outline-offset: 1px;
}

.form-group input.readonly {
    background-color: #f8fafc;
    cursor: not-allowed;
}

.badge {
    display: inline-block;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.375rem;
}

.badge-info {
    color: #fff;
    background-color: #17a2b8;
}

.form-control-static {
    padding-top: 0.65rem;
    padding-bottom: 0.65rem;
    margin-bottom: 0;
    min-height: calc(1.5em + 1.3rem + 2px);
}

.text-muted {
    color: #64748b;
    font-size: 0.75rem;
    margin-top: 0.25rem;
}

.empty-lines {
    background-color: #f8fafc;
    padding: 2rem;
    border-radius: 0.375rem;
    text-align: center;
    color: #64748b;
    border: 1px dashed #cbd5e1;
}

.order-lines {
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
    overflow: hidden;
}

.order-currency-info {
    background-color: #eff6ff;
    color: #1e40af;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    border-bottom: 1px solid #dbeafe;
}

.line-headers {
    display: grid;
    grid-template-columns: 2.5fr 0.8fr 0.8fr 0.8fr 1fr 0.8fr 0.8fr 0.8fr 1.2fr 1.2fr 0.5fr;
    gap: 0.5rem;
    background-color: #f8fafc;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e2e8f0;
    font-weight: 500;
    color: #475569;
    font-size: 0.75rem;
}

.order-line {
    display: grid;
    grid-template-columns: 2.5fr 0.8fr 0.8fr 0.8fr 1fr 0.8fr 0.8fr 0.8fr 1.2fr 1.2fr 0.5fr;
    gap: 0.5rem;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #e2e8f0;
    align-items: center;
}

.order-line:last-child {
    border-bottom: none;
}

.line-item input,
.line-item select {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.25rem;
    font-size: 0.875rem;
}

.line-item.subtotal,
.line-item.total {
    font-weight: 500;
    text-align: right;
}

.line-item.total {
    color: #2563eb;
}

.line-item.actions {
    text-align: center;
}

.btn-icon {
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    padding: 0.375rem;
    border-radius: 0.25rem;
}

.btn-icon:hover {
    background-color: #f1f5f9;
}

.btn-icon.delete:hover {
    background-color: #fee2e2;
    color: #dc2626;
}

/* Tax display styling */
.tax-rate-display {
    font-weight: 500;
    color: #059669;
}

.tax-amount-display {
    font-weight: 500;
    color: #059669;
}

.tax-breakdown {
    margin-top: 0.25rem;
}

.tax-detail {
    display: block;
    font-size: 0.6rem;
    color: #64748b;
    line-height: 1.2;
}

/* Tax Summary Section */
.tax-summary-section {
    background-color: #f0fdf4;
    border: 1px solid #bbf7d0;
    border-radius: 0.375rem;
    padding: 1rem;
    margin: 1rem 0;
}

.tax-summary-section h4 {
    margin: 0 0 0.75rem 0;
    color: #065f46;
    font-size: 0.875rem;
    font-weight: 600;
}

.tax-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.5rem;
}

.tax-group {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    background-color: white;
    border-radius: 0.25rem;
    border: 1px solid #d1fae5;
}

.tax-name {
    font-size: 0.75rem;
    color: #064e3b;
    font-weight: 500;
}

.tax-amount {
    font-size: 0.75rem;
    color: #059669;
    font-weight: 600;
}

.order-totals {
    background-color: #f8fafc;
    padding: 1rem 1.5rem;
    border-top: 1px solid #e2e8f0;
}

.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.total-row:last-child {
    margin-bottom: 0;
}

.total-label {
    font-weight: 500;
    color: #64748b;
}

.total-value {
    font-weight: 600;
    color: #1e293b;
}

.grand-total {
    border-top: 1px solid #e2e8f0;
    padding-top: 0.5rem;
    margin-top: 0.5rem;
}

.grand-total .total-label,
.grand-total .total-value {
    font-size: 1.125rem;
    color: #1e293b;
}

/* Dropdown styling */
.dropdown-container {
    position: relative;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    z-index: 1000;
    max-height: 200px;
    overflow-y: auto;
    background-color: white;
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.dropdown-item {
    padding: 0.75rem;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
}

.dropdown-item:hover {
    background-color: #f8fafc;
}

.dropdown-item:last-child {
    border-bottom: none;
}

.dropdown-item.text-muted {
    color: #64748b;
    font-style: italic;
}

.dropdown-item.text-muted:hover {
    background-color: transparent;
}

/* Item display styling */
.item-display {
    display: flex;
    flex-direction: column;
}

.item-display strong {
    color: #1e293b;
    font-weight: 600;
}

.item-display small {
    color: #64748b;
    font-size: 0.75rem;
    margin-top: 0.125rem;
}

.d-block {
    display: block;
}

.customer-info {
    display: flex;
    flex-direction: column;
}

.customer-name {
    font-weight: 500;
    color: #1e293b;
}

.customer-code {
    font-size: 0.75rem;
    color: #64748b;
    margin-top: 0.125rem;
}

.form-control.is-invalid {
    border-color: #dc2626;
}

.form-control.is-invalid:focus {
    outline: 2px solid #dc2626;
    outline-offset: 1px;
}

.item-code {
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.75rem;
    color: #4338ca;
    background-color: #f0f4ff;
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
    display: inline-block;
}

/* Improve line item container for better dropdown positioning */
.line-item {
    position: relative;
}

.line-item .dropdown-container {
    z-index: 100;
}

@media (max-width: 1024px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 0;
    }

    .order-line,
    .line-headers {
        grid-template-columns: repeat(10, 1fr) 0.5fr;
        font-size: 0.75rem;
        padding: 0.5rem;
    }
}

@media (max-width: 768px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .order-line,
    .line-headers {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        padding: 1rem;
    }

    .line-header {
        display: none;
    }

    .line-item {
        display: flex;
        align-items: center;
        width: 100%;
    }

    .line-item::before {
        content: attr(data-label);
        font-weight: 500;
        width: 8rem;
        text-align: left;
    }

    .total-row {
        flex-direction: column;
        align-items: flex-end;
    }

    .total-label,
    .total-value {
        width: auto;
    }
}
</style>