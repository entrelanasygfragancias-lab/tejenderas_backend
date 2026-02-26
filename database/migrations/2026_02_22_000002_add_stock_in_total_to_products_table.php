<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock_in_total')->default(0)->after('stock');
            $table->integer('stock_out_total')->default(0)->after('stock_in_total');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('stock_in_total');
            $table->dropColumn('stock_out_total');
        });
    }
};
