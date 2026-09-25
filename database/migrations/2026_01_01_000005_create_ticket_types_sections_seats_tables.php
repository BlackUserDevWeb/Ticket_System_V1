<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('kind', ['early_bird', 'standard', 'vip', 'loge', 'other'])->default('standard');
            $table->unsignedInteger('base_price');          // FCFA, HT hors frais affichés
            $table->unsignedInteger('price_displayed');     // FCFA, TTC affiché acheteur (frais inclus)
            $table->unsignedInteger('quantity_total');
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->unsignedInteger('quantity_available')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('autolist_enabled')->default(false);
            $table->unsignedInteger('autolist_min_price')->nullable();
            $table->dateTime('sales_start_at')->nullable();
            $table->dateTime('sales_end_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'is_active']);
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('rows')->default(1);
            $table->unsignedInteger('cols')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->unsignedInteger('col_number');
            $table->foreignId('ticket_type_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['available', 'held', 'sold'])->default('available');
            $table->timestamp('held_until')->nullable();
            $table->string('hold_token', 64)->nullable()->index();
            $table->timestamps();

            $table->unique(['section_id', 'row_number', 'col_number']);
            $table->index(['status', 'held_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seats');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('ticket_types');
    }
};
