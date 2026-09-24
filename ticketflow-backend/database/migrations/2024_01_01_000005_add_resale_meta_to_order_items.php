<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les commandes issues de revente (Autolist) réutilisent la table order_items.
 * On y ajoute un champ JSON `meta` portant {ticket_id, resale_id} pour que
 * ResaleService@finalizePurchase retrouve le ticket échangé sans schéma dédié.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
