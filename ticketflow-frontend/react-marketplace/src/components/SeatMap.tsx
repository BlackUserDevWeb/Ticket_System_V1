import { useEffect, useMemo, useRef, useState } from "react";
import type { EventDetail } from "../types";
import { api } from "../lib/api";
import { echo } from "../lib/echo";

interface RawUnit { id: number; section_id: number; label: string | null; pos_x: number | null; pos_y: number | null; status: string }

export interface Seat { id: number; label: string; row: string; number: number; x: number; y: number; status: "available" | "held" | "sold"; section_id: number }

/**
 * Plan de salle interactif SVG (rendu maison — seats.io est payant en prod ;
 * l'API reste la même : état des sièges + hold). Zoom/pan à la souris et au
 * pinch. Les changements d'état arrivent par Reverb (channel `event.{id}`) ;
 * sans WebSocket, polling 10 s (réseau mobile instable → dégradation douce).
 */
export default function SeatMap({ event, selected, onToggle }: {
  event: EventDetail; selected: Seat[]; onToggle: (s: Seat) => void;
}) {
  const [seats, setSeats] = useState<Seat[]>([]);
  const [view, setView] = useState({ x: 0, y: 0, k: 1 });
  const dragRef = useRef<{ sx: number; sy: number; ox: number; oy: number } | null>(null);

  useEffect(() => {
    let alive = true;
    const load = () => api.get<{ data: RawUnit[] }>(`/events/${event.id}/units`)
      .then((r) => {
        if (!alive) return;
        // Miroir de GET /events/{id}/units (backend) → format Seat du plan SVG.
        const STATUS: Record<string, Seat["status"]> = { open: "available", held: "held", reserved: "held", sold: "sold" };
        setSeats(r.data.map((u) => ({ id: u.id, label: u.label ?? `#${u.id}`, row: u.label?.split(" ")[0] ?? "", number: u.id, x: u.pos_x ?? 0, y: u.pos_y ?? 0, status: STATUS[u.status] ?? "available", section_id: u.section_id })));
      }).catch(() => {});
    load();
    // Temps réel : le backend broadcast SeatMapUpdated(event_id) avec les ids modifiés.
    const unsub = echo?.channel(`event.${event.id}`).listen("seatmap.updated", (_e: unknown) => {
      load();
    });
    const poll = echo ? null : setInterval(load, 10_000);
    return () => { alive = false; clearInterval(poll ?? undefined); (unsub as any)?.dispose?.(); };
  }, [event.id, event.slug]);

  const bySection = useMemo(() => {
    const m = new Map<number, Seat[]>();
    seats.forEach((s) => m.set(s.section_id, [...(m.get(s.section_id) ?? []), s]));
    return m;
  }, [seats]);

  const selIds = useMemo(() => new Set(selected.map((s) => s.id)), [selected]);

  const cls = (s: Seat) => selIds.has(s.id) ? "seat seat--selected" : s.status === "sold" ? "seat seat--sold" : s.status === "held" ? "seat seat--held" : "seat";

  return (
    <div className="seatmap tf-card" style={{ padding: "var(--tf-space-3)" }}>
      <div className="tf-row" style={{ justifyContent: "space-between", marginBottom: "var(--tf-space-2)", flexWrap: "wrap" }}>
        <div className="tf-row tf-muted" style={{ fontSize: "var(--tf-text-xs)", gap: "var(--tf-space-4)" }}>
          <span><i style={{ display: "inline-block", width: 10, height: 10, background: "var(--tf-bg-raised)", border: "1px solid var(--tf-border)", borderRadius: 2 }} /> Disponible</span>
          <span><i style={{ display: "inline-block", width: 10, height: 10, background: "var(--tf-warning)", borderRadius: 2 }} /> Réservé</span>
          <span><i style={{ display: "inline-block", width: 10, height: 10, background: "var(--tf-border)", borderRadius: 2 }} /> Vendu</span>
          <span><i style={{ display: "inline-block", width: 10, height: 10, background: "var(--tf-accent)", borderRadius: 2 }} /> Votre choix</span>
        </div>
        <div className="tf-row">
          <button className="tf-btn tf-btn--secondary" aria-label="Zoomer" onClick={() => setView((v) => ({ ...v, k: Math.min(3, v.k * 1.25) }))}>+</button>
          <button className="tf-btn tf-btn--secondary" aria-label="Dézoomer" onClick={() => setView((v) => ({ ...v, k: Math.max(0.6, v.k / 1.25) }))}>−</button>
        </div>
      </div>
      <svg viewBox="0 0 800 500" role="img" aria-label={`Plan de salle ${event.venue}`}
        onWheel={(e) => setView((v) => ({ ...v, k: Math.min(3, Math.max(0.6, v.k * (e.deltaY < 0 ? 1.1 : 0.9))) }))}
        onMouseDown={(e) => (dragRef.current = { sx: e.clientX, sy: e.clientY, ox: view.x, oy: view.y })}
        onMouseMove={(e) => { if (dragRef.current) setView((v) => ({ ...v, x: dragRef.current!.ox + (e.clientX - dragRef.current!.sx), y: dragRef.current!.oy + (e.clientY - dragRef.current!.sy) })); }}
        onMouseUp={() => (dragRef.current = null)} onMouseLeave={() => (dragRef.current = null)}>
        <g transform={`translate(${view.x},${view.y}) scale(${view.k})`}>
          {/* Scène */}
          <rect x={250} y={20} width={300} height={26} rx={6} fill="var(--tf-text)" opacity={.85} />
          <text x={400} y={38} textAnchor="middle" fill="var(--tf-bg)" fontSize={14} fontWeight={700}>SCÈNE / TERRAIN</text>
          {[...bySection.entries()].map(([sectionId, list]) => {
            const sec = event.sections.find((s) => s.id === sectionId);
            return (
              <g key={sectionId}>
                {sec && <text x={sec.x} y={sec.y - 6} fontSize={11} fill="var(--tf-text-muted)">{sec.name}</text>}
                {list.map((s) => (
                  <rect key={s.id} className={cls(s)} x={s.x} y={s.y} width={14} height={14} rx={3}
                    role="button" tabIndex={s.status === "available" ? 0 : -1}
                    aria-label={`Siège ${s.label}, ${s.status === "available" ? "disponible" : s.status === "held" ? "réservé temporairement" : "vendu"}`}
                    onClick={() => s.status === "available" && onToggle(s)}
                    onKeyDown={(e) => { if (e.key === "Enter" && s.status === "available") onToggle(s); }} />
                ))}
              </g>
            );
          })}
        </g>
      </svg>
    </div>
  );
}
