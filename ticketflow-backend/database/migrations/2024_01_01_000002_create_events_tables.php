<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Événements, sections, types de billets, unités (sièges), extras et sponsors.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('category')->index();   // concert, sport, theatre, festival, conference, prive…
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            // Lineup / têtes d'affiche (artistes, équipes) — utilisé par l'autocomplétion et les reco.
            $table->json('lineup')->nullable();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('sales_open_at')->nullable();
            $table->timestamp('sales_close_at')->nullable();
            // Workflow de modération admin : draft → pending → published | rejected.
            $table->enum('status', ['draft', 'pending', 'published', 'rejected', 'cancelled', 'archived'])
                  ->default('draft')->index();
            $table->text('rejection_reason')->nullable();
            // Sponsoring : événement mis en avant tant que sponsored_until > now().
            $table->timestamp('sponsored_until')->nullable()->index();
            // Tarification dynamique activable par l'organisateur (plan Pro+).
            $table->boolean('dynamic_pricing_enabled')->default(false);
            // Charte graphique de la page événement (logo, couleurs) — plan Pro+.
            $table->json('branding')->nullable();
            $table->boolean('seated_viewing')->default(true); // false = placement libre (debout)
            $table->timestamps();
            $table->index(['status', 'starts_at']);
            $table->index(['category', 'status']);
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');                 // Orchestre, Balcon, Tribune Nord…
            // quality_score 0..100 : note intrinsèque de la section (vue, proximité scène)
            // alimentée par l'organisateur ; sert au calcul du Score TicketFlow qualité/prix.
            $table->unsignedTinyInteger('quality_score')->default(50);
            // Polyligne SVG du plan cliquable : {"points": [[x,y],...]}
            $table->json('shape')->nullable();
            // Photos 360° depuis cette section (viewer immersif Vanilla JS).
            $table->string('panorama_360_path')->nullable();
            $table->timestamps();
            $table->index('event_id');
        });

        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');                 // Early Bird, Standard, VIP, Loge
            // Prix NET organisateur en XOF. Le prix AFFICHÉ acheteur = net × (1 + service_rate).
            $table->unsignedInteger('base_price');
            // Plafond de tarification dynamique (le moteur ne dépasse jamais ce prix).
            $table->unsignedInteger('max_price')->nullable();
            $table->unsignedInteger('stock');
            $table->unsignedInteger('sold')->default(0);
            $table->unsignedInteger('per_user_limit')->default(6); // anti-spéculation
            // Règles dynamiques : { early_bird_until, demand_thresholds: [...] }
            $table->json('pricing_rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['event_id', 'name']);
        });

        Schema::create('ticket_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');                // ex. "A-12" (rangée A, siège 12)
            $table->string('row_number')->nullable();
            $table->unsignedSmallInteger('seat_number')->nullable();
            // Position sur le plan SVG (rendu temps réel côté React).
            $table->decimal('pos_x', 8, 2)->nullable();
            $table->decimal('pos_y', 8, 2)->nullable();
            // Note locale du siège (proximité scène/terrain) pour le score qualité/prix.
            $table->unsignedTinyInteger('view_score')->default(50);
            $table->unsignedInteger('price_override')->nullable(); // siège premium individuel
            // Machine à états : open → held (temporaire) → reserved (paiement en cours) → sold | void.
            $table->enum('status', ['open', 'held', 'reserved', 'sold', 'void'])->default('open');
            $table->timestamp('held_until')->nullable()->index();
            $table->timestamps();
            // Index critique : requête "tous les sièges ouverts d'une section" + verrou FOR UPDATE.
            $table->index(['event_id', 'status']);
            $table->index(['ticket_type_id', 'status']);
            $table->unique(['event_id', 'label']);
        });

        Schema::create('event_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');                 // Parking, Boisson VIP, Goodies…
            $table->unsignedInteger('price');       // XOF
            $table->unsignedInteger('stock');
            $table->unsignedInteger('sold')->default(0);
            $table->timestamps();
        });

        Schema::create('sponsorships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('budget');      // XOF payé pour la mise en avant
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->enum('status', ['pending', 'active', 'rejected', 'finished'])->default('pending')->index();
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsorships');
        Schema::dropIfExists('event_extras');
        Schema::dropIfExists('ticket_units');
        Schema::dropIfExists('ticket_types');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('events');
    }
};
