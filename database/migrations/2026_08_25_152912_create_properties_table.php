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
    Schema::create('properties', function (Blueprint $table) {
    $table->id();
    $table->string('reference')->unique();
    $table->string('title');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->enum('type', ['apartment', 'villa', 'house', 'office', 'land', 'commercial', 'other']);
    $table->enum('transaction_type', ['sale', 'rent']);
    $table->decimal('price', 15, 2);
    $table->integer('surface');
    $table->integer('bedrooms')->default(0);
    $table->integer('bathrooms')->default(0);
    $table->string('address');
    $table->string('city');
    $table->decimal('latitude', 10, 8)->nullable();
    $table->decimal('longitude', 11, 8)->nullable();
    $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
    $table->boolean('featured')->default(false);
    $table->timestamp('published_at')->nullable();
    $table->timestamps();
});
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
