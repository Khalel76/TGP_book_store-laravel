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
        Schema::create('production_order_lines', function (Blueprint $table) {
            $table->id();
    $table->integer('production_order_id');
    $table->integer('product_id'); // المادة المسحوبة من المخزن
    $table->double('quantity_used');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_order_lines');
    }
};
