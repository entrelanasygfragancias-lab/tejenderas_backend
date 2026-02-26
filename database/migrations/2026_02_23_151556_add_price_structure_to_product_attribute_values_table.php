<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->decimal('base_price', 15, 2)->nullable()->after('attribute_value_id');
            $table->decimal('markup', 15, 2)->nullable()->after('base_price');
            $table->enum('markup_type', ['percentage', 'manual'])->default('percentage')->after('markup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->dropColumn(['base_price', 'markup', 'markup_type']);
        });
    }
};
