import { Link } from "react-router-dom";
import type { EventSummary } from "../types";
import { formatXOF } from "../lib/api";

const CATEGORIES: Record<string, string> = {
  concert: "Concert", sport: "Sport", theatre: "Théâtre", festival: "Festival", conference: "Conférence", autre: "Événement",
};

export default function EventCard({ event }: { event: EventSummary }) {
  const nearFull = (event.fill_ratio ?? 0) >= 0.85;
  return (
    <article className="tf-card tf-card--hoverable" style={{ padding: 0, overflow: "hidden" }}>
      <Link to={`/evenement/${event.slug}`} style={{ color: "inherit", display: "block" }}>
        <div style={{ aspectRatio: "16/9", background: "var(--tf-border)", position: "relative" }}>
          {event.image_url && <img src={event.image_url} alt="" loading="lazy" style={{ width: "100%", height: "100%", objectFit: "cover" }} />}
          {/* Badge sponsor DISCRET (cahier des charges) + alerte « bientôt complet » */}
          {event.is_sponsored && <span className="tf-badge tf-badge--muted" style={{ position: "absolute", top: 8, left: 8 }}>Sponsorisé</span>}
          {nearFull && <span className="tf-badge tf-badge--warning" style={{ position: "absolute", top: 8, right: 8 }}>Bientôt complet</span>}
        </div>
        <div style={{ padding: "var(--tf-space-4)" }}>
          <div className="tf-muted" style={{ fontSize: "var(--tf-text-xs)", textTransform: "uppercase", letterSpacing: ".05em" }}>
            {CATEGORIES[event.category] ?? event.category} · {new Date(event.starts_at).toLocaleDateString("fr-FR", { day: "numeric", month: "long", year: "numeric" })}
          </div>
          <h3 style={{ margin: "var(--tf-space-2) 0", fontSize: "var(--tf-text-lg)" }}>{event.title}</h3>
          <div className="tf-muted" style={{ fontSize: "var(--tf-text-sm)" }}>{event.venue}, {event.city}</div>
          {event.min_price != null && (
            <div style={{ marginTop: "var(--tf-space-3)" }}>
              <span className="tf-muted" style={{ fontSize: "var(--tf-text-xs)" }}>à partir de </span>
              <span className="tf-money">{formatXOF(event.min_price)}</span>
            </div>
          )}
        </div>
      </Link>
    </article>
  );
}
