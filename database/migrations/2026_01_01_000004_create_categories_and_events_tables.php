<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('artist')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->enum('status', ['draft', 'pending', 'published', 'cancelled', 'archived'])->default('draft');
            $table->boolean('is_sponsored')->default(false);
            $table->timestamp('sponsored_until')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->boolean('dynamic_pricing_enabled')->default(false);
            $table->json('seating_map')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index('is_sponsored');
            $table->index('artist');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
        Schema::dropIfExists('categories');
    }
};
