<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SearchAndOfflineTenantSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_search_works_with_the_mysql_compatible_operator(): void
    {
        [$user, $business, $branch] = $this->ownedBusiness('Search Shop');

        Product::create([
            'business_id' => $business->id,
            'name' => 'Premium Rice',
            'sku' => 'RICE-001',
            'unit' => 'bag',
            'buying_price' => 300,
            'selling_price' => 350,
        ]);

        Sanctum::actingAs($user);

        $this->withHeaders([
            'X-Business-Id' => $business->id,
            'X-Branch-Id' => $branch->id,
        ])->getJson('/api/v1/products?search=Rice')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Premium Rice');
    }

    public function test_offline_operation_from_another_business_is_rejected(): void
    {
        [$userA, $businessA, $branchA] = $this->ownedBusiness('Business A');
        [, $businessB, $branchB] = $this->ownedBusiness('Business B');

        $productB = Product::create([
            'business_id' => $businessB->id,
            'name' => 'Business B Product',
            'sku' => 'B-001',
            'unit' => 'piece',
            'buying_price' => 5,
            'selling_price' => 10,
        ]);

        Sanctum::actingAs($userA);

        $this->withHeaders([
            'X-Business-Id' => $businessA->id,
            'X-Branch-Id' => $branchA->id,
        ])->postJson('/api/v1/sync/push', [
            'device_id' => 'test-device',
            'operations' => [[
                'idempotency_key' => (string) Str::uuid(),
                'operation_type' => 'inventory_adjustment',
                'origin_business_id' => $businessA->id,
                'origin_user_id' => $userA->id,
                'client_created_at' => now()->toIso8601String(),
                'payload' => [
                    'branch_id' => $branchB->id,
                    'product_id' => $productB->id,
                    'direction' => 'in',
                    'quantity' => 1,
                ],
            ]],
        ])->assertOk()
            ->assertJsonPath('summary.conflicts', 1)
            ->assertJsonPath('results.0.status', 'conflict');
    }

    public function test_invalid_branch_header_is_not_silently_ignored(): void
    {
        [$userA, $businessA] = $this->ownedBusiness('Business A');
        [, , $branchB] = $this->ownedBusiness('Business B');

        Sanctum::actingAs($userA);

        $this->withHeaders([
            'X-Business-Id' => $businessA->id,
            'X-Branch-Id' => $branchB->id,
        ])->getJson('/api/v1/products')
            ->assertStatus(422)
            ->assertJsonPath('message', 'The selected branch does not belong to this business or is inactive.');
    }

    private function ownedBusiness(string $name): array
    {
        $user = User::create([
            'name' => $name.' Owner',
            'email' => Str::slug($name).'-'.Str::lower(Str::random(6)).'@example.test',
            'password' => 'TestPassword123!',
        ]);

        $business = Business::create([
            'name' => $name,
            'status' => 'active',
            'plan' => 'business',
        ]);

        $branch = Branch::create([
            'business_id' => $business->id,
            'name' => 'Main Branch',
            'code' => 'MAIN',
            'status' => 'active',
            'is_head_office' => true,
        ]);

        $business->users()->attach($user->id, [
            'branch_id' => $branch->id,
            'is_owner' => true,
            'role' => 'owner',
            'is_active' => true,
        ]);

        return [$user, $business, $branch];
    }
}
