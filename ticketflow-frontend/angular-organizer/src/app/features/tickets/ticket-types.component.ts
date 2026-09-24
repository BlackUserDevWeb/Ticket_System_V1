import { Component, OnInit, inject, input, signal } from "@angular/core";
import { CommonModule } from "@angular/common";
import { FormsModule } from "@angular/forms";
import { ApiService, formatXOF } from "../../core/api.service";

interface TicketType { id: number; name: string; price: number; quota: number; sold: number; section_id: number }

/** Gestion de la billetterie d'un evenement : types de billets, quotas, prix. */
@Component({
  selector: "app-ticket-types", standalone: true, imports: [CommonModule, FormsModule],
  template: `
  <h1>Billetterie</h1>
  <table class="tf-table"><thead><tr><th>Type</th><th>Prix</th><th>Quota</th><th>Vendus</th></tr></thead>
  <tbody>@for (t of types(); track t.id) { <tr><td>{{ t.name }}</td><td class="tf-money">{{ fmt(t.price) }}</td><td>{{ t.quota }}</td><td>{{ t.sold }}</td></tr> }</tbody></table>
  <form class="tf-card tf-row" style="margin-top:var(--tf-space-5)" (ngSubmit)="add()">
    <input class="tf-input" placeholder="Nom (ex: VIP)" [(ngModel)]="name" name="name" required />
    <input class="tf-input" type="number" placeholder="Prix FCFA" [(ngModel)]="price" name="price" required style="width:140px" />
    <input class="tf-input" type="number" placeholder="Quota" [(ngModel)]="quota" name="quota" required style="width:120px" />
    <button class="tf-btn tf-btn--primary" [disabled]="!name || !price">Ajouter</button>
  </form>`,
})
export class TicketTypesComponent implements OnInit {
  private api = inject(ApiService);
  id = input.required<string>();
  types = signal<TicketType[]>([]);
  readonly fmt = formatXOF;
  name = ""; price = 0; quota = 0;

  ngOnInit(): void { this.load(); }
  private load(): void { this.api.get<{ data: TicketType[] }>(`/organizer/events/${this.id()}/ticket-types`).subscribe((r) => this.types.set(r.data)); }
  add(): void {
    this.api.post(`/organizer/events/${this.id()}/ticket-types`, { name: this.name, price: this.price, quota: this.quota })
      .subscribe(() => { this.name = ""; this.price = 0; this.quota = 0; this.load(); });
  }
}
