<?php

namespace Tests\Feature;

use App\Http\Resources\SaleResource;
use App\Models\Branch;
use App\Models\Business;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_checkout_creates_one_transaction_receipt_with_multiple_product_lines(): void
    {
        [$user, $business, $branch] = $this->businessFixture();
        $rice = $this->stockedProduct($business, $branch, 'Rice', 'RICE-001', 10, 15, 20);
        $soap = $this->stockedProduct($business, $branch, 'Soap', 'SOAP-001', 4, 7, 30);
        $idempotencyKey = (string) Str::uuid();

        $payload = [
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'cashier_id' => $user->id,
            'idempotency_key' => $idempotencyKey,
            'items' => [
                ['product_id' => $rice->id, 'quantity' => 2],
                ['product_id' => $soap->id, 'quantity' => 3],
            ],
            'payment' => ['method' => 'cash', 'amount' => 51],
        ];

        $sale = app(SaleService::class)->create($payload);

        $this->assertSame(1, Sale::where('business_id', $business->id)->count());
        $this->assertCount(2, $sale->items);
        $this->assertSame('51.0000', (string) $sale->total);
        $this->assertSame('paid', $sale->payment_status);
        $this->assertSame('19.0000', (string) $sale->gross_profit);
        $this->assertSame('18.0000', (string) InventoryBalance::where('product_id', $rice->id)->value('quantity'));
        $this->assertSame('27.0000', (string) InventoryBalance::where('product_id', $soap->id)->value('quantity'));

        $receipt = (new SaleResource($sale))->toArray(Request::create('/api/v1/sales/'.$sale->id));
        $this->assertSame($sale->sale_number, $receipt['sale_number']);
        $this->assertCount(2, $receipt['items']);
        $this->assertSame(['Rice', 'Soap'], collect($receipt['items'])->pluck('product_name')->all());
        $this->assertCount(1, $receipt['payments']);

        // Retrying the same checkout must return the original transaction.
        $replayed = app(SaleService::class)->create($payload);
        $this->assertSame($sale->id, $replayed->id);
        $this->assertSame(1, Sale::where('business_id', $business->id)->count());
    }

    private function businessFixture(): array
    {
        $user = User::create([
            'name' => 'Receipt Cashier',
            'email' => Str::uuid().'@example.test',
            'password' => 'TestPassword123!',
        ]);
        $business = Business::create([
            'name' => 'Receipt Shop',
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

        return [$user, $business, $branch];
    }

    private function stockedProduct(
        Business $business,
        Branch $branch,
        string $name,
        string $sku,
        int $buyingPrice,
        int $sellingPrice,
        int $quantity
    ): Product {
        $product = Product::create([
            'business_id' => $business->id,
            'name' => $name,
            'sku' => $sku,
            'unit' => 'piece',
            'buying_price' => $buyingPrice,
            'selling_price' => $sellingPrice,
            'track_inventory' => true,
            'is_active' => true,
        ]);
        InventoryBalance::create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'reserved_quantity' => 0,
            'average_cost' => $buyingPrice,
        ]);

        return $product;
    }
}
