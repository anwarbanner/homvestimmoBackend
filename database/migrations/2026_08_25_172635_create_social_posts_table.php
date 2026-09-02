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
        Schema::create('social_posts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('property_id')->constrained()->onDelete('cascade');
    $table->string('platform'); // facebook, instagram
    $table->string('platform_post_id')->nullable(); // ID retourné par Meta API
    $table->enum('status', ['pending', 'published', 'failed'])->default('pending');
    $table->text('caption')->nullable();
    $table->timestamp('scheduled_at')->nullable();
    $table->timestamp('published_at')->nullable();
    $table->text('error_log')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
