import { useEffect, useState } from "react";
import { useParams } from "react-router-dom";
import { useMutation, useQuery } from "@tanstack/react-query";
import { api, formatXOF } from "../lib/api";
import type { EventDetail, Order } from "../types";
import Layout from "../components/Layout";
import { useAuth } from "../hooks/useAuth";
import { useCountdown } from "../hooks/useCountdown";

type Step = "infos" | "waiting" | "done" | "failed";
interface HoldResult { hold_id: string; order_id: number; expires_at: string; total_amount: number; lines: { label: string; amount: number }[] }
interface PaymentInit { payment_id: number; provider: string; instructions: string; ussd?: string }

/**
 * Tunnel d'achat :
 *  1. hold des sieges (5 min, compte a rebours visible) + extras ;
 *  2. Moov Money / Mixx by Yas -> demande de paiement push sur le telephone ;
 *  3. confirmation par webhook (polling statut commande toutes les 3 s)
 *     -> QR code affiche + email envoye (job en file cote backend).
 */
export default function Checkout() {
  const { slug } = useParams<{ slug: string }>();
  const { user } = useAuth();
  const cart = JSON.parse(sessionStorage.getItem("tf-cart") ?? "null");

  const [step, setStep] = useState<Step>("infos");
  const [hold, setHold] = useState<HoldResult | null>(null);
  const [payment, setPayment] = useState<PaymentInit | null>(null);
  const [phone, setPhone] = useState(user?.phone ?? "");
  const [provider, setProvider] = useState<"moov" | "mixx">("moov");
  const [extraIds, setExtraIds] = useState<number[]>([]);
  const [err, setErr] = useState<string | null>(null);
  const remaining = useCountdown(hold?.expires_at ?? null);

  const { data: ev } = useQuery({
    queryKey: ["event", slug],
    queryFn: () => api.get<{ data: EventDetail }>(`/events/${slug}`).then((r) => r.data),
  });

  // Le hold a expire cote client : on relance depuis zero (le cron serveur libere les sieges).
  useEffect(() => {
    if (hold && remaining === 0 && step === "infos") {
      setErr("Reservation temporaire expiree, les places ont ete liberees.");
      setHold(null);
    }
  }, [remaining]); // eslint-disable-line react-hooks/exhaustive-deps

  // Polling du statut : le webhook Mobile Money est asynchrone cote serveur.
  useEffect(() => {
    if (!hold || step !== "waiting") return;
    const t = setInterval(async () => {
      try {
        const { data } = await api.get<{ data: Order }>(`/orders/${hold.order_id}`);
        if (data.status === "paid") { clearInterval(t); setStep("done"); }
        if (data.status === "failed" || data.status === "expired") { clearInterval(t); setStep("failed"); }
      } catch { /* reseau instable : on continue de poller, le timeout serveur tranche */ }
    }, 3000);
    return () => clearInterval(t);
  }, [hold, step]);

  const createHold = useMutation({
    mutationFn: () => api.post<HoldResult>("/checkout/hold", { ...(cart ?? {}), event_slug: slug, extra_ids: extraIds }),
    onSuccess: (h) => { setHold(h); setErr(null); },
    onError: (e: Error) => setErr(e.message),
  });

  const pay = useMutation({
    mutationFn: () => api.post<PaymentInit>("/checkout/pay", { hold_id: hold!.hold_id, provider, phone }),
    onSuccess: (p) => { setPayment(p); setStep("waiting"); },
    onError: (e: Error) => setErr(e.message),
  });

  if (!user) return <Layout><p>Merci de <a href="/connexion">vous connecter</a> pour acheter un billet.</p></Layout>;

  return (
    <Layout>
      <h1>Achat - {ev?.title}</h1>
      {err && <p role="alert" className="tf-card" style={{ borderColor: "var(--tf-danger)", color: "var(--tf-danger)" }}>{err}</p>}
      <ol className="tf-muted" style={{ fontSize: "var(--tf-text-sm)", paddingLeft: 20 }} aria-label="Etapes de l'achat">
        <li>1. Reservation {step !== "infos" ? "(termine)" : ""}</li>
        <li>2. Paiement Mobile Money</li>
        <li>3. Billet QR code</li>
      </ol>

      {step === "infos" && (
        <div className="tf-card tf-stack" style={{ maxWidth: 560 }}>
          {!hold ? (
            <>
              <p>Confirmez la reservation temporaire de vos places (valable 5 minutes).</p>
              {!!ev?.extras.length && (
                <fieldset style={{ border: "none", padding: 0 }}>
                  <legend className="tf-label">Extras (parking, boissons, goodies)</legend>
                  {ev.extras.map((x) => (
                    <label key={x.id} className="tf-row" style={{ display: "flex", gap: 8 }}>
                      <input type="checkbox" checked={extraIds.includes(x.id)}
                        onChange={(e) => setExtraIds((ids) => e.target.checked ? [...ids, x.id] : ids.filter((i) => i !== x.id))} />
                      {x.name} - <span className="tf-money">{formatXOF(x.price)}</span>
                    </label>
                  ))}
                </fieldset>
              )}
              <button className="tf-btn tf-btn--primary" onClick={() => createHold.mutate()} disabled={createHold.isPending}>
                {createHold.isPending ? "Reservation..." : "Reserver mes places"}
              </button>
            </>
          ) : (
            <>
              <p><strong>Total : {formatXOF(hold.total_amount)}</strong> - frais de service inclus.</p>
              <ul>{hold.lines.map((l) => <li key={l.label} className="tf-muted">{l.label} : {formatXOF(l.amount)}</li>)}</ul>
              <p role="timer">Temps restant : {Math.floor((remaining ?? 0) / 60)}:{String((remaining ?? 0) % 60).padStart(2, "0")}</p>
              <div>
                <label className="tf-label" htmlFor="mm-phone">Numero Mobile Money (+228...)</label>
                <input id="mm-phone" className="tf-input" value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="+22890123456" inputMode="tel" />
              </div>
              <div className="tf-row" role="radiogroup" aria-label="Operateur de paiement">
                <label className="tf-row"><input type="radio" name="prov" checked={provider === "moov"} onChange={() => setProvider("moov")} /> Moov Money (Flooz)</label>
                <label className="tf-row"><input type="radio" name="prov" checked={provider === "mixx"} onChange={() => setProvider("mixx")} /> Mixx by Yas</label>
              </div>
              <button className="tf-btn tf-btn--primary" onClick={() => pay.mutate()} disabled={pay.isPending}>
                {pay.isPending ? "Envoi de la demande..." : `Payer ${formatXOF(hold.total_amount)}`}
              </button>
            </>
          )}
        </div>
      )}

      {step === "waiting" && (
        <div className="tf-card tf-stack" style={{ maxWidth: 560 }} role="status">
          <h2>Confirmez le paiement sur votre telephone</h2>
          <p>{payment?.instructions ?? "Une demande de paiement a ete envoyee."}</p>
          {payment?.ussd && <p className="tf-muted" style={{ fontFamily: "monospace" }}>Code compose automatiquement : {payment.ussd}</p>}
          <div className="tf-skeleton" style={{ height: 8 }} />
          <p className="tf-muted">Si vous ne recevez rien ici 2 minutes, verifiez votre solde ou recommencez.</p>
        </div>
      )}

      {step === "done" && hold && <TicketSuccess orderId={hold.order_id} />}
      {step === "failed" && <a className="tf-btn tf-btn--primary" href={`/evenement/${slug}`}>Reessayer</a>}
    </Layout>
  );
}

/** Confirmation : billets + QR codes (genere cote backend, image telechargeable vers le wallet mobile). */
function TicketSuccess({ orderId }: { orderId: number }) {
  const { data } = useQuery({
    queryKey: ["order", orderId],
    queryFn: () => api.get<{ data: Order }>(`/orders/${orderId}`).then((r) => r.data),
  });
  if (!data) return <p>Chargement de vos billets...</p>;
  return (
    <div className="tf-card tf-stack" style={{ maxWidth: 560 }} role="status">
      <h2>Paiement confirme</h2>
      <p className="tf-muted">Commande {data.reference} - un email vient de vous etre envoye.</p>
      {data.tickets?.map((t) => (
        <figure key={t.id} style={{ margin: 0, textAlign: "center" }}>
          {t.qr_url && <img src={t.qr_url} alt={`QR code du billet ${t.code}`} style={{ width: 220, height: 220 }} />}
          <figcaption>{t.seat_label ?? ""} - <code>{t.code}</code></figcaption>
          {t.qr_url && <a className="tf-btn tf-btn--secondary" href={t.qr_url} download>Enregistrer le billet</a>}
        </figure>
      ))}
      <a className="tf-btn tf-btn--primary" href="/compte">Voir tous mes billets</a>
    </div>
  );
}
