import { Component, inject, signal } from "@angular/core";
import { FormsModule } from "@angular/forms";
import { CommonModule } from "@angular/common";
import { AuthStore } from "../core/auth.store";

@Component({
  selector: "app-login",
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
  <div class="tf-card" style="max-width:400px;margin:10vh auto">
    <h1>Connexion organisateur</h1>
    @if (error()) { <p role="alert" style="color:var(--tf-danger)">{{ error() }}</p> }
    <form (ngSubmit)="submit()" class="tf-stack">
      <div><label class="tf-label" for="lg-email">Email</label>
        <input id="lg-email" class="tf-input" type="email" name="email" [(ngModel)]="email" required autocomplete="username" /></div>
      <div><label class="tf-label" for="lg-pwd">Mot de passe</label>
        <input id="lg-pwd" class="tf-input" type="password" name="pwd" [(ngModel)]="password" required autocomplete="current-password" /></div>
      <button class="tf-btn tf-btn--primary" [disabled]="busy()">{{ busy() ? '...' : 'Se connecter' }}</button>
    </form>
  </div>`,
})
export class LoginComponent {
  private auth = inject(AuthStore);
  email = ""; password = "";
  busy = signal(false); error = signal<string | null>(null);

  async submit(): Promise<void> {
    this.busy.set(true); this.error.set(null);
    try { await this.auth.login(this.email, this.password); }
    catch { this.error.set("Identifiants invalides."); }
    finally { this.busy.set(false); }
  }
}
