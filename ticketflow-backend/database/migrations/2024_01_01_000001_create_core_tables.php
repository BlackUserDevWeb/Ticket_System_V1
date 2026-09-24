<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tables de base : utilisateurs, villes, lieux, plans d'abonnement et abonnements.
 * Les montants sont stockés en entiers XOF (jamais de flottants → zéro erreur d'arrondi sur le FCFA).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            // Format togolais : +228XXXXXXXX — unique car c'est l'identifiant Mobile Money.
            $table->string('phone', 20)->unique()->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            // Rôles : acheteur, organisateur, admin, support.
            $table->enum('role', ['buyer', 'organizer', 'admin', 'support'])->default('buyer')->index();
            $table->boolean('is_active')->default(true);
            // Préférences UI/langue/localisation — JSON pour éviter une migration à chaque nouveau réglage.
            $table->json('preferences')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');               // Lomé, Kara, Sokodé…
            $table->string('region')->index();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('capacity');
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            // Plan de salle vectoriel (sections + sièges) stocké en JSON : flexible sans schéma rigide.
            $table->json('layout')->nullable();
            $table->timestamps();
            $table->index(['city_id', 'capacity']);
        });

        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();      // starter | pro | enterprise
            $table->string('name');
            $table->unsignedInteger('price_monthly'); // XOF / mois
            $table->unsignedInteger('max_events');    // 0 = illimité
            // features : { commission_rate: 0.05, dynamic_pricing: true, branding: true, analytics: "basic" }
            $table->json('features');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans');
            $table->enum('status', ['trialing', 'active', 'past_due', 'cancelled'])->default('trialing')->index();
            $table->timestamp('renews_at')->index();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['organizer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('venues');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('users');
    }
};
