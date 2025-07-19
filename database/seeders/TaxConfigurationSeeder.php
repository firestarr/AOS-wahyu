<?php
// database/seeders/TaxConfigurationSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tax\TaxGroup;
use App\Models\Tax\Tax;
use Illuminate\Support\Facades\DB;

class TaxConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // Create Tax Groups
            $salesTaxGroup = TaxGroup::create([
                'name' => 'Sales Tax',
                'description' => 'Standard sales taxes',
                'is_active' => true
            ]);

            $vatGroup = TaxGroup::create([
                'name' => 'VAT',
                'description' => 'Value Added Tax',
                'is_active' => true
            ]);

            $serviceTaxGroup = TaxGroup::create([
                'name' => 'Service Tax',
                'description' => 'Taxes for services',
                'is_active' => true
            ]);

            // Create Taxes - Similar to Odoo's tax structure
            
            // 1. Standard VAT 10% (Tax Exclusive)
            Tax::create([
                'name' => 'VAT 10%',
                'description' => 'Value Added Tax 10% (Tax Exclusive)',
                'tax_type' => 'both',
                'computation_type' => 'percentage',
                'rate' => 10.0000,
                'amount' => 0,
                'is_active' => true,
                'included_in_price' => false,
                'sequence' => 10,
                'tax_group_id' => $vatGroup->tax_group_id
            ]);

            // 2. VAT 11% (Tax Inclusive) - Like PPN Indonesia
            Tax::create([
                'name' => 'PPN 11%',
                'description' => 'Pajak Pertambahan Nilai 11% (Tax Inclusive)',
                'tax_type' => 'both',
                'computation_type' => 'percentage',
                'rate' => 11.0000,
                'amount' => 0,
                'is_active' => true,
                'included_in_price' => true,
                'sequence' => 10,
                'tax_group_id' => $vatGroup->tax_group_id
            ]);

            // 3. Sales Tax 5%
            Tax::create([
                'name' => 'Sales Tax 5%',
                'description' => 'Standard Sales Tax 5%',
                'tax_type' => 'sales',
                'computation_type' => 'percentage',
                'rate' => 5.0000,
                'amount' => 0,
                'is_active' => true,
                'included_in_price' => false,
                'sequence' => 10,
                'tax_group_id' => $salesTaxGroup->tax_group_id
            ]);

            // 4. Fixed Shipping Tax
            Tax::create([
                'name' => 'Shipping Tax',
                'description' => 'Fixed shipping tax amount',
                'tax_type' => 'both',
                'computation_type' => 'fixed',
                'rate' => 0,
                'amount' => 50.00,
                'is_active' => true,
                'included_in_price' => false,
                'sequence' => 20,
                'tax_group_id' => $serviceTaxGroup->tax_group_id
            ]);

            // 5. Service Tax 6%
            Tax::create([
                'name' => 'Service Tax 6%',
                'description' => 'Service Tax 6%',
                'tax_type' => 'both',
                'computation_type' => 'percentage',
                'rate' => 6.0000,
                'amount' => 0,
                'is_active' => true,
                'included_in_price' => false,
                'sequence' => 15,
                'tax_group_id' => $serviceTaxGroup->tax_group_id
            ]);

            // 6. Combined Tax Example (VAT + Service Tax)
            Tax::create([
                'name' => 'VAT 10% + Service 2%',
                'description' => 'Combined VAT and Service Tax',
                'tax_type' => 'sales',
                'computation_type' => 'percentage',
                'rate' => 12.0000,
                'amount' => 0,
                'is_active' => true,
                'included_in_price' => false,
                'sequence' => 10,
                'tax_group_id' => $vatGroup->tax_group_id
            ]);

            // 7. Zero Rate Tax (for exports, etc.)
            Tax::create([
                'name' => 'Zero Rate',
                'description' => 'Zero rate tax for exports',
                'tax_type' => 'both',
                'computation_type' => 'percentage',
                'rate' => 0.0000,
                'amount' => 0,
                'is_active' => true,
                'included_in_price' => false,
                'sequence' => 5,
                'tax_group_id' => $vatGroup->tax_group_id
            ]);

            // 8. High Rate Tax
            Tax::create([
                'name' => 'Luxury Tax 20%',
                'description' => 'High rate tax for luxury items',
                'tax_type' => 'both',
                'computation_type' => 'percentage',
                'rate' => 20.0000,
                'amount' => 0,
                'is_active' => true,
                'included_in_price' => false,
                'sequence' => 10,
                'tax_group_id' => $salesTaxGroup->tax_group_id
            ]);

            DB::commit();

            $this->command->info('Tax configuration seeded successfully!');
            $this->command->info('Created tax groups:');
            $this->command->info('- Sales Tax');
            $this->command->info('- VAT');
            $this->command->info('- Service Tax');
            $this->command->info('');
            $this->command->info('Created taxes:');
            $this->command->info('- VAT 10% (Tax Exclusive)');
            $this->command->info('- PPN 11% (Tax Inclusive)');
            $this->command->info('- Sales Tax 5%');
            $this->command->info('- Shipping Tax (Fixed Amount)');
            $this->command->info('- Service Tax 6%');
            $this->command->info('- VAT 10% + Service 2% (Combined)');
            $this->command->info('- Zero Rate (for exports)');
            $this->command->info('- Luxury Tax 20%');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error seeding tax configuration: ' . $e->getMessage());
            throw $e;
        }
    }
}