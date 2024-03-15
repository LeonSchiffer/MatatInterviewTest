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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->integer("number");
            $table->string("order_key");
            $table->string("status");
            $table->dateTime("date_created");
            $table->float("total");
            $table->integer("customer_id");
            $table->text("customer_note")->nullable();
            $table->json("billing")->nullable();
            $table->json("shipping")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
