<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('registration_number')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('country', 2)->default('GH');
            $table->string('currency', 3)->default('GHS');
            $table->string('timezone', 50)->default('Africa/Accra');
            $table->string('logo_path')->nullable();
            $table->enum('status', ['active', 'suspended', 'trial', 'cancelled'])->default('trial');
            $table->boolean('allow_negative_stock')->default(false);
            $table->boolean('multi_branch_enabled')->default(false);
            $table->json('settings')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('country');
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->foreignUuid('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('is_head_office')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'code']);
            $table->index(['business_id', 'status']);
        });

        Schema::create('business_user', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->boolean('is_owner')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'user_id']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_user');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('businesses');
    }
};
