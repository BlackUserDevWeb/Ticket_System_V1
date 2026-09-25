<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('city', [
                'Lome', 'Kara', 'Sokode', 'Kpalime', 'Atakpame', 'Dapaong', 'Anhoin', 'Bassar',
                'Guerin-Kouka', 'Notsé', 'Aneho', 'Vogan', 'Tsevie', 'Tsévié', 'Sotouboua',
                'Pagouda', 'Mango', 'Djougou', 'Bafilo', 'Notse', 'Other',
            ])->default('Lome');
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->unsignedInteger('capacity')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
