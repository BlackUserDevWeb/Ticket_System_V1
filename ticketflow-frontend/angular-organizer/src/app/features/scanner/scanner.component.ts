import { Component, ElementRef, OnDestroy, OnInit, ViewChild, inject, signal } from "@angular/core";
import { CommonModule } from "@angular/common";
import { ApiService, formatXOF } from "../../core/api.service";

interface ScanResult { valid: boolean; ticket?: { code: string; holder: string; seat: string; event: string }; reason?: string }

/**
 * Scanner de billets — QR code en temps reel via la BarcodeDetector API
 * (native Chromium/Android ; fallback saisie manuelle du code pour les
 * telephones sans support). Chaque scan est verifie cote serveur (anti
 * double-usage : le backend marque le billet "used" dans la meme requete).
 */
@Component({
  selector: "app-scanner", standalone: true, imports: [CommonModule],
  template: `
  <h1>Scanner un billet</h1>
  @if (!supported()) { <p class="tf-muted">Camera non disponible sur ce navigateur — utilisez la saisie manuelle ci-dessous.</p> }
  <video #video width="480" height="360" playsinline muted style="width:100%;max-width:480px;border-radius:var(--tf-radius-lg);background:#000"></video>
  <div class="tf-row" style="margin-top:var(--tf-space-4)">
    <input class="tf-input" placeholder="ou saisir le code TF-XXXX" [(ngModel)]="manualCode" (keyup.enter)="check(manualCode)" />
    <button class="tf-btn tf-btn--primary" (click)="check(manualCode)">Verifier</button>
    <button class="tf-btn tf-btn--secondary" (click)="toggle()">{{ running() ? 'Stopper' : 'Demarrer' }} la camera</button>
  </div>
  @if (last()) {
    <div class="tf-card" role="status" style="margin-top:var(--tf-space-5); border-color: {{ last()!.valid ? 'var(--tf-success)' : 'var(--tf-danger)' }}">
      @if (last()!.valid) {
        <p style="color:var(--tf-success);font-weight:700">ENTREE AUTORISEE</p>
        <p>{{ last()!.ticket!.holder }} · {{ last()!.ticket!.seat }} · {{ last()!.ticket!.event }}</p>
      } @else {
        <p style="color:var(--tf-danger);font-weight:700">REFUSE — {{ last()!.reason }}</p>
      }
    </div>
  }`,
})
export class ScannerComponent implements OnInit, OnDestroy {
  private api = inject(ApiService);
  @ViewChild("video") videoRef!: ElementRef<HTMLVideoElement>;
  manualCode = "";
  supported = signal(true); running = signal(false); last = signal<ScanResult | null>(null);
  private stream?: MediaStream; private timer?: ReturnType<typeof setInterval>;

  async ngOnInit(): Promise<void> {
    if (!("BarcodeDetector" in window)) { this.supported.set(false); return; }
    await this.toggle();
  }
  ngOnDestroy(): void { this.stop(); }

  async toggle(): Promise<void> { this.running() ? this.stop() : this.start(); }

  private async start(): Promise<void> {
    try {
      this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
      const v = this.videoRef.nativeElement; v.srcObject = this.stream; await v.play();
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const detector = new (window as any).BarcodeDetector({ formats: ["qr_code"] });
      this.timer = setInterval(async () => {
        if (!this.running()) return;
        const codes = await detector.detect(v);
        if (codes.length) { this.stop(); await this.check(codes[0].rawValue); this.start(); }
      }, 400);
      this.running.set(true);
    } catch { this.supported.set(false); }
  }
  private stop(): void {
    clearInterval(this.timer); this.stream?.getTracks().forEach((t) => t.stop()); this.running.set(false);
  }
  check(code: string): Promise<void> {
    return this.api.post<ScanResult>("/organizer/scan", { code }).toPromise()
      .then((r) => { if (r) this.last.set(r); })
      .catch(() => this.last.set({ valid: false, reason: "Erreur reseau — verifier la connexion." }));
  }
}
