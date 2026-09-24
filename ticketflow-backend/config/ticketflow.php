<?php

/**
 * Configuration métier TicketFlow.
 * Centralise l'économie (frais/commissions), les paiements Mobile Money et les règles anti-fraude.
 * Tout est surchargeable via .env pour changer sans redéploiement de code.
 */
return [

    // Frais de service INTÉGRÉS au prix affiché (modèle "prix tout compris", inspiré de TickPick
    // mais avec frais inclus plutôt qu'absents). L'acheteur ne voit jamais le détail :
    // prix_affiché = round(prix_net * (1 + service_rate)).
    'service_rate' => (float) env('TF_SERVICE_RATE', 0.08),
    'tva_rate' => (float) env('TF_TVA_RATE', 0.18), // TVA togolaise, mentionnée dans les CGU

    // Taux de commission par défaut prélevé sur les ventes organisateur
    // (surchargé par subscription_plans.features.commission_rate).
    'commission_rate' => (float) env('TF_COMMISSION_RATE', 0.05),

    // Durée du hold temporaire de sièges pendant le tunnel d'achat (secondes).
    'hold_seconds' => (int) env('TF_HOLD_SECONDS', 300), // 5 minutes

    // Anti-spéculation : nombre max de transferts successifs d'un même ticket.
    'max_transfer_depth' => 2,

    // Score qualité/prix (Score Report à la TickPick) — pondérations normalisées.
    'scoring' => [
        'quality_weight' => 0.6,   // part de la qualité intrinsèque (section + siège)
        'price_weight' => 0.4,     // part du prix relatif vs médiane de l'événement
        'best_deal_min' => 8.0,    // seuil pour décrocher le badge "Meilleure offre"
    ],

    // Tarification dynamique : bornes globales de sécurité (le moteur reste dans [min, max] orga).
    'dynamic_pricing' => [
        'max_multiplier' => 1.35,      // jamais plus de +35 % vs prix de base
        'min_multiplier' => 0.85,      // jamais moins de −15 % (protège la marge organisateur)
        'fill_threshold_hot' => 0.75,  // taux de remplissage déclenchant une hausse
        'hours_before_hot' => 72,      // fenêtre "dernière minute" où la demande s'envole
    ],

    'payments' => [
        'moov_money' => [
            'base_url' => env('MOOV_BASE_URL', 'https://api.floozgroup.com'),
            'api_key' => env('MOOV_API_KEY'),
            'secret' => env('MOOV_SECRET'),
            'webhook_secret' => env('MOOV_WEBHOOK_SECRET'),
            'timeout_seconds' => 120, // timeout USSI : au-delà, la commande expire
        ],
        'mixx_yas' => [
            'base_url' => env('MIXX_BASE_URL', 'https://api.mixxbyyas.tg'),
            'api_key' => env('MIXX_API_KEY'),
            'secret' => env('MIXX_SECRET'),
            'webhook_secret' => env('MIXX_WEBHOOK_SECRET'),
            'timeout_seconds' => 120,
        ],
    ],

    // QR : préfixe des données encodées + longueur de signature HMAC tronquée.
    'qr' => [
        'payload_prefix' => 'TICKFLOW1',
        'signature_length' => 16,
    ],

    'notifications' => [
        'event_reminder_hours' => [24, 2], // rappels J-1 et H-2 avant l'événement
    ],
];
