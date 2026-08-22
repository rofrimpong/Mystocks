<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Sale;
use App\Models\User;
use App\Services\CustomerLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerCreditLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_sale_payment_and_cancellation_keep_customer_balance_consistent(): void
    {
        [$user, $business, $branch, $customer] = $this->ledgerFixture();
        $sale = $this->sale($business, $branch, $user, $customer, '125.0000');
        $ledger = app(CustomerLedgerService::class);

        $ledger->recordCreditSale($customer, $sale, $user->id);
        $this->assertSame('125.0000', (string) $customer->fresh()->outstanding_balance);

        // Replaying the same sale must not duplicate customer debt.
        $ledger->recordCreditSale($customer, $sale, $user->id);
        $this->assertSame(1, CustomerTransaction::where('reference_id', $sale->id)->where('type', 'sale')->count());

        $ledger->recordPayment($customer, ['amount' => 25, 'payment_method' => 'cash'], $user->id);
        $this->assertSame('100.0000', (string) $customer->fresh()->outstanding_balance);

        $this->expectException(ValidationException::class);
        $ledger->reverseCreditSale($sale, $user->id);
    }

    public function test_unpaid_credit_sale_can_be_reversed_once(): void
    {
        [$user, $business, $branch, $customer] = $this->ledgerFixture();
        $sale = $this->sale($business, $branch, $user, $customer, '80.0000');
        $ledger = app(CustomerLedgerService::class);

        $ledger->recordCreditSale($customer, $sale, $user->id);
        $ledger->reverseCreditSale($sale, $user->id);
        $ledger->reverseCreditSale($sale, $user->id);

        $this->assertSame('0.0000', (string) $customer->fresh()->outstanding_balance);
        $this->assertSame(1, CustomerTransaction::where('reference_id', $sale->id)->where('type', 'credit_note')->count());
    }

    public function test_credit_limit_and_overpayment_are_rejected(): void
    {
        [$user, $business, $branch, $customer] = $this->ledgerFixture('50.0000');
        $ledger = app(CustomerLedgerService::class);

        try {
            $ledger->recordCreditSale($customer, $this->sale($business, $branch, $user, $customer, '60.0000'), $user->id);
            $this->fail('Credit limit should have been enforced.');
        } catch (ValidationException) {
            $this->assertSame('0.0000', (string) $customer->fresh()->outstanding_balance);
        }

        $sale = $this->sale($business, $branch, $user, $customer, '40.0000');
        $ledger->recordCreditSale($customer, $sale, $user->id);
        $this->expectException(ValidationException::class);
        $ledger->recordPayment($customer, ['amount' => 41, 'payment_method' => 'cash'], $user->id);
    }

    private function ledgerFixture(string $creditLimit = '0.0000'): array
    {
        $user = User::create(['name' => 'Ledger Owner', 'email' => Str::uuid().'@example.test', 'password' => 'TestPassword123!']);
        $business = Business::create(['name' => 'Ledger Shop', 'status' => 'active', 'plan' => 'business']);
        $branch = Branch::create(['business_id' => $business->id, 'name' => 'Main', 'code' => 'MAIN', 'status' => 'active', 'is_head_office' => true]);
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Credit Customer', 'status' => 'active', 'credit_limit' => $creditLimit, 'outstanding_balance' => 0]);
        return [$user, $business, $branch, $customer];
    }

    private function sale(Business $business, Branch $branch, User $user, Customer $customer, string $total): Sale
    {
        return Sale::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'cashier_id' => $user->id,
            'customer_id' => $customer->id, 'sale_number' => 'SALE-'.Str::upper(Str::random(8)),
            'status' => 'completed', 'subtotal' => $total, 'discount_amount' => 0, 'tax_amount' => 0,
            'total' => $total, 'cost_of_goods' => 0, 'gross_profit' => $total,
            'payment_status' => 'credit', 'sold_at' => now(),
        ]);
    }
}
