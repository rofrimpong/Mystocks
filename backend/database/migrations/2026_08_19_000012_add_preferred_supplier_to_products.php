<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignUuid('preferred_supplier_id')->nullable()->after('category_id')->constrained('suppliers')->nullOnDelete();
            $table->index(['business_id', 'preferred_supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['preferred_supplier_id']);
            $table->dropIndex(['business_id', 'preferred_supplier_id']);
            $table->dropColumn('preferred_supplier_id');
        });
    }
};
