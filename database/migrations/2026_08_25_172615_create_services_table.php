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
        Schema::create('services', function (Blueprint $table) {
    $table->id();
    $table->foreignId('property_id')->constrained();
    $table->foreignId('customer_id')->constrained(); // Le client qui souscrit
    $table->string('service_type'); // Ex: 'nettoyage', 'maintenance', 'gestion_syndic'
    $table->date('start_date');
    $table->date('end_date')->nullable();
    $table->decimal('monthly_fee', 10, 2)->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
