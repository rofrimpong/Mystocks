<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->nullable(); // FK later
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('purchase_number')->nullable();
            $table->string('supplier_invoice_number')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->enum('status', ['draft', 'received', 'cancelled', 'partially_returned'])->default('received');
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->enum('payment_status', ['pending', 'paid', 'partial', 'credit'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('purchased_at');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'purchase_number']);
            $table->unique(['business_id', 'idempotency_key']);
            $table->index(['business_id', 'branch_id', 'purchased_at']);
            $table->index(['business_id', 'status']);
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('line_total', 15, 4);
            $table->timestamps();

            $table->index('purchase_id');
            $table->index('product_id');
        });

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->enum('method', ['cash', 'mobile_money', 'card', 'bank_transfer', 'credit', 'other']);
            $table->decimal('amount', 15, 4);
            $table->string('reference')->nullable();
            $table->foreignUuid('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index('purchase_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
    }
};
