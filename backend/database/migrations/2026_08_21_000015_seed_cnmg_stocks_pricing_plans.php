<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private function savePlan(array $plan): void
    {
        $existing = DB::table('plans')->where('slug', $plan['slug'])->first();

        if ($existing) {
            DB::table('plans')->where('slug', $plan['slug'])->update(array_merge($plan, ['updated_at' => now()]));
            return;
        }

        DB::table('plans')->insert(array_merge($plan, [
            'id' => (string) Str::uuid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function up(): void
    {
        $this->savePlan([
            'name' => 'Free',
            'slug' => 'free',
            'description' => 'For very small businesses getting started with CNMG STOCKS.',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'currency' => 'GHS',
            'max_products' => 50,
            'max_users' => 1,
            'max_branches' => 1,
            'has_reports' => false,
            'has_multi_branch' => false,
            'has_api_access' => false,
            'has_priority_support' => false,
            'features' => json_encode(['sales', 'inventory']),
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $this->savePlan([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'For small shops and traders ready for more records and reporting.',
            'price_monthly' => 30,
            'price_yearly' => 300,
            'currency' => 'GHS',
            'max_products' => 500,
            'max_users' => 2,
            'max_branches' => 1,
            'has_reports' => true,
            'has_multi_branch' => false,
            'has_api_access' => false,
            'has_priority_support' => false,
            'features' => json_encode(['sales', 'inventory', 'customers', 'suppliers', 'expenses', 'reports']),
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $this->savePlan([
            'name' => 'Business',
            'slug' => 'business',
            'description' => 'For growing SMEs that need teams, branches and advanced reporting.',
            'price_monthly' => 60,
            'price_yearly' => 600,
            'currency' => 'GHS',
            'max_products' => null,
            'max_users' => 5,
            'max_branches' => 3,
            'has_reports' => true,
            'has_multi_branch' => true,
            'has_api_access' => false,
            'has_priority_support' => false,
            'features' => json_encode(['unlimited_products', 'advanced_reports', 'stock_history', 'roles_permissions']),
            'is_active' => true,
            'sort_order' => 3,
        ]);
        $this->savePlan([
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'For larger businesses that need more users, branches and priority support.',
            'price_monthly' => 100,
            'price_yearly' => 1000,
            'currency' => 'GHS',
            'max_products' => null,
            'max_users' => 15,
            'max_branches' => 10,
            'has_reports' => true,
            'has_multi_branch' => true,
            'has_api_access' => false,
            'has_priority_support' => true,
            'features' => json_encode(['unlimited_products', 'advanced_analytics', 'priority_support']),
            'is_active' => true,
            'sort_order' => 4,
        ]);
        $this->savePlan([
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'description' => 'Custom plan for chains and organisations with larger operational needs.',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'currency' => 'GHS',
            'max_products' => null,
            'max_users' => null,
            'max_branches' => null,
            'has_reports' => true,
            'has_multi_branch' => true,
            'has_api_access' => true,
            'has_priority_support' => true,
            'features' => json_encode(['custom_pricing', 'onboarding', 'dedicated_support', 'api_access']),
            'is_active' => true,
            'sort_order' => 5,
        ]);
    }

    public function down(): void
    {
        // Pricing records are intentionally preserved on rollback.
    }
};
