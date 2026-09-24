import { useState } from "react";
import { Link } from "react-router-dom";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api, formatXOF } from "../lib/api";
import type { Order } from "../types";
import Layout from "../components/Layout";
import { useAuth } from "../hooks/useAuth";

type Ticket = NonNullable<Order["tickets"]>[number];
interface ResaleListing { id: number; ticket_unit_id: number; status: string; price_asked: number }

/**
 * Espace acheteur : billets actifs (QR + transfert), historique de commandes,
 * revente (Autolist = prix fixé par le vendeur, frais intégrés), favoris.
 */
export default function Account() {
  const { user, loading } = useAuth();
  const qc = useQueryClient();
  const [tab, setTab] = useState<"tickets" | "orders" | "resales" | "favorites">("tickets");
  const [transferTo, setTransferTo] = useState<number | null>(null);
  const [recipient, setRecipient] = useState("");
  const [resaleFor, setResaleFor] = useState<number | null>(null);
  const [resalePrice, setResalePrice] = useState("");

  const me = useQuery({ queryKey: ["me"], queryFn: () => api.get<{ data: import("../hooks/useAuth").User }>("/auth/me").then((r) => r.data), enabled: !loading });

  const tickets = useQuery({
    queryKey: ["tickets", tab],
    queryFn: () => api.get<{ data: any[] }>(`/account/${tab}`).then((r) => r.data),
    enabled: !!user,
  });

  const transfer = useMutation({
    mutationFn: ({ ticketId, email }: { ticketId: number; email: string }) => api.post(`/tickets/${ticketId}/transfer`, { recipient: email }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["tickets"] }); setTransferTo(null); setRecipient(""); },
  });
  const listResale = useMutation({
    mutationFn: ({ ticketId, price }: { ticketId: number; price: number }) => api.post("/resales", { ticket_unit_id: ticketId, price }),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ["tickets"] }); setResaleFor(null); setResalePrice(""); },
  });

  if (loading) return <Layout><p>Chargement…</p></Layout>;
  if (!user) return <Layout><p>Merci de <Link to="/connexion">vous connecter</Link>.</p></Layout>;

  const TABS = [
    { k: "tickets", label: "Mes billets" }, { k: "orders", label: "Commandes" },
    { k: "resales", label: "Revente" }, { k: "favorites", label: "Favoris" },
  ] as const;

  return (
    <Layout>
      <h1>Mon compte</h1>
      <p className="tf-muted">{me.data?.name} · {me.data?.email}</p>
      <div className="tf-row" role="tablist" aria-label="Onglets du compte" style={{ borderBottom: "1px solid var(--tf-border)", marginBottom: "var(--tf-space-5)" }}>
        {TABS.map((t) => (
          <button key={t.k} role="tab" aria-selected={tab === t.k} onClick={() => setTab(t.k)}
            className={"tf-btn " + (tab === t.k ? "tf-btn--primary" : "tf-btn--ghost")} style={{ borderRadius: "var(--tf-radius-sm) var(--tf-radius-sm) 0 0" }}>
            {t.label}
          </button>
        ))}
      </div>

      {tab === "tickets" && (
        <ul className="tf-grid tf-grid--2" style={{ listStyle: "none", padding: 0 }}>
          {(tickets.data ?? [] as any[]).map((t: Ticket) => (
            <li key={t.id} className="tf-card">
              <div className="tf-row" style={{ justifyContent: "space-between" }}>
                <strong>{t.seat_label ?? "Billet"}</strong>
                <span className="tf-badge">{t.transferable ? "Transférable" : "Nominatif"}</span>
              </div>
              {t.qr_url && <img src={t.qr_url} alt="QR code" style={{ width: 160, height: 160, display: "block", margin: "var(--tf-space-3) auto" }} />}
              <code style={{ display: "block", textAlign: "center" }}>{t.code}</code>
              {transferTo === t.id ? (
                <form className="tf-row" onSubmit={(e) => { e.preventDefault(); transfer.mutate({ ticketId: t.id, email: recipient }); }}>
                  <input className="tf-input" placeholder="Email du destinataire" value={recipient} onChange={(e) => setRecipient(e.target.value)} />
                  <button className="tf-btn tf-btn--primary" disabled={transfer.isPending}>Envoyer</button>
                </form>
              ) : resaleFor === t.id ? (
                <form className="tf-row" onSubmit={(e) => { e.preventDefault(); listResale.mutate({ ticketId: t.id, price: Number(resalePrice) }); }}>
                  <input className="tf-input" type="number" placeholder="Prix demandé (FCFA)" value={resalePrice} onChange={(e) => setResalePrice(e.target.value)} />
                  <button className="tf-btn tf-btn--primary" disabled={listResale.isPending}>Mettre en vente</button>
                </form>
              ) : (
                <div className="tf-row" style={{ marginTop: "var(--tf-space-3)" }}>
                  {t.transferable && <button className="tf-btn tf-btn--secondary" onClick={() => setTransferTo(t.id)}>Transférer</button>}
                  <button className="tf-btn tf-btn--secondary" onClick={() => setResaleFor(t.id)}>Revendre</button>
                </div>
              )}
            </li>
          ))}
        </ul>
      )}

      {tab === "orders" && (
        <ul style={{ listStyle: "none", padding: 0 }}>
          {(tickets.data ?? []).map((o: Order) => (
            <li key={o.id} className="tf-card" style={{ marginBottom: "var(--tf-space-3)" }}>
              <div className="tf-row" style={{ justifyContent: "space-between" }}>
                <span><code>{o.reference}</code> · {o.status}</span>
                <span className="tf-money">{formatXOF(o.total_amount)}</span>
              </div>
            </li>
          ))}
        </ul>
      )}

      {tab === "resales" && (
        <ul style={{ listStyle: "none", padding: 0 }}>
          {(tickets.data ?? []).map((r: ResaleListing) => (
            <li key={r.id} className="tf-card" style={{ marginBottom: "var(--tf-space-3)" }}>
              Annonce #{r.id} — statut {r.status} — prix demandé {formatXOF(r.price_asked)}
            </li>
          ))}
        </ul>
      )}

      {tab === "favorites" && (
        <ul className="tf-grid tf-grid--2" style={{ listStyle: "none", padding: 0 }}>
          {(tickets.data ?? []).map((e: any) => (
            <li key={e.id}><Link className="tf-card" style={{ display: "block" }} to={`/evenement/${e.slug}`}>{e.title}</Link></li>
          ))}
        </ul>
      )}
    </Layout>
  );
}
