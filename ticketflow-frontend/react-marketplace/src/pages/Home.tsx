import { useQuery } from "@tanstack/react-query";
import { api } from "../lib/api";
import type { EventSummary, Paginated } from "../types";
import Layout from "../components/Layout";
import HeroSlider from "../components/HeroSlider";
import EventCard from "../components/EventCard";
import SearchBar from "../components/SearchBar";
import { useAuth } from "../hooks/useAuth";

/**
 * Accueil : slider à la une (sponsorisés en tête, tri fait côté serveur),
 * puis sections Tendances / Près de chez vous / Bientôt complet / Nouveautés,
 * et recommandations personnalisées si l'utilisateur est connecté
 * (endpoint /account/recommendations basé historique + favoris + ville).
 */
export default function Home() {
  const { user } = useAuth();
  const q = (key: string, url: string) =>
    useQuery({ queryKey: [key], queryFn: () => api.get<Paginated<EventSummary>>(url).then((r) => r.data) });

  const featured = q("featured", "/events?featured=1&per_page=6");
  const trending = q("trending", "/events?sort=trending&per_page=4");
  const nearby = q("nearby", "/events?sort=nearby&per_page=4");
  const almostFull = q("almost-full", "/events?filter=almost_full&per_page=4");
  const newest = q("newest", "/events?sort=newest&per_page=4");
  const recos = useQuery({
    queryKey: ["reco", user?.id], enabled: !!user,
    queryFn: () => api.get<{ data: EventSummary[] }>("/account/recommendations").then((r) => r.data),
  });

  const Section = ({ title, items }: { title: string; items?: EventSummary[] }) =>
    items?.length ? (
      <section style={{ marginTop: "var(--tf-space-8)" }} aria-label={title}>
        <h2 style={{ fontSize: "var(--tf-text-xl)" }}>{title}</h2>
        <div className="tf-grid tf-grid--4">{items.map((e) => <EventCard key={e.id} event={e} />)}</div>
      </section>
    ) : null;

  return (
    <Layout>
      <div className="tf-stack">
        <div style={{ textAlign: "center", paddingBlock: "var(--tf-space-6)" }}>
          <h1>Vivez les meilleurs événements du Togo</h1>
          <p className="tf-muted" style={{ maxWidth: 520, margin: "0 auto var(--tf-space-5)" }}>
            Concerts, sport, théâtre, festivals — billets au juste prix, paiement Moov Money ou Mixx by Yas, frais intégrés au prix affiché.
          </p>
          <div style={{ display: "flex", justifyContent: "center" }}><SearchBar /></div>
        </div>
        <HeroSlider events={featured.data ?? []} />
        {recos.data && <Section title="Recommandé pour vous" items={recos.data} />}
        <Section title="Tendances" items={trending.data} />
        <Section title="Près de chez vous" items={nearby.data} />
        <Section title="Bientôt complet" items={almostFull.data} />
        <Section title="Nouveautés" items={newest.data} />
      </div>
    </Layout>
  );
}
