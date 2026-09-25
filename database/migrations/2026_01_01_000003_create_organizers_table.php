<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('phone', 20);
            $table->string('city')->default('Lome');
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('brand_color', 7)->default('#4f46e5');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('subscription_plan', 30)->default('starter');
            $table->timestamp('subscription_expires_at')->nullable();
            $table->timestamps();

            $table->unique('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizers');
    }
};
