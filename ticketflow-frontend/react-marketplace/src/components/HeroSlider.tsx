import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import type { EventSummary } from "../types";
import { formatXOF } from "../lib/api";

/**
 * Carrousel « à la une » — autoplay 6 s, pause au survol/focus (accessibilité),
 * navigation clavier flèches, dots ARIA. Pas de librairie externe : 60 lignes
 * suffisent et on garde le contrôle du poids (perf mobile Togolais).
 */
export default function HeroSlider({ events }: { events: EventSummary[] }) {
  const [i, setI] = useState(0);
  const [paused, setPaused] = useState(false);
  useEffect(() => {
    if (paused || events.length < 2) return;
    const id = setInterval(() => setI((p) => (p + 1) % events.length), 6000);
    return () => clearInterval(id);
  }, [paused, events.length]);
  if (!events.length) return null;
  const e = events[i];
  return (
    <section aria-roledescription="carrousel" aria-label="Événements à la une"
      onMouseEnter={() => setPaused(true)} onMouseLeave={() => setPaused(false)}
      onKeyDown={(ev) => { if (ev.key === "ArrowRight") setI((p) => (p + 1) % events.length); if (ev.key === "ArrowLeft") setI((p) => (p - 1 + events.length) % events.length); }}
      tabIndex={0}>
      <div className="tf-hero-slide" key={e.id}>
        {e.image_url && <img src={e.image_url} alt="" />}
        <div className="tf-hero-overlay">
          {e.is_sponsored && <span className="tf-badge tf-badge--muted" style={{ marginBottom: 8 }}>Sponsorisé</span>}
          <h2 style={{ color: "#fff" }}>{e.title}</h2>
          <p style={{ margin: "0 0 var(--tf-space-4)", opacity: .85 }}>{e.venue}, {e.city} · {new Date(e.starts_at).toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long" })}{e.min_price != null ? ` · dès ${formatXOF(e.min_price)}` : ""}</p>
          <Link className="tf-btn tf-btn--primary" to={`/evenement/${e.slug}`}>Voir les billets</Link>
        </div>
      </div>
      <div className="tf-slider-dots" role="tablist" aria-label="Choix de la diapositive">
        {events.map((ev, idx) => (
          <button key={ev.id} role="tab" aria-selected={idx === i} aria-current={idx === i} aria-label={`Diapositive ${idx + 1}`} onClick={() => setI(idx)} />
        ))}
      </div>
    </section>
  );
}
