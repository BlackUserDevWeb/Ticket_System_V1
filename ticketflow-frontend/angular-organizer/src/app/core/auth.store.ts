import { Injectable, computed, signal, inject } from "@angular/core";
import { Router } from "@angular/router";
import { firstValueFrom } from "rxjs";
import { ApiService } from "./api.service";

export interface OrganizerUser { id: number; name: string; email: string; role: string }

/** Etat d'authentification en Signals (Angular 19) : source unique reactive. */
@Injectable({ providedIn: "root" })
export class AuthStore {
  private api = inject(ApiService);
  private router = inject(Router);
  readonly user = signal<OrganizerUser | null>(null);
  readonly isLoggedIn = computed(() => !!this.user());

  async login(email: string, password: string): Promise<void> {
    const res = await firstValueFrom(this.api.post<{ token: string; user: OrganizerUser }>("/auth/login", { email, password }));
    localStorage.setItem("tf-token", res.token);
    this.user.set(res.user);
    await this.router.navigate(["/"]);
  }
  logout(): void {
    localStorage.removeItem("tf-token");
    this.user.set(null);
    void this.router.navigate(["/login"]);
  }
}
