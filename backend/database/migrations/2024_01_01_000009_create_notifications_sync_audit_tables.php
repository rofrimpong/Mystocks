<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->nullable()->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('type'); // low_stock, sale_completed, payment_received, system, etc.
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['business_id', 'type']);
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('business_id')->nullable()->constrained('businesses')->cascadeOnDelete();
            $table->string('token');
            $table->string('platform', 20)->nullable(); // android, ios, web
            $table->string('device_name')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'token']);
        });

        // Offline sync tracking
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('key')->unique();
            $table->string('operation_type'); // sale, purchase, adjustment, etc.
            $table->uuid('resource_id')->nullable(); // resulting sale_id etc.
            $table->enum('status', ['processing', 'completed', 'failed', 'conflict'])->default('processing');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('device_id')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'operation_type']);
            $table->index('status');
        });

        Schema::create('sync_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('device_id');
            $table->string('operation_type');
            $table->string('idempotency_key');
            $table->enum('status', ['pending', 'synced', 'conflict', 'rejected', 'failed']);
            $table->json('payload');
            $table->json('server_result')->nullable();
            $table->text('conflict_reason')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('client_created_at');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'idempotency_key']);
            $table->index(['business_id', 'status']);
            $table->index(['device_id', 'status']);
        });

        // Immutable audit log
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // login, product.price_changed, inventory.adjusted, sale.cancelled, etc.
            $table->string('entity_type')->nullable();
            $table->uuid('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'action']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sync_operations');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('notifications');
    }
};
