<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchasePayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        private readonly InventoryService $inventoryService
    ) {}

    /**
     * Create a purchase, its items, optionally record payment, and increase inventory.
     *
     * @param  array{
     *     business_id: string,
     *     branch_id: string,
     *     supplier_id?: string|null,
     *     created_by: string,
     *     supplier_invoice_number?: string|null,
     *     notes?: string|null,
     *     purchased_at?: string|null,
     *     discount_amount?: string|float,
     *     tax_amount?: string|float,
     *     idempotency_key?: string|null,
     *     items: array<int, array{product_id: string, quantity: string|float, unit_cost: string|float, discount_amount?: string|float}>,
     *     payment?: array{method: string, amount: string|float, reference?: string|null}|null,
     * }  $data
     */
    public function create(array $data): Purchase
    {
        return DB::transaction(function () use ($data) {
            // Idempotency check
            if (! empty($data['idempotency_key'])) {
                $existing = Purchase::where('business_id', $data['business_id'])
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->first();
                if ($existing) {
                    return $existing->load(['items', 'payments', 'supplier', 'branch']);
                }
            }

            if (empty($data['items'])) {
                throw ValidationException::withMessages([
                    'items' => ['At least one item is required.'],
                ]);
            }

            $subtotal = '0';
            $lineItems = [];

            foreach ($data['items'] as $item) {
                $product = Product::where('business_id', $data['business_id'])
                    ->where('id', $item['product_id'])
                    ->firstOrFail();

                $qty = $this->dec($item['quantity']);
                $unitCost = $this->dec($item['unit_cost']);
                $lineDiscount = $this->dec($item['discount_amount'] ?? 0);
                $lineTotal = bcsub(bcmul($qty, $unitCost, 4), $lineDiscount, 4);

                if (bccomp($lineTotal, '0', 4) < 0) {
                    throw ValidationException::withMessages([
                        'items' => ["Line total cannot be negative for product {$product->name}."],
                    ]);
                }

                $subtotal = bcadd($subtotal, $lineTotal, 4);

                $lineItems[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'discount_amount' => $lineDiscount,
                    'line_total' => $lineTotal,
                ];
            }

            $discountAmount = $this->dec($data['discount_amount'] ?? 0);
            $taxAmount = $this->dec($data['tax_amount'] ?? 0);
            $total = bcadd(bcsub($subtotal, $discountAmount, 4), $taxAmount, 4);

            $purchaseNumber = $this->generatePurchaseNumber($data['business_id']);

            $purchase = Purchase::create([
                'business_id' => $data['business_id'],
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'created_by' => $data['created_by'],
                'purchase_number' => $purchaseNumber,
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'status' => 'received',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'payment_status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'purchased_at' => $data['purchased_at'] ?? now(),
            ]);

            foreach ($lineItems as $line) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $line['product']->id,
                    'product_name' => $line['product']->name,
                    'product_sku' => $line['product']->sku,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'discount_amount' => $line['discount_amount'],
                    'tax_amount' => '0',
                    'line_total' => $line['line_total'],
                ]);

                // Increase inventory via the inventory engine
                $this->inventoryService->move([
                    'business_id' => $data['business_id'],
                    'branch_id' => $data['branch_id'],
                    'product_id' => $line['product']->id,
                    'type' => 'purchase',
                    'direction' => 'in',
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'reference_type' => Purchase::class,
                    'reference_id' => $purchase->id,
                    'reference_number' => $purchaseNumber,
                    'user_id' => $data['created_by'],
                    'reason' => 'Purchase received',
                    'occurred_at' => $purchase->purchased_at,
                ]);

                // Optionally update product buying_price to latest
                $line['product']->update(['buying_price' => $line['unit_cost']]);
            }

            // Optional payment at creation time
            if (! empty($data['payment'])) {
                $this->recordPayment($purchase, $data['payment'], $data['created_by']);
            }

            return $purchase->load(['items', 'payments', 'supplier', 'branch']);
        });
    }

    public function recordPayment(Purchase $purchase, array $paymentData, string $userId): PurchasePayment
    {
        $amount = $this->dec($paymentData['amount']);

        $payment = PurchasePayment::create([
            'purchase_id' => $purchase->id,
            'method' => $paymentData['method'],
            'amount' => $amount,
            'reference' => $paymentData['reference'] ?? null,
            'paid_by' => $userId,
            'paid_at' => $paymentData['paid_at'] ?? now(),
        ]);

        // Recalculate payment status
        $totalPaid = $purchase->payments()->sum('amount');
        $totalPaid = $this->dec($totalPaid);

        if (bccomp($totalPaid, (string) $purchase->total, 4) >= 0) {
            $purchase->payment_status = 'paid';
        } elseif (bccomp($totalPaid, '0', 4) > 0) {
            $purchase->payment_status = 'partial';
        } else {
            $purchase->payment_status = 'pending';
        }
        $purchase->save();

        return $payment;
    }

    private function generatePurchaseNumber(string $businessId): string
    {
        $prefix = 'PO-' . strtoupper(substr(str_replace('-', '', $businessId), 0, 4));
        $date = now()->format('ymd');
        $random = strtoupper(Str::random(4));

        return "{$prefix}-{$date}-{$random}";
    }

    private function dec(string|float|int $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
