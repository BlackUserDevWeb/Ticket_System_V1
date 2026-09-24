import { useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Link, useParams } from "react-router-dom";
import { api, formatXOF } from "../lib/api";
import type { EventDetail as Ev, TicketOffer } from "../types";
import Layout from "../components/Layout";
import SeatMap, { type Seat } from "../components/SeatMap";
import Panorama360 from "../components/Panorama360";
import { useAuth } from "../hooks/useAuth";

/**
 * Page evenement - coeur de la marketplace (modele TickPick) :
 *  - liste des OFFRES triee par score qualite/prix, badge "Meilleure offre" ;
 *  - filtres section / prix ;
 *  - sélection place assise (SeatMap) ou categorie libre (contenance) ;
 *  - bouton favori qui alimente les recommandations ; viewer 360 par section.
 * Personnalisation organisateur : couleur d'accent appliquee localement.
 */
export default function EventDetailPage() {
  const { slug } = useParams<{ slug: string }>();
  const { user } = useAuth();
  const qc = useQueryClient();
  const [seats, setSeats] = useState<Seat[]>([]);
  const [sectionFilter, setSectionFilter] = useState<number | 0>(0);
  const [maxPrice, setMaxPrice] = useState("");
  const [viewSection, setViewSection] = useState<number | null>(null);

  const { data: ev, error } = useQuery({
    queryKey: ["event", slug], enabled: !!slug,
    queryFn: () => api.get<{ data: Ev }>(`/events/${slug}`).then((r) => r.data),
  });

  const favorite = useMutation({
    mutationFn: () => api.post(`/events/${ev!.id}/favorite`),
    onSuccess: () => qc.invalidateQueries({ queryKey: ["reco"] }),
  });

  const offers = useMemo(() => {
    if (!ev) return [];
    let list = [...ev.offers];
    if (sectionFilter) list = list.filter((o) => o.section_id === sectionFilter);
    if (maxPrice !== "") list = list.filter((o) => o.price_display <= Number(maxPrice));
    list.sort((a, b) => b.score - a.score || a.price_display - b.price_display);
    return list;
  }, [ev, sectionFilter, maxPrice]);

  const bestDealId = useMemo(() => offers.find((o) => o.is_best_deal)?.ticket_type_id ?? offers[0]?.ticket_type_id, [offers]);

  if (error) return <Layout><p>Evenement introuvable. <Link to="/recherche">Retour a la recherche</Link></p></Layout>;
  if (!ev) return <Layout><div className="tf-skeleton" style={{ height: 240 }} /><div className="tf-skeleton" style={{ height: 40, marginTop: 16 }} /></Layout>;

  const chosenOffer: TicketOffer | undefined = seats.length ? ev.offers.find((o) => o.seatmap && o.section_id === seats[0].section_id) : undefined;

  const goCheckout = () => {
    sessionStorage.setItem("tf-cart", JSON.stringify(
      seats.length ? { ticket_type_id: chosenOffer?.ticket_type_id, seat_unit_ids: seats.map((s) => s.id) } : null,
    ));
    window.location.href = `/achat/${ev.slug}`;
  };

  return (
    <Layout>
      <div style={ev.organizer.color ? ({ "--tf-accent": ev.organizer.color } as React.CSSProperties) : undefined}>
        <div className="tf-stack">
          <header>
            <div className="tf-muted" style={{ fontSize: "var(--tf-text-sm)" }}>
              {new Date(ev.starts_at).toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long", year: "numeric" })} - {ev.venue}, {ev.city}
              {ev.is_sponsored ? " - Sponsorise" : ""}
            </div>
            <h1>{ev.title}</h1>
            {user && (
              <button className="tf-btn tf-btn--secondary" onClick={() => favorite.mutate()} aria-pressed={favorite.isSuccess}>
                {favorite.isSuccess ? "Retirer des favoris" : "Ajouter aux favoris"}
              </button>
            )}
          </header>
          {ev.image_url && <img src={ev.image_url} alt="" style={{ width: "100%", borderRadius: "var(--tf-radius-lg)", aspectRatio: "16/9", objectFit: "cover" }} />}
          <section aria-label="Description"><h2>A propos</h2><p style={{ whiteSpace: "pre-line" }}>{ev.description}</p>
            {ev.lineup && <><h3>Affiche</h3><p style={{ whiteSpace: "pre-line" }}>{ev.lineup}</p></>}
            <p className="tf-muted">Organise par <strong>{ev.organizer.name}</strong></p></section>

          <aside aria-label="Billets disponibles" className="tf-card">
            <h2 style={{ fontSize: "var(--tf-text-lg)" }}>Billets</h2>
            <p className="tf-muted" style={{ fontSize: "var(--tf-text-xs)" }}>Le prix affiche inclut tous les frais de service - pas de surprise au paiement.</p>
            <div className="tf-row" style={{ flexWrap: "wrap" }}>
              <select className="tf-input" value={sectionFilter} onChange={(e) => setSectionFilter(Number(e.target.value))} aria-label="Filtrer par section">
                <option value={0}>Toutes sections</option>
                {ev.sections.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
              </select>
              <input className="tf-input" type="number" placeholder="Prix max" value={maxPrice} onChange={(e) => setMaxPrice(e.target.value)} aria-label="Prix maximum" style={{ width: 110 }} />
            </div>
            <ul style={{ listStyle: "none", padding: 0, margin: "var(--tf-space-4) 0 0" }}>
              {offers.map((o) => (
                <li key={o.ticket_type_id} className="tf-card" style={{ padding: "var(--tf-space-3)", marginBottom: "var(--tf-space-3)", borderColor: o.ticket_type_id === bestDealId ? "var(--tf-accent)" : undefined }}>
                  <div className="tf-row" style={{ justifyContent: "space-between" }}>
                    <div>
                      <strong>{o.name}</strong>
                      <div className="tf-muted" style={{ fontSize: "var(--tf-text-sm)" }}>{o.section_name}{o.available <= 10 ? ` - plus que ${o.available}` : ""}</div>
                      {o.dynamic_pricing && <span className="tf-badge tf-badge--warning" style={{ marginTop: 4 }}>Prix dynamique</span>}
                    </div>
                    <div style={{ textAlign: "right" }}>
                      <div className="tf-money" style={{ fontSize: "var(--tf-text-lg)" }}>{formatXOF(o.price_display)}</div>
                      <div className="tf-row" style={{ justifyContent: "flex-end", marginTop: 4 }}>
                        {o.ticket_type_id === bestDealId && <span className="tf-badge tf-badge--success">Meilleure offre</span>}
                        <span className="tf-score" title={`Score qualite/prix : ${o.score}/10`}>{o.score.toFixed(1)}</span>
                      </div>
                    </div>
                  </div>
                  {!o.seatmap && (
                    <button className="tf-btn tf-btn--primary" style={{ width: "100%", marginTop: "var(--tf-space-3)" }}
                      onClick={() => { sessionStorage.setItem("tf-cart", JSON.stringify({ ticket_type_id: o.ticket_type_id, quantity: 1 })); location.href = `/achat/${ev.slug}`; }}>
                      Acheter
                    </button>
                  )}
                </li>
              ))}
            </ul>
          </aside>

          {ev.has_seatmap && (
            <section aria-label="Plan de salle">
              <h2>Choisissez vos places</h2>
              <SeatMap event={ev} selected={seats}
                onToggle={(s) => { setSeats((prev) => prev.some((p) => p.id === s.id) ? prev.filter((p) => p.id !== s.id) : [...prev, s].slice(0, 8)); setViewSection(s.section_id); }} />
              {seats.length > 0 && (
                <>
                  <p className="tf-muted">{seats.map((s) => s.label).join(", ")} - {seats.length} place(s) selectionnee(s)</p>
                  <button className="tf-btn tf-btn--primary" onClick={goCheckout}>Continuer avec {seats.length} place(s)</button>
                </>
              )}
            </section>
          )}
          {viewSection != null && (
            <section aria-label="Vue depuis le siege">
              <h2>Vue depuis ce siege</h2>
              <Panorama360 label={ev.sections.find((s) => s.id === viewSection)?.name ?? "votre place"}
                src={`/storage/panoramas/${ev.id}-${viewSection}.jpg`} />
            </section>
          )}
        </div>
      </div>
    </Layout>
  );
}
