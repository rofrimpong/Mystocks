<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    /**
     * Finalize a sale with full transactional integrity.
     *
     * Steps (all inside one DB transaction):
     * 1. Validate products & stock (with locking)
     * 2. Create sale
     * 3. Create sale items (historical prices)
     * 4. Reduce inventory via InventoryService
     * 5. Calculate COGS & gross profit
     * 6. Record payment if provided
     * 7. Commit or full rollback
     *
     * @param  array{
     *     business_id: string,
     *     branch_id: string,
     *     cashier_id: string,
     *     customer_id?: string|null,
     *     notes?: string|null,
     *     sold_at?: string|null,
     *     discount_amount?: string|float,
     *     tax_amount?: string|float,
     *     idempotency_key?: string|null,
     *     device_id?: string|null,
     *     items: array<int, array{
     *         product_id: string,
     *         quantity: string|float,
     *         unit_selling_price?: string|float|null,
     *         discount_amount?: string|float
     *     }>,
     *     payment?: array{method: string, amount: string|float, reference?: string|null, provider?: string|null}|null,
     * }  $data
     */
    public function create(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            // 1. Idempotency – never create duplicate sales
            if (! empty($data['idempotency_key'])) {
                $existing = Sale::where('business_id', $data['business_id'])
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->first();
                if ($existing) {
                    return $existing->load(['items', 'payments', 'customer', 'branch', 'cashier']);
                }
            }

            if (empty($data['items'])) {
                throw ValidationException::withMessages([
                    'items' => ['At least one item is required.'],
                ]);
            }

            $subtotal = '0';
            $totalCost = '0';
            $preparedItems = [];

            // 2. Validate each product, lock stock, prepare lines
            foreach ($data['items'] as $index => $item) {
                $product = Product::where('business_id', $data['business_id'])
                    ->where('id', $item['product_id'])
                    ->where('is_active', true)
                    ->first();

                if (! $product) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => ['Product not found or inactive.'],
                    ]);
                }

                $qty = $this->dec($item['quantity']);
                if (bccomp($qty, '0', 4) <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => ['Quantity must be greater than zero.'],
                    ]);
                }

                // Selling price: use provided or current product price
                $unitSelling = isset($item['unit_selling_price'])
                    ? $this->dec($item['unit_selling_price'])
                    : $this->dec($product->selling_price);

                $lineDiscount = $this->dec($item['discount_amount'] ?? 0);
                $lineTotal = bcsub(bcmul($qty, $unitSelling, 4), $lineDiscount, 4);

                // Historical cost from current average cost (or product buying_price fallback)
                $balance = InventoryBalance::where('branch_id', $data['branch_id'])
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                $unitCost = $balance
                    ? $this->dec($balance->average_cost)
                    : $this->dec($product->buying_price);

                // If still zero, fall back to product buying_price
                if (bccomp($unitCost, '0', 4) === 0) {
                    $unitCost = $this->dec($product->buying_price);
                }

                $lineCost = bcmul($qty, $unitCost, 4);
                $lineProfit = bcsub($lineTotal, $lineCost, 4);

                // Stock check (InventoryService will also enforce, but we fail early with clear message)
                if ($product->track_inventory && ! $product->is_service) {
                    $available = $balance ? $this->dec($balance->quantity) : '0';
                    $business = \App\Models\Business::find($data['business_id']);
                    if (bccomp($available, $qty, 4) < 0 && ! ($business?->allow_negative_stock ?? false)) {
                        throw ValidationException::withMessages([
                            "items.{$index}.quantity" => [
                                sprintf(
                                    'Insufficient stock for "%s". Available: %s, requested: %s.',
                                    $product->name,
                                    $available,
                                    $qty
                                ),
                            ],
                        ]);
                    }
                }

                $subtotal = bcadd($subtotal, $lineTotal, 4);
                $totalCost = bcadd($totalCost, $lineCost, 4);

                $preparedItems[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit_selling_price' => $unitSelling,
                    'unit_cost_price' => $unitCost,
                    'discount_amount' => $lineDiscount,
                    'line_total' => $lineTotal,
                    'line_cost' => $lineCost,
                    'line_profit' => $lineProfit,
                ];
            }

            $discountAmount = $this->dec($data['discount_amount'] ?? 0);
            $taxAmount = $this->dec($data['tax_amount'] ?? 0);
            $total = bcadd(bcsub($subtotal, $discountAmount, 4), $taxAmount, 4);
            $grossProfit = bcsub($total, $totalCost, 4); // simplified; tax handling can be refined later

            $saleNumber = $this->generateSaleNumber($data['business_id']);

            // 3. Create sale
            $sale = Sale::create([
                'business_id' => $data['business_id'],
                'branch_id' => $data['branch_id'],
                'cashier_id' => $data['cashier_id'],
                'customer_id' => $data['customer_id'] ?? null,
                'sale_number' => $saleNumber,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'status' => 'completed',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'cost_of_goods' => $totalCost,
                'gross_profit' => $grossProfit,
                'payment_status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'device_id' => $data['device_id'] ?? null,
                'sold_at' => $data['sold_at'] ?? now(),
            ]);

            // 4. Create items + reduce inventory
            foreach ($preparedItems as $line) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'product_sku' => $line['product']->sku,
                    'quantity' => $line['quantity'],
                    'unit_selling_price' => $line['unit_selling_price'],
                    'unit_cost_price' => $line['unit_cost_price'],
                    'discount_amount' => $line['discount_amount'],
                    'tax_amount' => '0',
                    'line_total' => $line['line_total'],
                    'line_cost' => $line['line_cost'],
                    'line_profit' => $line['line_profit'],
                ]);

                if ($line['product']->track_inventory && ! $line['product']->is_service) {
                    $this->inventoryService->move([
                        'business_id' => $data['business_id'],
                        'branch_id' => $data['branch_id'],
                        'product_id' => $line['product']->id,
                        'type' => 'sale',
                        'direction' => 'out',
                        'quantity' => $line['quantity'],
                        'unit_cost' => $line['unit_cost_price'],
                        'reference_type' => Sale::class,
                        'reference_id' => $sale->id,
                        'reference_number' => $saleNumber,
                        'user_id' => $data['cashier_id'],
                        'reason' => 'Sale',
                        'occurred_at' => $sale->sold_at,
                    ]);
                }
            }

            // 5. Record payment if provided.
            // Credit means the amount remains outstanding; no cash payment is recorded yet.
            if (! empty($data['payment'])) {
                if (($data['payment']['method'] ?? null) === 'credit') {
                    $sale->payment_status = 'credit';
                    $sale->save();
                } else {
                    $this->recordPayment($sale, $data['payment'], $data['cashier_id']);
                }
            }

            return $sale->load(['items', 'payments', 'customer', 'branch', 'cashier']);
        });
    }

    public function cancel(Sale $sale, string $userId): Sale
    {
        return DB::transaction(function () use ($sale, $userId) {
            $sale = Sale::where("id", $sale->id)->lockForUpdate()->firstOrFail();
            if ($sale->status === "cancelled") {
                throw ValidationException::withMessages(["sale" => ["Sale has already been cancelled."]]);
            }
            if ($sale->status !== "completed") {
                throw ValidationException::withMessages(["sale" => ["Only completed sales can be cancelled."]]);
            }
            $sale->load(["items.product"]);
            foreach ($sale->items as $item) {
                if ($item->product && $item->product->track_inventory && ! $item->product->is_service) {
                    $this->inventoryService->move([
                        "business_id" => $sale->business_id,
                        "branch_id" => $sale->branch_id,
                        "product_id" => $item->product_id,
                        "type" => "sale_return",
                        "direction" => "in",
                        "quantity" => $item->quantity,
                        "unit_cost" => $item->unit_cost_price,
                        "reference_type" => Sale::class,
                        "reference_id" => $sale->id,
                        "reference_number" => $sale->sale_number,
                        "user_id" => $userId,
                        "reason" => "Sale cancelled",
                        "occurred_at" => now(),
                    ]);
                }
            }
            $sale->status = "cancelled";
            $sale->save();
            return $sale->load(["items", "payments", "customer", "branch", "cashier"]);
        });
    }

    public function recordPayment(Sale $sale, array $paymentData, string $userId): SalePayment
    {
        $amount = $this->dec($paymentData['amount']);

        $payment = SalePayment::create([
            'sale_id' => $sale->id,
            'method' => $paymentData['method'],
            'amount' => $amount,
            'reference' => $paymentData['reference'] ?? null,
            'provider' => $paymentData['provider'] ?? null,
            'received_by' => $userId,
            'paid_at' => $paymentData['paid_at'] ?? now(),
        ]);

        $totalPaid = $this->dec($sale->payments()->sum('amount'));

        if (bccomp($totalPaid, (string) $sale->total, 4) >= 0) {
            $sale->payment_status = 'paid';
        } elseif (bccomp($totalPaid, '0', 4) > 0) {
            $sale->payment_status = 'partial';
        } else {
            $sale->payment_status = 'pending';
        }

        // Credit sales
        if (($paymentData['method'] ?? '') === 'credit') {
            $sale->payment_status = 'credit';
        }

        $sale->save();

        return $payment;
    }

    private function generateSaleNumber(string $businessId): string
    {
        $prefix = 'SL-' . strtoupper(substr(str_replace('-', '', $businessId), 0, 4));
        $date = now()->format('ymd');
        $random = strtoupper(Str::random(4));

        return "{$prefix}-{$date}-{$random}";
    }

    private function dec(string|float|int $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
