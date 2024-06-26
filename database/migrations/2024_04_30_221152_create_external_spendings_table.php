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
        Schema::create('external_spendings', function (Blueprint $table) {
            $table->id();
            $table->date('spending_date');
            $table->string('spending_desc')->nullable();
            $table->string('spending_type')->nullable();
            $table->boolean('is_operational')->nullable();  
            $table->decimal('spending_price', 10, 2); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('external_spendings');
    }
};
