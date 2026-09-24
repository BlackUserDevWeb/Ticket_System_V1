import { HttpClient, HttpParams } from "@angular/common/http";
import { Injectable, inject } from "@angular/core";
import { Observable } from "rxjs";

/**
 * Service d'acces API v1 Laravel (pattern Injection de dependances Angular —
 * un des arguments forts du choix Angular pour ce back-office metier).
 */
@Injectable({ providedIn: "root" })
export class ApiService {
  private http = inject(HttpClient);
  private base = "/api/v1";

  get<T>(path: string, params: Record<string, string | number> = {}): Observable<T> {
    let p = new HttpParams();
    Object.entries(params).forEach(([k, v]) => (p = p.set(k, v)));
    return this.http.get<T>(`${this.base}${path}`, { params: p });
  }
  post<T>(path: string, body: unknown): Observable<T> { return this.http.post<T>(`${this.base}${path}`, body); }
  put<T>(path: string, body: unknown): Observable<T> { return this.http.put<T>(`${this.base}${path}`, body); }
  del<T>(path: string): Observable<T> { return this.http.delete<T>(`${this.base}${path}`); }
}

/** Formatage FCFA identique au front React (partage via design-system/doc). */
export function formatXOF(amount: number | string): string {
  const n = typeof amount === "string" ? parseInt(amount, 10) : amount;
  const grouped = String(Math.abs(n)).replace(/\B(?=(\d{3})+(?!\d))/g, "\u00a0");
  return (n < 0 ? "-" : "") + grouped + "\u00a0FCFA";
}
