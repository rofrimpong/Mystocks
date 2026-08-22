<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 20)
                ->nullable()
                ->unique()
                ->after('phone');
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('referrer_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignUuid('referred_user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('referral_code', 20);

            $table->string('status', 30)->default('registered');

            $table->timestamp('qualified_at')->nullable();
            $table->timestamp('rewarded_at')->nullable();

            $table->timestamps();

            $table->index(['referrer_id', 'status']);
            $table->index('referral_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['referral_code']);
            $table->dropColumn('referral_code');
        });
    }
};
