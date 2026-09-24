import { Component, OnInit, inject, signal } from "@angular/core";
import { CommonModule } from "@angular/common";
import { ApiService, formatXOF } from "../../core/api.service";

interface FundsOverview { available: number; pending: number; next_payout_date: string | null }
interface PayoutRow { id: number; amount: number; status: string; requested_at: string; mobile_money_ref: string | null }

/** Fonds & payouts : solde disponible, demandes de retrait vers compte Mobile Money. */
@Component({
  selector: "app-funds", standalone: true, imports: [CommonModule],
  template: `
  <h1>Fonds &amp; payouts</h1>
  <div class="tf-kpi">
    <div class="tf-card"><p class="tf-muted">Disponible</p><p class="tf-money" style="font-size:var(--tf-text-xl)">{{ fmt(ov()?.available ?? 0) }}</p></div>
    <div class="tf-card"><p class="tf-muted">En attente</p><p class="tf-money" style="font-size:var(--tf-text-xl)">{{ fmt(ov()?.pending ?? 0) }}</p></div>
    <div class="tf-card"><p class="tf-muted">Prochain versement</p><p style="font-weight:700">{{ ov()?.next_payout_date ?? '-' }}</p></div>
  </div>
  <button class="tf-btn tf-btn--primary" style="margin-top:var(--tf-space-5)" (click)="request()" [disabled]="busy()">Demander un payout</button>
  <table class="tf-table" style="margin-top:var(--tf-space-5)"><thead><tr><th>Date</th><th>Montant</th><th>Statut</th><th>Réf MoMo</th></tr></thead>
  <tbody>@for (p of payouts(); track p.id) { <tr><td>{{ p.requested_at | date:'dd/MM' }}</td><td class="tf-money">{{ fmt(p.amount) }}</td><td>{{ p.status }}</td><td><code>{{ p.mobile_money_ref ?? '-' }}</code></td></tr> }</tbody></table>`,
})
export class FundsComponent implements OnInit {
  private api = inject(ApiService);
  ov = signal<FundsOverview | null>(null);
  payouts = signal<PayoutRow[]>([]);
  busy = false;
  readonly fmt = formatXOF;
  ngOnInit(): void {
    this.api.get<{ data: FundsOverview }>("/organizer/funds").subscribe((r) => this.ov.set(r.data));
    this.api.get<{ data: PayoutRow[] }>("/organizer/payouts").subscribe((r) => this.payouts.set(r.data));
  }
  request(): void {
    this.busy = true;
    this.api.post("/organizer/payouts", {}).subscribe({ next: () => { this.busy = false; location.reload(); }, error: () => (this.busy = false) });
  }
}
