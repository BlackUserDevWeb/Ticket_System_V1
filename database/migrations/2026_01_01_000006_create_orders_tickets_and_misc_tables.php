<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 32)->unique();
            $table->unsignedInteger('subtotal')->default(0);      // prix tickets (affiché)
            $table->unsignedInteger('platform_fee')->default(0);  // commission plateforme (interne)
            $table->unsignedInteger('total')->default(0);         // total payé
            $table->enum('payment_method', ['moov_money', 'mixx_by_yas'])->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->string('provider_transaction_id')->nullable()->index();
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'refunded'])->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['event_id', 'payment_status']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seat_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 64)->unique();       // contenu du QR code
            $table->string('qr_hash', 64)->unique();    // empreinte anti-contrefaçon
            $table->enum('status', ['valid', 'used', 'transferred', 'cancelled', 'refund_requested'])->default('valid');
            $table->boolean('autolist')->default(false);
            $table->unsignedInteger('autolist_price')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->index(['autolist', 'status']);
        });

        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scanned_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('offline')->default(false);
            $table->string('device_id')->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token', 64)->nullable()->index();
            $table->enum('status', ['pending', 'accepted', 'declined', 'expired'])->default('pending');
            $table->timestamps();
        });

        Schema::create('resales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('price');
            $table->boolean('autolist')->default(false);
            $table->enum('status', ['pending', 'active', 'sold', 'cancelled', 'rejected'])->default('pending');
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'autolist']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'event_id']);
        });

        Schema::create('price_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('target_price')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'event_id']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['notifications', 'settings', 'refunds', 'price_alerts', 'favorites', 'resales', 'transfers', 'check_ins', 'tickets', 'orders'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
