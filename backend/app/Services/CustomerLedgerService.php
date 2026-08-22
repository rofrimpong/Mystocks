<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerLedgerService
{
    public function recordCreditSale(Customer $customer, Sale $sale, string $userId): CustomerTransaction
    {
        return DB::transaction(function () use ($customer, $sale, $userId) {
            $customer = Customer::where('business_id', $sale->business_id)
                ->where('id', $customer->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = CustomerTransaction::where('business_id', $sale->business_id)
                ->where('type', 'sale')
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->first();
            if ($existing) {
                return $existing;
            }

            $amount = $this->decimal($sale->total);
            $newBalance = bcadd((string) $customer->outstanding_balance, $amount, 4);
            if (bccomp((string) $customer->credit_limit, '0', 4) > 0
                && bccomp($newBalance, (string) $customer->credit_limit, 4) > 0) {
                throw ValidationException::withMessages([
                    'customer_id' => ['This sale would exceed the customer credit limit.'],
                ]);
            }

            $transaction = CustomerTransaction::create([
                'business_id' => $sale->business_id,
                'customer_id' => $customer->id,
                'type' => 'sale',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_type' => Sale::class,
                'reference_id' => $sale->id,
                'notes' => 'Credit sale '.$sale->sale_number,
                'created_by' => $userId,
                'occurred_at' => $sale->sold_at ?? now(),
            ]);

            $customer->update(['outstanding_balance' => $newBalance]);
            return $transaction;
        });
    }

    public function reverseCreditSale(Sale $sale, string $userId): ?CustomerTransaction
    {
        if (! $sale->customer_id) {
            return null;
        }

        return DB::transaction(function () use ($sale, $userId) {
            $customer = Customer::where('business_id', $sale->business_id)
                ->where('id', $sale->customer_id)
                ->lockForUpdate()
                ->firstOrFail();
            $original = CustomerTransaction::where('business_id', $sale->business_id)
                ->where('type', 'sale')
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->first();
            if (! $original) {
                return null;
            }
            $existing = CustomerTransaction::where('business_id', $sale->business_id)
                ->where('type', 'credit_note')
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->first();
            if ($existing) {
                return $existing;
            }

            if (bccomp((string) $customer->outstanding_balance, (string) $original->amount, 4) < 0) {
                throw ValidationException::withMessages([
                    'sale' => ['This credit sale has repayments applied to it. Record the required refund or balance adjustment before cancelling it.'],
                ]);
            }

            $amount = bcsub('0', (string) $original->amount, 4);
            $newBalance = bcadd((string) $customer->outstanding_balance, $amount, 4);

            $transaction = CustomerTransaction::create([
                'business_id' => $sale->business_id,
                'customer_id' => $customer->id,
                'type' => 'credit_note',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_type' => Sale::class,
                'reference_id' => $sale->id,
                'notes' => 'Cancelled credit sale '.$sale->sale_number,
                'created_by' => $userId,
                'occurred_at' => now(),
            ]);

            $customer->update(['outstanding_balance' => $newBalance]);
            return $transaction;
        });
    }

    public function recordPayment(Customer $customer, array $data, string $userId): CustomerTransaction
    {
        return $this->post($customer, [
            'type' => 'payment',
            'amount' => bcsub('0', $this->decimal($data['amount']), 4),
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? 'Customer repayment',
            'occurred_at' => $data['occurred_at'] ?? now(),
        ], $userId, false);
    }

    public function recordOpeningBalance(Customer $customer, array $data, string $userId): CustomerTransaction
    {
        if ($customer->transactions()->exists() || bccomp((string) $customer->outstanding_balance, '0', 4) !== 0) {
            throw ValidationException::withMessages([
                'amount' => ['Opening balance can only be recorded before the customer has ledger activity.'],
            ]);
        }
        return $this->post($customer, [
            'type' => 'opening_balance',
            'amount' => $this->decimal($data['amount']),
            'notes' => $data['notes'] ?? 'Opening balance',
            'occurred_at' => $data['occurred_at'] ?? now(),
        ], $userId, true);
    }

    public function recordAdjustment(Customer $customer, array $data, string $userId): CustomerTransaction
    {
        $amount = $this->decimal($data['amount']);
        if ($data['direction'] === 'decrease') {
            $amount = bcsub('0', $amount, 4);
        }
        return $this->post($customer, [
            'type' => 'adjustment',
            'amount' => $amount,
            'notes' => $data['notes'],
            'occurred_at' => $data['occurred_at'] ?? now(),
        ], $userId, $data['direction'] === 'increase');
    }

    private function post(Customer $customer, array $data, string $userId, bool $canIncrease): CustomerTransaction
    {
        return DB::transaction(function () use ($customer, $data, $userId, $canIncrease) {
            $customer = Customer::where('business_id', $customer->business_id)
                ->where('id', $customer->id)
                ->lockForUpdate()
                ->firstOrFail();
            $newBalance = bcadd((string) $customer->outstanding_balance, $data['amount'], 4);
            if (bccomp($newBalance, '0', 4) < 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Amount cannot be greater than the outstanding balance.'],
                ]);
            }
            if ($canIncrease && bccomp((string) $customer->credit_limit, '0', 4) > 0
                && bccomp($newBalance, (string) $customer->credit_limit, 4) > 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Amount would exceed the customer credit limit.'],
                ]);
            }
            $transaction = CustomerTransaction::create(array_merge($data, [
                'business_id' => $customer->business_id,
                'customer_id' => $customer->id,
                'balance_after' => $newBalance,
                'created_by' => $userId,
            ]));
            $customer->update(['outstanding_balance' => $newBalance]);
            return $transaction;
        });
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }
}
