<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('city', 100)->nullable()->after('phone');
            $table->enum('role', ['buyer', 'organizer', 'support', 'admin'])->default('buyer')->after('city');
            $table->boolean('is_active')->default(true)->after('role');
            $table->text('bio')->nullable()->after('is_active');
            $table->string('avatar_path')->nullable()->after('bio');
            $table->timestamp('banned_at')->nullable()->after('avatar_path');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'city', 'role', 'is_active', 'bio', 'avatar_path', 'banned_at']);
        });
    }
};
