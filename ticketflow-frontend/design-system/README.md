# TicketFlow — Design System

Source de vérité unique des 3 fronts (React marketplace, Angular organisateur, Vanilla scanner).

## Fichiers
- `tokens.css` — **la** source de vérité : variables CSS (`--tf-*`), base typographique, composants utilitaires (`tf-btn`, `tf-card`, `tf-badge`, `tf-modal`, `tf-skeleton`, `tf-tabbar`…). Importé par les 3 apps.
- `index.html` — style guide visuel ouvert dans l'outil de scan Storybook-like local (couleurs, typo, composants, icônes).
- `icons.svg` — sprite SVG (24×24, stroke 1.8, courants `currentColor`).

## Icônes (sprite `<use href="icons.svg#i-nom">`)
`i-ticket i-calendar i-map-pin i-search i-heart i-user i-wallet i-qr i-check i-x i-alert i-megaphone i-chart i-settings i-arrow-left i-arrow-right i-star i-plus i-minus i-clock i-shield i-gift i-camera i-chat i-money i-transfer i-print i-zap i-eye i-lock`

## Couleurs sémantiques
| Token | Light | Dark | Usage |
|---|---|---|---|
| `--tf-accent` | #6d5ef8 | #6d5ef8 | Actions principales |
| `--tf-success` | #178a50 | #34c37a | Confirmation paiement/scan |
| `--tf-warning` | #b45309 | #e0a34a | Hold expirant, stock faible |
| `--tf-danger` | #c0262e | #f0555e | Erreur, refus d'accès |

## Règles
- Pas de glassmorphisme, pas d'ombres multiples ; Inter uniquement ; devise FCFA formatée `12 500 FCFA`.
- Mobile-first 375px : barre d'onglets bas d'écran sur la marketplace (<768px).
- Dark mode : `[data-theme]` forcé par script anti-flash ; sinon `prefers-color-scheme`.
- Accessibilité WCAG 2.2 AA : focus visible, ARIA sur tous les composants interactifs, `prefers-reduced-motion`.

## Cohérence inter-apps
Un petit serveur Node (`tools/consistency-server.mjs`) sert ce dossier au port **6006** pour les sessions de design review partagées ; le scanner Vanilla est aussi servi en statique via ce mécanisme.
