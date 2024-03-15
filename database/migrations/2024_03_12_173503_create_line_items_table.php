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
        Schema::create('line_items', function (Blueprint $table) {
            $table->id("id");
            $table->foreignId("order_id");
            $table->string("name");
            $table->bigInteger("product_id");
            $table->bigInteger("variation_id");
            $table->integer("quantity");
            $table->string("tax_class")->nullable();
            $table->float("subtotal");
            $table->float("subtotal_tax");
            $table->float("total");
            $table->float("total_tax");
            $table->json("taxes")->nullable();
            $table->json("meta_data")->nullable();
            $table->string("sku")->nullable();
            $table->float("price");
            $table->json("image")->nullable();
            $table->string("parent_name")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('line_items');
    }
};
