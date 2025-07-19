<?php
// database/migrations/2025_07_19_000001_create_tax_configuration_tables.php

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
        // Create tax_groups table
        Schema::create('tax_groups', function (Blueprint $table) {
            $table->id('tax_group_id');
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('is_active');
        });

        // Create taxes table
        Schema::create('taxes', function (Blueprint $table) {
            $table->id('tax_id');
            $table->string('name', 100);
            $table->string('description', 255)->nullable();
            $table->enum('tax_type', ['sales', 'purchase', 'both'])->default('both');
            $table->enum('computation_type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('rate', 8, 4)->default(0); // Support up to 9999.9999%
            $table->decimal('amount', 15, 2)->default(0); // For fixed amount taxes
            $table->boolean('is_active')->default(true);
            $table->boolean('included_in_price')->default(false); // Tax included in price or added on top
            $table->integer('sequence')->default(10); // Order of tax calculation
            $table->unsignedBigInteger('tax_group_id')->nullable();
            $table->timestamps();
            
            $table->foreign('tax_group_id')->references('tax_group_id')->on('tax_groups')->onDelete('set null');
            $table->index(['tax_type', 'is_active']);
            $table->index('sequence');
        });

        // Create item_taxes table (many-to-many relationship)
        Schema::create('item_taxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('tax_id');
            $table->enum('tax_type', ['sales', 'purchase']);
            $table->timestamps();
            
            $table->foreign('item_id')->references('item_id')->on('Item')->onDelete('cascade');
            $table->foreign('tax_id')->references('tax_id')->on('taxes')->onDelete('cascade');
            $table->unique(['item_id', 'tax_id', 'tax_type']);
            $table->index(['item_id', 'tax_type']);
        });

        // Create customer_taxes table (default taxes for customers)
        Schema::create('customer_taxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('tax_id');
            $table->timestamps();
            
            $table->foreign('customer_id')->references('customer_id')->on('Customer')->onDelete('cascade');
            $table->foreign('tax_id')->references('tax_id')->on('taxes')->onDelete('cascade');
            $table->unique(['customer_id', 'tax_id']);
        });

        // Create vendor_taxes table (default taxes for vendors)
        Schema::create('vendor_taxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('tax_id');
            $table->timestamps();
            
            $table->foreign('vendor_id')->references('vendor_id')->on('Vendor')->onDelete('cascade');
            $table->foreign('tax_id')->references('tax_id')->on('taxes')->onDelete('cascade');
            $table->unique(['vendor_id', 'tax_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendor_taxes');
        Schema::dropIfExists('customer_taxes');
        Schema::dropIfExists('item_taxes');
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('tax_groups');
    }
};