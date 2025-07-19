<?php
// database/migrations/2025_07_19_000002_add_tax_fields_to_soline_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('SOLine', function (Blueprint $table) {
            // Add tax rate and breakdown fields
            $table->decimal('tax_rate', 8, 4)->default(0)->after('tax'); // Tax rate percentage
            $table->json('applied_taxes')->nullable()->after('tax_rate'); // JSON of applied taxes for detailed breakdown
            $table->decimal('subtotal_before_tax', 15, 2)->default(0)->after('applied_taxes'); // Subtotal before any tax
            $table->decimal('tax_inclusive_amount', 15, 2)->default(0)->after('subtotal_before_tax'); // Amount if tax is included
            
            // Base currency tax fields
            if (!Schema::hasColumn('SOLine', 'base_currency_unit_price')) {
                $table->decimal('base_currency_unit_price', 15, 4)->default(0)->after('tax_inclusive_amount');
                $table->decimal('base_currency_subtotal', 15, 2)->default(0)->after('base_currency_unit_price');
                $table->decimal('base_currency_discount', 15, 2)->default(0)->after('base_currency_subtotal');
                $table->decimal('base_currency_tax', 15, 2)->default(0)->after('base_currency_discount');
                $table->decimal('base_currency_total', 15, 2)->default(0)->after('base_currency_tax');
            }
        });

        // Also update Purchase Order lines for consistency
        Schema::table('po_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('po_lines', 'tax_rate')) {
                $table->decimal('tax_rate', 8, 4)->default(0)->after('tax');
                $table->json('applied_taxes')->nullable()->after('tax_rate');
                $table->decimal('subtotal_before_tax', 15, 2)->default(0)->after('applied_taxes');
                $table->decimal('tax_inclusive_amount', 15, 2)->default(0)->after('subtotal_before_tax');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('SOLine', function (Blueprint $table) {
            $table->dropColumn([
                'tax_rate',
                'applied_taxes',
                'subtotal_before_tax',
                'tax_inclusive_amount'
            ]);
        });

        Schema::table('po_lines', function (Blueprint $table) {
            $table->dropColumn([
                'tax_rate',
                'applied_taxes', 
                'subtotal_before_tax',
                'tax_inclusive_amount'
            ]);
        });
    }
};