<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\AccessControlProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@sams.local'],
            [
                'name' => 'Administrator SAMS',
                'password' => 'password',
                'role' => 'super_admin',
                'is_active' => true,
            ],
        );

        $roleUsers = [
            ['email' => 'purchasing@sams.local', 'name' => 'Purchasing User', 'role' => 'purchasing'],
            ['email' => 'warehouse@sams.local', 'name' => 'Warehouse User', 'role' => 'warehouse'],
            ['email' => 'finance@sams.local', 'name' => 'Finance User', 'role' => 'finance'],
            ['email' => 'staff@sams.local', 'name' => 'Staff User', 'role' => 'staff'],
        ];

        foreach ($roleUsers as $roleUser) {
            User::query()->updateOrCreate(
                ['email' => $roleUser['email']],
                [
                    'name' => $roleUser['name'],
                    'password' => 'password',
                    'role' => $roleUser['role'],
                    'is_active' => true,
                ],
            );
        }

        DB::table('companies')->updateOrInsert(
            ['code' => 'SAMS'],
            [
                'public_id' => (string) Str::uuid(),
                'name' => 'SAMS Demo Company',
                'legal_name' => 'PT SAMS Demo Indonesia',
                'timezone' => 'Asia/Makassar',
                'currency' => 'IDR',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $company = DB::table('companies')->where('code', 'SAMS')->first();

        DB::table('branches')->updateOrInsert(
            ['company_id' => $company->id, 'code' => 'HO'],
            [
                'public_id' => (string) Str::uuid(),
                'name' => 'Head Office',
                'address' => 'Demo local SAMS',
                'timezone' => 'Asia/Makassar',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $branch = DB::table('branches')
            ->where('company_id', $company->id)
            ->where('code', 'HO')
            ->first();

        $departments = [
            ['code' => 'PUR', 'name' => 'Purchasing'],
            ['code' => 'WH', 'name' => 'Warehouse'],
            ['code' => 'FIN', 'name' => 'Finance'],
            ['code' => 'OPS', 'name' => 'Operations'],
        ];

        foreach ($departments as $department) {
            DB::table('departments')->updateOrInsert(
                [
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'code' => $department['code'],
                ],
                [
                    'name' => $department['name'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $purchasing = DB::table('departments')
            ->where('company_id', $company->id)
            ->where('branch_id', $branch->id)
            ->where('code', 'PUR')
            ->first();

        DB::table('company_user')->updateOrInsert(
            ['company_id' => $company->id, 'user_id' => $admin->id],
            [
                'branch_id' => $branch->id,
                'department_id' => $purchasing->id,
                'is_default' => true,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        User::query()
            ->whereIn('email', collect($roleUsers)->pluck('email'))
            ->get()
            ->each(function (User $user) use ($company, $branch, $purchasing, $now) {
                DB::table('company_user')->updateOrInsert(
                    ['company_id' => $company->id, 'user_id' => $user->id],
                    [
                        'branch_id' => $branch->id,
                        'department_id' => $purchasing->id,
                        'is_default' => true,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            });

        $units = [
            'PCS' => ['name' => 'Pieces', 'decimal_places' => 0],
            'BOX' => ['name' => 'Box', 'decimal_places' => 0],
            'KG' => ['name' => 'Kilogram', 'decimal_places' => 2],
            'GR' => ['name' => 'Gram', 'decimal_places' => 2],
            'LTR' => ['name' => 'Liter', 'decimal_places' => 2],
            'BTL' => ['name' => 'Bottle', 'decimal_places' => 0],
            'PACK' => ['name' => 'Pack', 'decimal_places' => 0],
            'ROLL' => ['name' => 'Roll', 'decimal_places' => 0],
            'SET' => ['name' => 'Set', 'decimal_places' => 0],
        ];

        foreach ($units as $code => $unit) {
            DB::table('units')->updateOrInsert(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'name' => $unit['name'],
                    'decimal_places' => $unit['decimal_places'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $categories = [
            'GENERAL' => 'General Supplies',
            'FOOD' => 'Food & Beverage',
            'BEV' => 'Beverage',
            'HK' => 'Housekeeping Supplies',
            'LINEN' => 'Linen & Guest Room',
            'ENG' => 'Engineering & Maintenance',
            'OFFICE' => 'Office Supplies',
            'ASSET' => 'Fixed Asset',
        ];

        foreach ($categories as $code => $name) {
            DB::table('item_categories')->updateOrInsert(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'name' => $name,
                    'is_active' => true,
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $suppliers = [
            [
                'code' => 'SUP-FOOD-01',
                'name' => 'Bali Fresh Market',
                'contact_person' => 'Ibu Wayan',
                'phone' => '0812-0001-1001',
                'email' => 'sales@balifresh.example',
                'payment_terms_days' => 7,
            ],
            [
                'code' => 'SUP-HK-01',
                'name' => 'Nusantara Cleaning Supply',
                'contact_person' => 'Pak Andi',
                'phone' => '0812-0001-1002',
                'email' => 'order@nusantaraclean.example',
                'payment_terms_days' => 14,
            ],
            [
                'code' => 'SUP-LINEN-01',
                'name' => 'Prima Linen Laundry',
                'contact_person' => 'Ibu Sari',
                'phone' => '0812-0001-1003',
                'email' => 'admin@primalinen.example',
                'payment_terms_days' => 14,
            ],
            [
                'code' => 'SUP-ENG-01',
                'name' => 'Makassar Engineering Parts',
                'contact_person' => 'Pak Budi',
                'phone' => '0812-0001-1004',
                'email' => 'parts@makassarengineering.example',
                'payment_terms_days' => 30,
            ],
            [
                'code' => 'SUP-OFFICE-01',
                'name' => 'Mandiri Office Stationery',
                'contact_person' => 'Ibu Rina',
                'phone' => '0812-0001-1005',
                'email' => 'cs@mandirioffice.example',
                'payment_terms_days' => 14,
            ],
        ];

        foreach ($suppliers as $supplier) {
            DB::table('suppliers')->updateOrInsert(
                ['company_id' => $company->id, 'code' => $supplier['code']],
                [
                    'public_id' => (string) Str::uuid(),
                    'name' => $supplier['name'],
                    'contact_person' => $supplier['contact_person'],
                    'phone' => $supplier['phone'],
                    'email' => $supplier['email'],
                    'payment_terms_days' => $supplier['payment_terms_days'],
                    'is_active' => true,
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $locations = [
            ['code' => 'MAIN-WH', 'name' => 'Main Warehouse', 'type' => 'warehouse'],
            ['code' => 'FB-STORE', 'name' => 'F&B Store', 'type' => 'store'],
            ['code' => 'HK-STORE', 'name' => 'Housekeeping Store', 'type' => 'store'],
            ['code' => 'ENG-STORE', 'name' => 'Engineering Store', 'type' => 'store'],
            ['code' => 'KITCHEN', 'name' => 'Main Kitchen', 'type' => 'kitchen'],
        ];

        foreach ($locations as $location) {
            DB::table('storage_locations')->updateOrInsert(
                ['branch_id' => $branch->id, 'code' => $location['code']],
                [
                    'company_id' => $company->id,
                    'name' => $location['name'],
                    'type' => $location['type'],
                    'allow_negative_stock' => false,
                    'is_active' => true,
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $categoryIds = DB::table('item_categories')
            ->where('company_id', $company->id)
            ->pluck('id', 'code');

        $unitIds = DB::table('units')
            ->where('company_id', $company->id)
            ->pluck('id', 'code');

        $items = [
            [
                'sku' => 'ITM-RICE-01',
                'name' => 'Beras Premium 25kg',
                'category' => 'FOOD',
                'unit' => 'KG',
                'type' => 'inventory',
                'minimum_stock' => 50,
                'maximum_stock' => 500,
                'standard_cost' => 14500,
                'description' => 'Beras premium untuk kebutuhan kitchen dan staff meal.',
            ],
            [
                'sku' => 'ITM-COFFEE-01',
                'name' => 'Coffee Beans Arabica',
                'category' => 'BEV',
                'unit' => 'KG',
                'type' => 'inventory',
                'minimum_stock' => 10,
                'maximum_stock' => 80,
                'standard_cost' => 180000,
                'description' => 'Biji kopi arabica untuk outlet restoran dan breakfast.',
            ],
            [
                'sku' => 'ITM-MINWATER-330',
                'name' => 'Air Mineral 330ml',
                'category' => 'BEV',
                'unit' => 'BTL',
                'type' => 'inventory',
                'minimum_stock' => 240,
                'maximum_stock' => 2000,
                'standard_cost' => 2800,
                'description' => 'Air mineral guest room dan meeting room.',
            ],
            [
                'sku' => 'ITM-TISSUE-ROLL',
                'name' => 'Toilet Tissue Roll',
                'category' => 'HK',
                'unit' => 'ROLL',
                'type' => 'inventory',
                'minimum_stock' => 150,
                'maximum_stock' => 1200,
                'standard_cost' => 4500,
                'description' => 'Tissue toilet untuk guest room dan public area.',
            ],
            [
                'sku' => 'ITM-AMENITY-DENTAL',
                'name' => 'Dental Kit Guest Amenity',
                'category' => 'HK',
                'unit' => 'PCS',
                'type' => 'inventory',
                'minimum_stock' => 300,
                'maximum_stock' => 3000,
                'standard_cost' => 3200,
                'description' => 'Dental kit untuk perlengkapan kamar tamu.',
            ],
            [
                'sku' => 'ITM-TOWEL-BATH',
                'name' => 'Bath Towel White',
                'category' => 'LINEN',
                'unit' => 'PCS',
                'type' => 'asset',
                'minimum_stock' => 80,
                'maximum_stock' => 800,
                'standard_cost' => 85000,
                'description' => 'Handuk mandi putih standar hotel.',
            ],
            [
                'sku' => 'ITM-BEDSHEET-KING',
                'name' => 'Bed Sheet King Size',
                'category' => 'LINEN',
                'unit' => 'PCS',
                'type' => 'asset',
                'minimum_stock' => 60,
                'maximum_stock' => 500,
                'standard_cost' => 165000,
                'description' => 'Sprei king size untuk kamar tamu.',
            ],
            [
                'sku' => 'ITM-LED-12W',
                'name' => 'LED Bulb 12W Warm White',
                'category' => 'ENG',
                'unit' => 'PCS',
                'type' => 'inventory',
                'minimum_stock' => 40,
                'maximum_stock' => 300,
                'standard_cost' => 38000,
                'description' => 'Lampu LED pengganti untuk kamar dan area publik.',
            ],
            [
                'sku' => 'ITM-CLEANER-FLOOR',
                'name' => 'Floor Cleaner 5 Liter',
                'category' => 'HK',
                'unit' => 'LTR',
                'type' => 'inventory',
                'minimum_stock' => 30,
                'maximum_stock' => 250,
                'standard_cost' => 18500,
                'description' => 'Cairan pembersih lantai untuk housekeeping.',
            ],
            [
                'sku' => 'ITM-PAPER-A4',
                'name' => 'HVS A4 80gsm',
                'category' => 'OFFICE',
                'unit' => 'PACK',
                'type' => 'inventory',
                'minimum_stock' => 20,
                'maximum_stock' => 150,
                'standard_cost' => 62000,
                'description' => 'Kertas kantor untuk administrasi hotel.',
            ],
            [
                'sku' => 'ITM-LAPTOP-OPS',
                'name' => 'Laptop Operations Standard',
                'category' => 'ASSET',
                'unit' => 'PCS',
                'type' => 'asset',
                'minimum_stock' => 0,
                'maximum_stock' => 10,
                'standard_cost' => 9500000,
                'description' => 'Laptop operasional untuk supervisor/manager.',
            ],
            [
                'sku' => 'ITM-SERVICE-AC',
                'name' => 'Jasa Service AC Split',
                'category' => 'ENG',
                'unit' => 'PCS',
                'type' => 'service',
                'minimum_stock' => 0,
                'maximum_stock' => null,
                'standard_cost' => 350000,
                'description' => 'Jasa maintenance AC split per unit.',
            ],
        ];

        foreach ($items as $item) {
            DB::table('items')->updateOrInsert(
                ['company_id' => $company->id, 'sku' => $item['sku']],
                [
                    'item_category_id' => $categoryIds[$item['category']] ?? null,
                    'base_unit_id' => $unitIds[$item['unit']],
                    'public_id' => (string) Str::uuid(),
                    'barcode' => null,
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'item_type' => $item['type'],
                    'minimum_stock' => $item['minimum_stock'],
                    'maximum_stock' => $item['maximum_stock'],
                    'standard_cost' => $item['standard_cost'],
                    'is_active' => true,
                    'deleted_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $budgets = [
            'PUR' => [
                'name' => 'Budget Purchasing 2026',
                'lines' => [
                    ['account_code' => 'PUR-FNB', 'description' => 'Food & Beverage Purchasing', 'allocated_amount' => 250000000],
                    ['account_code' => 'PUR-GEN', 'description' => 'General Purchasing', 'allocated_amount' => 120000000],
                ],
            ],
            'HK' => [
                'name' => 'Budget Housekeeping 2026',
                'lines' => [
                    ['account_code' => 'HK-AMN', 'description' => 'Guest Amenities', 'allocated_amount' => 90000000],
                    ['account_code' => 'HK-LIN', 'description' => 'Linen Replacement', 'allocated_amount' => 180000000],
                    ['account_code' => 'HK-CHM', 'description' => 'Cleaning Chemical', 'allocated_amount' => 75000000],
                ],
            ],
            'ENG' => [
                'name' => 'Budget Engineering 2026',
                'lines' => [
                    ['account_code' => 'ENG-MNT', 'description' => 'Maintenance Parts & Services', 'allocated_amount' => 160000000],
                    ['account_code' => 'ENG-CAPEX', 'description' => 'Small Capex & Tools', 'allocated_amount' => 220000000],
                ],
            ],
            'OPS' => [
                'name' => 'Budget Operations 2026',
                'lines' => [
                    ['account_code' => 'OPS-OFFICE', 'description' => 'Office & Administration Supplies', 'allocated_amount' => 60000000],
                    ['account_code' => 'OPS-ASSET', 'description' => 'Operational Assets', 'allocated_amount' => 150000000],
                ],
            ],
        ];

        foreach ($budgets as $departmentCode => $budgetConfig) {
            $department = DB::table('departments')
                ->where('company_id', $company->id)
                ->where('branch_id', $branch->id)
                ->where('code', $departmentCode)
                ->first();

            if (! $department) {
                continue;
            }

            DB::table('budgets')->updateOrInsert(
                [
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'department_id' => $department->id,
                    'name' => $budgetConfig['name'],
                ],
                [
                    'period_start' => '2026-01-01',
                    'period_end' => '2026-12-31',
                    'status' => 'active',
                    'total_amount' => collect($budgetConfig['lines'])->sum('allocated_amount'),
                    'created_by' => $admin->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $budget = DB::table('budgets')
                ->where('company_id', $company->id)
                ->where('branch_id', $branch->id)
                ->where('department_id', $department->id)
                ->where('name', $budgetConfig['name'])
                ->first();

            foreach ($budgetConfig['lines'] as $line) {
                DB::table('budget_lines')->updateOrInsert(
                    ['budget_id' => $budget->id, 'account_code' => $line['account_code']],
                    [
                        'description' => $line['description'],
                        'allocated_amount' => $line['allocated_amount'],
                        'committed_amount' => 0,
                        'actual_amount' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }

        foreach ([
            ['code' => '1100', 'name' => 'Cash and Bank', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '2100', 'name' => 'Trade Accounts Payable', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '6100', 'name' => 'Operating Expense', 'type' => 'expense', 'normal_balance' => 'debit'],
        ] as $account) {
            DB::table('gl_accounts')->updateOrInsert(
                ['company_id' => $company->id, 'code' => $account['code']],
                $account + [
                    'allow_posting' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        app(AccessControlProvisioner::class)->syncAllCompanies();

        DB::table('company_modules')
            ->where('company_id', $company->id)
            ->whereIn('module_id', DB::table('modules')->where('status', 'active')->select('id'))
            ->update(['is_licensed' => true, 'is_enabled' => true]);

        DB::table('company_subscriptions')->updateOrInsert(
            ['company_id' => $company->id],
            [
                'plan_code' => 'development-suite',
                'license_model' => 'internal',
                'billing_cycle' => 'none',
                'status' => 'active',
                'starts_on' => today()->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('company_storage_profiles')->updateOrInsert(
            ['company_id' => $company->id],
            [
                'mode' => 'local',
                'provider' => 'local',
                'status' => 'active',
                'root_prefix' => 'companies/'.$company->id,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        app(AccessControlProvisioner::class)->syncAllCompanies();
    }
}
