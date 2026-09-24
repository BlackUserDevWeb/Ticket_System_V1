import { Component, OnInit, inject, signal } from "@angular/core";
import { CommonModule } from "@angular/common";
import { FormsModule, ReactiveFormsModule, FormBuilder, FormGroup, Validators } from "@angular/forms";
import { RouterLink } from "@angular/router";
import { ApiService, formatXOF } from "../../core/api.service";

interface OrgEvent { id: number; title: string; slug: string; starts_at: string; status: string; sold: number; revenue: number }

/**
 * Liste des evenements de l'organisateur + creation via Reactive Forms
 * (validation declarative partagee avec le backend Laravel : memes regles).
 */
@Component({
  selector: "app-events", standalone: true, imports: [CommonModule, FormsModule, ReactiveFormsModule, RouterLink],
  template: `
  <h1>Mes evenements</h1>
  <button class="tf-btn tf-btn--primary" (click)="showForm.set(!showForm())">+ Creer un evenement</button>
  @if (showForm()) {
    <form [formGroup]="form" (ngSubmit)="submit()" class="tf-card tf-stack" style="margin-top:var(--tf-space-5); max-width:640px">
      <div><label class="tf-label">Titre *</label><input class="tf-input" formControlName="title" /></div>
      <div class="tf-row">
        <div style="flex:1"><label class="tf-label">Categorie *</label>
          <select class="tf-input" formControlName="category"><option value="concert">Concert</option><option value="sport">Sport</option><option value="theatre">Theatre</option><option value="festival">Festival</option></select></div>
        <div style="flex:1"><label class="tf-label">Date de debut *</label><input class="tf-input" type="datetime-local" formControlName="starts_at" /></div>
      </div>
      <div><label class="tf-label">Description *</label><textarea class="tf-input" rows="4" formControlName="description"></textarea></div>
      <button class="tf-btn tf-btn--primary" [disabled]="form.invalid || busy()">Enregistrer</button>
      @if (form.dirty && form.invalid) { <p class="tf-muted">Certains champs sont invalides.</p> }
    </form>
  }
  <table class="tf-table" style="margin-top:var(--tf-space-6)">
    <thead><tr><th>Evenement</th><th>Date</th><th>Vendus</th><th>Revenu</th><th>Actions</th></tr></thead>
    <tbody>
      @for (e of events(); track e.id) {
        <tr><td>{{ e.title }}</td><td>{{ e.starts_at | date:'dd/MM/yyyy' }}</td><td>{{ e.sold }}</td>
            <td class="tf-money">{{ fmt(e.revenue) }}</td>
            <td><a [routerLink]="['/evenements', e.id, 'billetterie']">Billetterie</a> ·
                <a [routerLink]="['/evenements', e.id, 'pricing']">Pricing</a></td></tr>
      } @empty { <tr><td colspan="5">Aucun evenement.</td></tr> }
    </tbody>
  </table>`,
})
export class EventsListComponent implements OnInit {
  private api = inject(ApiService);
  private fb = inject(FormBuilder);
  events = signal<OrgEvent[]>([]);
  showForm = signal(false); busy = signal(false);
  readonly fmt = formatXOF;
  form: FormGroup = this.fb.group({
    title: ["", [Validators.required, Validators.maxLength(160)]],
    category: ["concert", Validators.required],
    starts_at: ["", Validators.required],
    description: ["", [Validators.required, Validators.minLength(20)]],
  });

  ngOnInit(): void { this.load(); }
  private load(): void { this.api.get<{ data: OrgEvent[] }>("/organizer/events").subscribe((r) => this.events.set(r.data)); }
  submit(): void {
    if (this.form.invalid) return;
    this.busy.set(true);
    this.api.post("/organizer/events", this.form.value).subscribe({ next: () => { this.busy.set(false); this.showForm.set(false); this.form.reset(); this.load(); }, error: () => this.busy.set(false) });
  }
}
