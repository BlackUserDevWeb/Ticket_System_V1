import { useEffect, useRef, useState } from "react";
import { useNavigate } from "react-router-dom";
import { api } from "../lib/api";

interface Suggestion { type: "event" | "artist" | "city"; label: string; value: string }

/** Barre de recherche avec autocomplétion (endpoint /events/suggest, debounce 250 ms). */
export default function SearchBar({ initial = "" }: { initial?: string }) {
  const [q, setQ] = useState(initial);
  const [items, setItems] = useState<Suggestion[]>([]);
  const [open, setOpen] = useState(false);
  const boxRef = useRef<HTMLDivElement>(null);
  const nav = useNavigate();

  useEffect(() => {
    if (q.trim().length < 2) { setItems([]); return; }
    const t = setTimeout(async () => {
      try { const r = await api.get<{ data: Suggestion[] }>(`/events/suggest?q=${encodeURIComponent(q)}`); setItems(r.data); setOpen(true); }
      catch { /* suggestions non critiques : on échoue en silence */ }
    }, 250);
    return () => clearTimeout(t);
  }, [q]);

  useEffect(() => {
    const close = (e: MouseEvent) => { if (!boxRef.current?.contains(e.target as Node)) setOpen(false); };
    document.addEventListener("mousedown", close);
    return () => document.removeEventListener("mousedown", close);
  }, []);

  const submit = (value: string) => { setOpen(false); nav(`/recherche?q=${encodeURIComponent(value)}`); };

  return (
    <div ref={boxRef} style={{ position: "relative", maxWidth: 560, width: "100%" }} role="combobox" aria-expanded={open} aria-haspopup="listbox">
      <form onSubmit={(e) => { e.preventDefault(); submit(q); }} role="search">
        <label htmlFor="tf-search" className="tf-label" style={{ position: "absolute", left: -9999 }}>Rechercher un événement, un artiste, une ville</label>
        <input id="tf-search" className="tf-input" placeholder="Concert, match, festival, artiste…" value={q}
          onChange={(e) => setQ(e.target.value)} onFocus={() => items.length && setOpen(true)} autoComplete="off" />
      </form>
      {open && items.length > 0 && (
        <ul role="listbox" style={{ position: "absolute", top: "100%", left: 0, right: 0, margin: "var(--tf-space-1) 0 0", padding: 0, listStyle: "none", background: "var(--tf-bg)", border: "1px solid var(--tf-border)", borderRadius: "var(--tf-radius-md)", boxShadow: "var(--tf-shadow-pop)", zIndex: 20 }}>
          {items.map((s) => (
            <li key={s.type + s.value} role="option" aria-selected={false}>
              <button className="tf-btn tf-btn--ghost" style={{ width: "100%", justifyContent: "flex-start" }} onClick={() => submit(s.label)}>
                <span className="tf-muted" style={{ fontSize: "var(--tf-text-xs)", marginRight: 8 }}>{s.type}</span>{s.label}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
