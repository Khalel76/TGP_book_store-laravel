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
        Schema::create('purchase_bills', function (Blueprint $table) {
            $table->id();
    $table->date('date');
    $table->string('invoice_number')->nullable(); // فاتورة المورد
    $table->integer('supplier_id');
    $table->decimal('total_amount', 18, 2)->default(0);
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_bills');
    }
};
