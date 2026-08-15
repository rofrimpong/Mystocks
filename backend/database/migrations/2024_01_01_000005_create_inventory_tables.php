<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fast current stock lookup
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 15, 4)->default(0);
            $table->decimal('reserved_quantity', 15, 4)->default(0); // for pending orders if needed
            $table->decimal('average_cost', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
            $table->index(['business_id', 'product_id']);
            $table->index(['business_id', 'branch_id']);
        });

        // Permanent immutable-style ledger
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('type', [
                'opening_stock',
                'purchase',
                'sale',
                'sale_return',
                'purchase_return',
                'adjustment',
                'transfer_in',
                'transfer_out',
                'damaged',
                'expired',
                'production',
                'other',
            ]);
            $table->enum('direction', ['in', 'out']);
            $table->decimal('quantity', 15, 4);
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->decimal('total_cost', 15, 4)->nullable();
            $table->string('reference_type')->nullable(); // e.g. App\Models\Sale
            $table->uuid('reference_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['business_id', 'branch_id', 'product_id']);
            $table->index(['business_id', 'type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_balances');
    }
};
