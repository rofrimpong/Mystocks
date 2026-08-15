<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('credit_limit', 15, 4)->default(0);
            $table->decimal('outstanding_balance', 15, 4)->default(0);
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'phone']);
            $table->index(['business_id', 'name']);
        });

        Schema::create('customer_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->enum('type', ['sale', 'payment', 'credit_note', 'adjustment', 'opening_balance']);
            $table->decimal('amount', 15, 4); // positive = increases balance (debt), negative = reduces
            $table->decimal('balance_after', 15, 4);
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['customer_id', 'occurred_at']);
            $table->index(['business_id', 'type']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('outstanding_balance', 15, 4)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'name']);
        });

        Schema::create('supplier_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->enum('type', ['purchase', 'payment', 'debit_note', 'adjustment', 'opening_balance']);
            $table->decimal('amount', 15, 4);
            $table->decimal('balance_after', 15, 4);
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['supplier_id', 'occurred_at']);
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->boolean('is_system')->default(false); // rent, utilities, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'slug']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUuid('category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 15, 4);
            $table->string('description')->nullable();
            $table->enum('payment_method', ['cash', 'mobile_money', 'card', 'bank_transfer', 'other'])->default('cash');
            $table->string('reference')->nullable();
            $table->string('attachment_path')->nullable();
            $table->date('expense_date');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'expense_date']);
            $table->index(['business_id', 'category_id']);
            $table->index(['business_id', 'branch_id']);
        });

        // Add missing foreign keys now that tables exist
        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
        });

        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('supplier_transactions');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customer_transactions');
        Schema::dropIfExists('customers');
    }
};
