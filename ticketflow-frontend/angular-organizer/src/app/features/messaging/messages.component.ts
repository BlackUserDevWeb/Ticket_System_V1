import { Component, OnInit, inject, signal } from "@angular/core";
import { CommonModule } from "@angular/common";
import { FormsModule } from "@angular/forms";
import { ApiService } from "../../core/api.service";

interface Conversation { id: number; with_name: string; last_message: string; unread: number }
interface Message { id: number; body: string; from: string; created_at: string }

/** Messagerie acheteur <-> organisateur (temps reel via Reverb channel conversation.{id}). */
@Component({
  selector: "app-messages", standalone: true, imports: [CommonModule, FormsModule],
  template: `
  <h1>Messages</h1>
  <div style="display:grid;grid-template-columns:260px 1fr;gap:var(--tf-space-5)">
    <ul style="list-style:none;padding:0;margin:0">
      @for (c of convs(); track c.id) {
        <li><button class="tf-btn {{ active()?.id === c.id ? 'tf-btn--primary' : 'tf-btn--ghost' }}" style="width:100%;justify-content:flex-start" (click)="open(c)">
          {{ c.with_name }} @if (c.unread) { <span class="tf-badge">{{ c.unread }}</span> }
        </button></li>
      }
    </ul>
    @if (active(); as a) {
      <div class="tf-card tf-stack">
        @for (m of msgs(); track m.id) {
          <p style="margin:0"><strong>{{ m.from }}</strong> : {{ m.body }}</p>
        }
        <form class="tf-row" (ngSubmit)="send()">
          <input class="tf-input" placeholder="Votre message..." [(ngModel)]="draft" name="draft" required />
          <button class="tf-btn tf-btn--primary">Envoyer</button>
        </form>
      </div>
    }
  </div>`,
})
export class MessagesComponent implements OnInit {
  private api = inject(ApiService);
  convs = signal<Conversation[]>([]); active = signal<Conversation | null>(null);
  msgs = signal<Message[]>([]); draft = "";
  ngOnInit(): void { this.api.get<{ data: Conversation[] }>("/organizer/conversations").subscribe((r) => this.convs.set(r.data)); }
  open(c: Conversation): void {
    this.active.set(c);
    this.api.get<{ data: Message[] }>(`/organizer/conversations/${c.id}/messages`).subscribe((r) => this.msgs.set(r.data));
  }
  send(): void {
    if (!this.draft.trim() || !this.active()) return;
    this.api.post(`/organizer/conversations/${this.active()!.id}/messages`, { body: this.draft })
      .subscribe(() => { this.open(this.active()!); this.draft = ""; });
  }
}
