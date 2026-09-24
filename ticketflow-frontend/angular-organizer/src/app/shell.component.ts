import { Component, inject } from "@angular/core";
import { RouterLink, RouterLinkActive, RouterOutlet } from "@angular/router";
import { AuthStore } from "./core/auth.store";

/** Gabarit back-office : sidebar navigation + zone de contenu. */
@Component({
  selector: "app-shell",
  standalone: true,
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
  <div style="display:flex; min-height:100vh">
    <nav class="tf-sidebar" aria-label="Navigation organisateur">
      <strong style="font-size:var(--tf-text-lg)">Ticket<span style="color:var(--tf-accent)">Flow</span></strong>
      <p class="tf-muted" style="font-size:var(--tf-text-xs)">Espace organisateur</p>
      <a routerLink="/" routerLinkActive="active" [routerLinkActiveOptions]="{exact:true}">Tableau de bord</a>
      <a routerLink="/evenements" routerLinkActive="active">Evenements</a>
      <a routerLink="/scanner" routerLinkActive="active">Scanner</a>
      <a routerLink="/checkin" routerLinkActive="active">Controlles</a>
      <a routerLink="/fonds" routerLinkActive="active">Fonds &amp; payouts</a>
      <a routerLink="/messages" routerLinkActive="active">Messages</a>
      <button class="tf-btn tf-btn--ghost" style="margin-top:var(--tf-space-6)" (click)="auth.logout()">Deconnexion ({{ auth.user()?.name }})</button>
    </nav>
    <main style="flex:1; padding:var(--tf-space-6); max-width:1200px">
      <router-outlet />
    </main>
  </div>`,
})
export class ShellComponent {
  auth = inject(AuthStore);
}
