<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->decimal('price_delta', 10, 2)->default(0)->after('attribute_value_id');
            $table->string('image')->nullable()->after('price_delta');
        });

        // Opcional: Podríamos removerlos de attribute_values si queremos que sean estrictamente por producto
        // Schema::table('attribute_values', function (Blueprint $table) {
        //     $table->dropColumn(['image', 'price_delta']);
        // });
    }

    public function down(): void
    {
        Schema::table('product_attribute_values', function (Blueprint $table) {
            $table->dropColumn(['price_delta', 'image']);
        });
    }
};
