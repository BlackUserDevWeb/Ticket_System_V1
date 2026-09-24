<?php

/*
| routes/console.php — commandes de console partagées.
| Par défaut vide : les jobs planifiés sont déclarés dans bootstrap/app.php
| (withSchedule) pour garder la lecture centralisée. On y loggue juste
| l'état du stock chaque nuit (supervision simple sans outil externe).
*/

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

// Rapport quotidien d'intégrité : holds non libérés, paiements en attente > 1 h.
Schedule::call(function () {
    $stuckOrders = \App\Models\Order::where('status', 'pending')
        ->where('created_at', '<', now()->subHour())
        ->count();
    if ($stuckOrders > 0) {
        Log::warning("TicketFlow: {$stuckOrders} commandes en attente depuis plus d'une heure.");
    }
})->dailyAt('03:00')->name('ticketflow:integrity-report');
