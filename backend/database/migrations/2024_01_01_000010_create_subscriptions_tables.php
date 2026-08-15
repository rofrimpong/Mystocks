<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 15, 2)->default(0);
            $table->decimal('price_yearly', 15, 2)->default(0);
            $table->string('currency', 3)->default('GHS');
            $table->unsignedInteger('max_products')->nullable(); // null = unlimited
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_branches')->nullable();
            $table->boolean('has_reports')->default(true);
            $table->boolean('has_multi_branch')->default(false);
            $table->boolean('has_api_access')->default(false);
            $table->boolean('has_priority_support')->default(false);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('plan_id')->constrained('plans')->restrictOnDelete();
            $table->enum('status', ['trialing', 'active', 'past_due', 'cancelled', 'expired'])->default('trialing');
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index('current_period_ends_at');
        });

        Schema::create('platform_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('GHS');
            $table->enum('method', ['mobile_money', 'card', 'bank_transfer', 'manual', 'other']);
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->enum('status', ['pending', 'successful', 'failed', 'refunded'])->default('pending');
            $table->json('provider_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
