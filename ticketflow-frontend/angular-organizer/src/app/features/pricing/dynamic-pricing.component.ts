import { Component, OnInit, inject, input, signal } from "@angular/core";
import { CommonModule } from "@angular/common";
import { FormsModule } from "@angular/forms";
import { ApiService, formatXOF } from "../../core/api.service";

interface PricingRule { id: number; trigger_type: string; threshold: number; multiplier: number; active: boolean }

/** Regles de prix dynamique (ex: +15% quand 80% de la section est vendue). */
@Component({
  selector: "app-dynamic-pricing", standalone: true, imports: [CommonModule, FormsModule],
  template: `
  <h1>Prix dynamique</h1>
  <table class="tf-table"><thead><tr><th>Déclencheur</th><th>Seuil</th><th>Multiplicateur</th><th>Actif</th></tr></thead>
  <tbody>@for (r of rules(); track r.id) {
    <tr><td>{{ r.trigger_type }}</td><td>{{ r.threshold }}%</td><td>x{{ r.multiplier }}</td>
        <td><input type="checkbox" [checked]="r.active" (change)="toggle(r)" aria-label="Activer la regle" /></td></tr>
  }</tbody></table>`,
})
export class DynamicPricingComponent implements OnInit {
  private api = inject(ApiService);
  id = input.required<string>();
  rules = signal<PricingRule[]>([]);
  readonly fmt = formatXOF;
  ngOnInit(): void { this.load(); }
  private load(): void { this.api.get<{ data: PricingRule[] }>(`/organizer/events/${this.id()}/pricing-rules`).subscribe((r) => this.rules.set(r.data)); }
  toggle(rule: PricingRule): void {
    this.api.put(`/organizer/pricing-rules/${rule.id}`, { active: !rule.active }).subscribe(() => this.load());
  }
}
