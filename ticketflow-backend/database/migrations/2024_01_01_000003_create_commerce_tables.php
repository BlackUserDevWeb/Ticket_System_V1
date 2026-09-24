<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commandes, lignes de commande, paiements Mobile Money, tickets (QR), extras achetés.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();          // TF-2026-XXXXXX lisible dans les emails
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            // pending → paid | failed | expired (hold expiré) | refunded | partially_refunded
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'refunded', 'partially_refunded'])
                  ->default('pending')->index();
            // Sous-total = somme des prix NETS organisateur.
            $table->unsignedInteger('subtotal');
            // Frais de service TicketFlow : intégrés au prix AFFICHÉ (politique "prix tout compris").
            $table->unsignedInteger('service_fee');
            $table->unsignedInteger('extras_total')->default(0);
            $table->unsignedInteger('total');               // ce que l'acheteur paie réellement
            // Expiration du hold de sièges (5 min) — scannée par une commande planifiée.
            $table->timestamp('hold_expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['buyer_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_unit_id')->constrained('ticket_units');
            $table->foreignId('ticket_type_id')->constrained('ticket_types');
            // Prix unitaire net + part de frais appliquée à CETTE ligne (répartition exacte).
            $table->unsignedInteger('unit_price_net');
            $table->unsignedInteger('unit_fee');
            $table->unsignedTinyInteger('quantity')->default(1); // 1 pour du siège numéroté
            $table->timestamps();
            $table->index('order_id');
        });

        Schema::create('extras_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_extra_id')->constrained('event_extras');
            $table->unsignedTinyInteger('quantity');
            $table->unsignedInteger('unit_price');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Un seul fournisseur actif à la fois par commande ; plusieurs tentatives possibles.
            $table->enum('provider', ['moov_money', 'mixx_yas']);
            $table->string('payer_phone', 20);              // numéro +228 débité
            $table->unsignedInteger('amount');              // total encaissé (frais inclus)
            $table->unsignedInteger('fee');                 // part TicketFlow
            // initiated → pending (USSI push) → success | failed | expired | refunded
            $table->enum('status', ['initiated', 'pending', 'success', 'failed', 'expired', 'refunded'])->default('initiated')->index();
            $table->string('provider_reference')->nullable()->unique(); // id opérateur (idempotence webhook)
            $table->text('failure_reason')->nullable();
            // Payload brut du webhook : traçabilité complète en cas de litige.
            $table->json('webhook_payload')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ticket_unit_id')->constrained('ticket_units');
            // Code unique encodé dans le QR : UUID + secret HMAC vérifié au scan (anti-contrefaçon).
            $table->uuid('code')->unique();
            $table->string('qr_signature');
            // active → used (scanné) | transferred | refunded | cancelled
            $table->enum('status', ['active', 'used', 'transferred', 'refunded', 'cancelled'])->default('active')->index();
            // Profondeur de transfert : bridée par config pour lutter contre la spéculation.
            $table->unsignedTinyInteger('transfer_depth')->default(0);
            $table->timestamp('wallet_pass_updated_at')->nullable(); // spatie/laravel-mobile-pass
            $table->timestamps();
            $table->index(['owner_id', 'status']);
        });

        Schema::create('ticket_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_transfers');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('extras_order');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
