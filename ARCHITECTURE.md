# TicketFlow — Architecture globale & modèle de données

Plateforme de billetterie événementielle pour le Togo. Devise : XOF (FCFA). Langue : français.
Modèle de référence : TickPick (marketplace transparente, score qualité/prix, Autolist).

## 1. Vue d'ensemble

```
┌─────────────────────────────────────────────────────────────────────┐
│                        CLIENTS (navigateurs)                        │
│  ┌───────────────┐  ┌────────────────────┐  ┌────────────────────┐  │
│  │ React 19 +    │  │ Angular 19         │  │ Vanilla JS         │  │
│  │ Vite          │  │ (Dashboard Orga +  │  │ (Scanner QR        │  │
│  │ (Marketplace  │  │  Back-office       │  │  offline, Viewer   │  │
│  │  publique)    │  │  Admin)            │  │  360°, micro-      │  │
│  │               │  │                    │  │  interactions)     │  │
│  └───────┬───────┘  └─────────┬──────────┘  └─────────┬──────────┘  │
│          │         Design system partagé (tokens CSS) │             │
└──────────┼────────────────────┼───────────────────────┼─────────────┘
           │ HTTPS / JSON:API   │                        │ WebSocket (Laravel Reverb)
┌──────────▼────────────────────▼───────────────────────▼─────────────┐
│                     LARAVEL 11 API RESTful (/api/v1)                │
│  Sanctum (auth token) · Form Requests · Resources · Policies        │
│  Rate limiting · Transactions + verrous atomiques (anti-overselling)│
│  ┌─────────────┐ ┌──────────────┐ ┌────────────┐ ┌───────────────┐  │
│  │ Payment     │ │ QrCode       │ │ Scoring    │ │ DynamicPricing│  │
│  │ Services    │ │ Service      │ │ Service    │ │ Engine        │  │
│  │ (Moov/Flooz,│ │ (endroid)    │ │ (Score/10) │ │ (demande,     │  │
│  │  Mixx by Yas│ │              │ │            │ │  remplissage) │  │
│  └──────┬──────┘ └──────────────┘ └────────────┘ └───────────────┘  │
│         │ Webhooks                                                  │
├─────────┼───────────────────────────────────────────────────────────┤
│  MySQL 8 │ Redis (cache + holds 5 min + files) │ Queue (emails/notif)│
└──────────┴───────────────────────────────────────────────────────────┘
           │
   Mobile Money agrégateurs (API externes Moov Money / Flooz, Mixx by Yas)
```

### Justifications des choix
- **React 19** pour la marketplace : SEO (possibilité de pré-rendu), écosystème riche
  (jsqr, gl-react pour les plans de salle), performance perçue sur mobile (marché togolais = mobile-first).
- **Angular 19** pour les dashboards : reactive forms massifs (formulaire multi-étapes de création
  d'événement), ag-grid/tanstack-like tables, DI et gestion d'état structurée (signals), typage strict.
- **Vanilla JS** pour le scanner QR : doit tourner avec jsQR + IndexedDB sans framework, être embarqué
  dans n'importe quelle page (React ou Angular) et fonctionner **offline** (cache local des tickets valides).
- **Laravel Reverb** (plutôt que Pusher) : auto-hébergé, coût zéro en XOF, mêmes canaux privés.
- **Sanctum** : tokens Bearer simples pour SPA multi-clients ; pas de cookies cross-domain à gérer.

## 2. Modèle de données (diagramme entité-relation textuel)

```
users ──┬──< events >── venues ── venues_cities(cities)
        │        │
        │        ├──< ticket_types ──< ticket_units (sièges numérotés, statut)
        │        ├──< sections (plan de salle)
        │        ├──< event_extras (parking, boissons…)
        │        ├──< sponsorships (campagnes sponsorisées)
        │        └──< event_views / analytics
        │
        ├──< orders ──< order_items ──> ticket_units
        │       │            │
        │       │            └──< tickets (QR unique, statut: active/used/transferred/refunded)
        │       ├── payments (MobileMoney: moov|mixx, statut, webhook payload)
        │       └── invoices
        │
        ├──< resales (Autolist : prix fixé, auto-exécution)
        ├──< favorites (bouton cœur artiste/équipe/événement)
        ├──< price_alerts
        ├──< notifications
        │
organizer_profiles ──< subscriptions >── subscription_plans (Starter/Pro/Enterprise)
admins/support = users.role
check_ins (scan entrées, synchronisable offline)
transactions_ledger (commission TicketFlow, payouts organisateur)
```

### Tables principales et index clés

| Table | Colonnes notables | Index / contrainte |
|---|---|---|
| `users` | role(buyer/organizer/admin/support), phone (+228…), preferences json | idx(role), uniq(email) |
| `venues` | nom, ville, lat/lng, plan_svg json, capacity | idx(city_id, capacity) |
| `events` | organizer_id, venue_id, category, starts_at, status(draft/pending/published/rejected/cancelled), sponsored_until, dynamic_pricing bool, branding json | idx(status, starts_at), idx(category), fulltext(name,description) |
| `ticket_types` | event_id, name(EarlyBird/VIP…), base_price (F CFA int), stock, sold, per_user_limit, pricing_rules json | uniq(event_id,name) |
| `ticket_units` | event_id, ticket_type_id, section_id, label, row, seat, price_override, status(open/held/reserved/sold/void), held_until | idx(event_id,status), **row lock via SELECT…FOR UPDATE** |
| `orders` | buyer_id, event_id, status(pending/paid/failed/expired/refunded), subtotal, service_fee(transparent: intégré affiché), total, hold_expires_at | idx(status, hold_expires_at) |
| `payments` | order_id, provider(moov_money/mixx_yas), provider_ref, amount, fee, status(initiated/pending/success/failed), webhook_payload json | uniq(provider_ref), idx(order_id) |
| `tickets` | order_item_id, unit_code UUID, qr_secret, status(active/used/transferred/refunded), transfer_depth | uniq(unit_code) |
| `resales` | ticket_id, seller_id, ask_price, autolist bool, status(listed/sold/cancelled), buyer_id | idx(status, ask_price) |
| `subscription_plans` | slug(starter/pro/enterprise), price_monthly, max_events, features json | |
| `subscriptions` | organizer_id, plan_id, status, renews_at | idx(organizer_id,status) |
| `sponsorships` | event_id, admin_id?, budget, starts_at, ends_at, status(pending/active/finished), impressions, clicks | idx(status) |
| `holds` (Redis, pas SQL) | `{order_id} -> [unit_ids]`, TTL 300 s | |
| `price_alerts`, `favorites`, `notifications`, `check_ins`, `event_extras`, `extras_order` | … | |

### Règles métier intégrées au schéma
- **Frais de service « intégrés »** : `service_rate` en config ; le prix affiché = prix net organisateur
  + frais. La table `payments` journalise `amount` (payé) et `fee` (part TicketFlow) → reversement = amount − fee.
- **Commission** : paramétrable par plan d'abonnement (`subscription_plans.features.commission_rate`).
- **Anti-overselling** : `ticket_units.status` + transaction SQL avec `lockForUpdate()` + hold Redis TTL 5 min.
- **Traçabilité transfert** : chaque `TicketTransfer` horodaté, `transfer_depth` limité (anti-spéculation).
- **Offline check-in** : le scanner télécharge un lot de `ticket_units` valides signés (HMAC) vers IndexedDB ;
  `check_ins` possède `synced bool` pour la remontée différée.

## 3. Endpoints API (extrait, /api/v1)

Public : `GET /events`, `GET /events/{slug}`, `GET /events/{id}/sections`, `GET /events/{id}/units`,
`GET /search/suggest`, `POST /checkout/hold`, `POST /checkout/order`, `POST /payment/{provider/initiate}`,
`POST /webhooks/{provider}` (sans auth, signature vérifiée).

Acheteur (auth) : `GET /me/orders`, `GET /me/tickets`, `POST /tickets/{code}/transfer`, `POST /resales` (Autolist),
`GET/POST /me/alertes-prix`, `GET /me/recommendations`.

Organisateur : `POST /organizer/events` (multi-étapes), `PATCH /events/{id}/pricing`, `GET /organizer/events/{id}/sales/stats`,
`GET /organizer/events/{id}/participants.csv`, `POST /checkin` (scan), `GET /organizer/subscription`.

Admin : `GET /admin/events?status=pending`, `POST /admin/events/{id}/approve|reject`, CRUD users/plans/sponsorships,
`GET /admin/disputes`, `POST /admin/refunds/{payment}`.

## 4. Dépôts

- `ticketflow-backend/` — Laravel 11, Sanctum, Reverb, PestPHP, Docker.
- `ticketflow-frontend/` — React 19 (Vite) + Angular 19 (org-admin) + Vanilla scripts + design-system partagé + GitHub Actions.
