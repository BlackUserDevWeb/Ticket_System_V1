import { Component, OnInit, inject, signal } from "@angular/core";
import { CommonModule } from "@angular/common";
import { ApiService } from "../../core/api.service";

interface CheckinRow { code: string; holder: string; seat: string; checked_in_at: string }

/** Historique des controles d'acces (temps reel via Reverb channel checkin.event.{id}). */
@Component({
  selector: "app-checkin", standalone: true, imports: [CommonModule],
  template: `
  <h1>Controles d'acces</h1>
  <p class="tf-muted">{{ rows().length }} billets controles</p>
  <table class="tf-table"><thead><tr><th>Code</th><th>Porteur</th><th>Place</th><th>Heure</th></tr></thead>
  <tbody>@for (r of rows(); track r.code + r.checked_in_at) { <tr><td><code>{{ r.code }}</code></td><td>{{ r.holder }}</td><td>{{ r.seat }}</td><td>{{ r.checked_in_at | date:'HH:mm:ss' }}</td></tr> }</tbody></table>`,
})
export class CheckinComponent implements OnInit {
  private api = inject(ApiService);
  rows = signal<CheckinRow[]>([]);
  ngOnInit(): void {
    this.api.get<{ data: CheckinRow[] }>("/organizer/checkins").subscribe((r) => this.rows.set(r.data));
    // Mise a jour temps reel : chaque scan broadcast CheckinRecorded cote Laravel.
    setInterval(() => this.api.get<{ data: CheckinRow[] }>("/organizer/checkins").subscribe((r) => this.rows.set(r.data)), 5000);
  }
}
