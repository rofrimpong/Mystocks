<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('customer_id')->nullable(); // will add FK after customers table
            $table->string('sale_number')->nullable();
            $table->string('idempotency_key')->nullable(); // for offline sync
            $table->enum('status', ['draft', 'completed', 'cancelled', 'refunded', 'partially_refunded'])->default('completed');
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->decimal('cost_of_goods', 15, 4)->default(0); // sum of historical costs
            $table->decimal('gross_profit', 15, 4)->default(0);
            $table->enum('payment_status', ['pending', 'paid', 'partial', 'credit'])->default('paid');
            $table->text('notes')->nullable();
            $table->string('device_id')->nullable();
            $table->timestamp('sold_at');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'sale_number']);
            $table->unique(['business_id', 'idempotency_key']);
            $table->index(['business_id', 'branch_id', 'sold_at']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'payment_status']);
            $table->index('cashier_id');
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name'); // snapshot
            $table->string('product_sku')->nullable();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_selling_price', 15, 4); // historical
            $table->decimal('unit_cost_price', 15, 4);    // historical cost at time of sale
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('line_total', 15, 4);
            $table->decimal('line_cost', 15, 4);           // quantity * unit_cost_price
            $table->decimal('line_profit', 15, 4);
            $table->timestamps();

            $table->index('sale_id');
            $table->index('product_id');
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->enum('method', ['cash', 'mobile_money', 'card', 'bank_transfer', 'credit', 'other']);
            $table->decimal('amount', 15, 4);
            $table->string('reference')->nullable(); // MoMo transaction ID, etc.
            $table->string('provider')->nullable();  // MTN, Vodafone, etc.
            $table->foreignUuid('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
