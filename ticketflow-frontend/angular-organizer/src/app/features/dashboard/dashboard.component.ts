import { Component, OnInit, inject, signal } from "@angular/core";
import { CommonModule } from "@angular/common";
import { ApiService, formatXOF } from "../../core/api.service";

interface Kpi { revenue: number; sold: number; capacity: number; avg_rating: number }
interface Point { label: string; value: number }

/**
 * Tableau de bord : Ventes / Billets vendus / Remplissage / Note moyenne +
 * graphiques Chart.js (ventes par jour, top sections).
 */
@Component({
  selector: "app-dashboard", standalone: true, imports: [CommonModule],
  template: `
  <h1>Tableau de bord</h1>
  <div class="tf-kpi">
    <div class="tf-card"><p class="tf-muted">Revenu total</p><p class="tf-money" style="font-size:var(--tf-text-2xl)">{{ formatXOF(kpi()?.revenue ?? 0) }}</p></div>
    <div class="tf-card"><p class="tf-muted">Billets vendus</p><p style="font-size:var(--tf-text-2xl);font-weight:700">{{ kpi()?.sold ?? 0 }}</p></div>
    <div class="tf-card"><p class="tf-muted">Remplissage</p><p style="font-size:var(--tf-text-2xl);font-weight:700">{{ fill() }}%</p></div>
    <div class="tf-card"><p class="tf-muted">Note moyenne</p><p style="font-size:var(--tf-text-2xl);font-weight:700">{{ kpi()?.avg_rating?.toFixed(1) ?? '-' }}/5</p></div>
  </div>
  <div class="tf-grid tf-grid--2" style="margin-top:var(--tf-space-6)">
    <div class="tf-card"><h2>Ventes par jour</h2>
      <canvas #salesChart width="400" height="200" aria-label="Graphique des ventes par jour" role="img"></canvas></div>
    <div class="tf-card"><h2>Top sections</h2>
      <canvas #sectionsChart width="400" height="200" aria-label="Graphique des meilleures sections" role="img"></canvas></div>
  </div>`,
})
export class DashboardComponent implements OnInit {
  private api = inject(ApiService);
  kpi = signal<Kpi | null>(null);
  salesSeries = signal<Point[]>([]);
  sectionsSeries = signal<Point[]>([]);
  readonly formatXOF = formatXOF;

  ngOnInit(): void {
    this.api.get<{ data: Kpi }>("/organizer/dashboard/kpi").subscribe((r) => this.kpi.set(r.data));
    this.api.get<{ data: Point[] }>("/organizer/dashboard/sales-by-day").subscribe((r) => {
      this.salesSeries.set(r.data);
      import("chart.js/auto").then(({ default: Chart }) => {
        const el = document.querySelector("canvas[aria-label='Graphique des ventes par jour']") as HTMLCanvasElement;
        new Chart(el, { type: "line", data: { labels: r.data.map((p) => p.label), datasets: [{ data: r.data.map((p) => p.value), borderColor: "#6d5ef8", tension: .3 }] }, options: { plugins: { legend: { display: false } } } });
      });
    });
    this.api.get<{ data: Point[] }>("/organizer/dashboard/top-sections").subscribe((r) => {
      this.sectionsSeries.set(r.data);
      import("chart.js/auto").then(({ default: Chart }) => {
        const el = document.querySelector("canvas[aria-label='Graphique des meilleures sections']") as HTMLCanvasElement;
        new Chart(el, { type: "bar", data: { labels: r.data.map((p) => p.label), datasets: [{ data: r.data.map((p) => p.value), backgroundColor: "#6d5ef8" }] }, options: { plugins: { legend: { display: false } } } });
      });
    });
  }
  fill(): number { const k = this.kpi(); return k && k.capacity ? Math.round((k.sold / k.capacity) * 100) : 0; }
}
