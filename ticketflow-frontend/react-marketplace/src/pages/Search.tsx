import { useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { useSearchParams } from "react-router-dom";
import { api } from "../lib/api";
import type { EventSummary, Paginated } from "../types";
import Layout from "../components/Layout";
import EventCard from "../components/EventCard";
import SearchBar from "../components/SearchBar";

const CATEGORIES = ["", "concert", "sport", "theatre", "festival", "conference"];
const LABELS: Record<string, string> = { "": "Toutes catégories", concert: "Concerts", sport: "Sport", theatre: "Théâtre", festival: "Festivals", conference: "Conférences" };

/** Recherche + filtres catégorie / ville / date / tri, pagination page par page. */
export default function Search() {
  const [params, setParams] = useSearchParams();
  const [page, setPage] = useState(1);
  const q = params.get("q") ?? "";
  const cat = params.get("category") ?? "";
  const city = params.get("city") ?? "";
  const date = params.get("date") ?? "";
  const sort = params.get("sort") ?? "relevance";

  const set = (k: string, v: string) => { const p = new URLSearchParams(params); v ? p.set(k, v) : p.delete(k); setParams(p); setPage(1); };

  const { data, isFetching } = useQuery({
    queryKey: ["search", q, cat, city, date, sort, page],
    queryFn: () => {
      const qs = new URLSearchParams();
      if (q) qs.set("q", q); if (cat) qs.set("category", cat); if (city) qs.set("city", city);
      if (date) qs.set("date_from", date); qs.set("sort", sort); qs.set("page", String(page)); qs.set("per_page", "12");
      return api.get<Paginated<EventSummary>>(`/events?${qs}`);
    },
  });

  return (
    <Layout>
      <h1>Références</h1>
      <div className="tf-row" style={{ flexWrap: "wrap", margin: "var(--tf-space-4) 0" }}>
        <SearchBar initial={q} />
        <select className="tf-input" style={{ width: "auto" }} value={cat} onChange={(e) => set("category", e.target.value)} aria-label="Catégorie">
          {CATEGORIES.map((c) => <option key={c} value={c}>{LABELS[c]}</option>)}
        </select>
        <input className="tf-input" style={{ width: "auto" }} type="date" value={date} onChange={(e) => set("date", e.target.value)} aria-label="À partir du" />
        <select className="tf-input" style={{ width: "auto" }} value={sort} onChange={(e) => set("sort", e.target.value)} aria-label="Trier par">
          <option value="relevance">Pertinence</option><option value="price_asc">Prix croissant</option>
          <option value="date_asc">Date proche</option><option value="popularity">Popularité</option>
        </select>
      </div>
      {!data && isFetching ? <p className="tf-muted">Chargement…</p> : null}
      <div className="tf-grid tf-grid--3">
        {data?.data.map((e) => <EventCard key={e.id} event={e} />)}
      </div>
      {(data?.meta.last_page ?? 1) > 1 && (
        <div className="tf-row" style={{ justifyContent: "center", marginTop: "var(--tf-space-6)" }}>
          <button className="tf-btn tf-btn--secondary" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>Précédent</button>
          <span className="tf-muted">Page {page} / {data?.meta.last_page}</span>
          <button className="tf-btn tf-btn--secondary" disabled={page >= (data?.meta.last_page ?? 1)} onClick={() => setPage((p) => p + 1)}>Suivant</button>
        </div>
      )}
    </Layout>
  );
}
