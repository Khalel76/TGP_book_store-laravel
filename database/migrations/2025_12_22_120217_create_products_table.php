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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
    $table->string('name');
    $table->string('code')->nullable(); // Barcode or SKU
    $table->string('type'); // raw_material, porcelain, manufactured
    $table->integer('category_id');


    // Inventory & Pricing
    $table->double('quantity_in_stock')->default(0);
    $table->decimal('cost_price', 18, 2)->default(0);
    $table->decimal('selling_price', 18, 2)->default(0);

    // Flags
    $table->boolean('is_sellable')->default(false);
    $table->boolean('is_purchasable')->default(false);
    $table->boolean('is_manufactured')->default(false);

    // Specific Attributes (Nullable)
    $table->string('origin_country')->nullable(); // Porcelain
    $table->string('pattern')->nullable();        // Porcelain
    $table->double('thickness')->nullable();      // Porcelain
    $table->string('unit')->nullable();           // Raw Material (Meter, Piece
    $table->timestamps();

    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
