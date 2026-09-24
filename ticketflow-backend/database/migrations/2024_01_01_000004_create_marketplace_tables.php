<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revente (Autolist), favoris, alertes prix, notifications, check-in offline, modération, analytics.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            // Prix demandé fixé par le vendeur. En mode Autolist, la vente s'exécute automatiquement
            // dès qu'un acheteur se présente à ce prix (ou moins) — sans intervention du vendeur.
            $table->unsignedInteger('ask_price');
            $table->boolean('autolist')->default(true);
            // listed → sold | cancelled | withdrawn (retiré après mise en vente d'un ticket transféré)
            $table->enum('status', ['listed', 'sold', 'cancelled'])->default('listed')->index();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            // Recherche marketplace revente : tri par prix sur un événement donné.
            $table->index(['event_id', 'status', 'ask_price']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('favoritable'); // Event ou entité "artiste/équipe" (via name ci-dessous)
            $table->string('name')->nullable();    // ex. "Aymric", "Eperviers du Togo"
            $table->timestamps();
            $table->unique(['user_id', 'favoritable_type', 'favoritable_id'], 'favorites_unique_idx');
        });

        Schema::create('price_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('target_price');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('triggered_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'event_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // types : reminder_event, price_drop, resale_available, order_paid, transfer_request…
            $table->string('type')->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('link')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scanned_by')->constrained('users')->cascadeOnDelete();
            $table->string('gate')->default('principale');
            // Offline-first : le scanner local enregistre puis synchronise. synced=false tant que
            // l'enregistrement n'a pas été accepté par l'API (dédupliqué côté serveur par ticket_id).
            $table->boolean('synced')->default(true)->index();
            $table->timestamp('scanned_at');
            $table->timestamps();
            // Un seul check-in valide par ticket (contrôle d'intégrité post-sync).
            $table->unique('ticket_id');
        });

        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['refund_request', 'fraud', 'event_cancelled', 'ticket_invalid', 'other']);
            $table->text('description');
            // open → investigating → resolved_refund | resolved_rejected
            $table->enum('status', ['open', 'investigating', 'resolved_refund', 'resolved_rejected'])->default('open')->index();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        Schema::create('event_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable();
            // Source d'acquisition : direct, recherche, reseau_social, parrainage… (analytics orga)
            $table->string('source')->default('direct');
            $table->timestamp('viewed_at');
            $table->index(['event_id', 'viewed_at']);
        });

        // Registre comptable plateforme : commission, sponsoring, abonnements, remboursements.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // débiteur/créditeur
            $table->enum('kind', ['commission', 'service_fee', 'subscription', 'sponsorship', 'payout', 'refund']);
            $table->integer('amount_xof'); // signé : + recette TicketFlow, − reversement
            $table->nullableMorphs('subject'); // Payment | Subscription | Sponsorship
            $table->string('description');
            $table->timestamps();
            $table->index(['kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('event_views');
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('check_ins');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('price_alerts');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('resales');
    }
};
