import { type ReactNode } from "react";
import { Link, NavLink, useNavigate } from "react-router-dom";
import { useAuth } from "../hooks/useAuth";
import { useTheme } from "../hooks/useTheme";

/**
 * Gabarit commun : header desktop + tab bar mobile (le trafic togolais est
 * majoritairement mobile → navigation repensée en bas d'écran, <768px).
 */
export default function Layout({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth();
  const { theme, toggle } = useTheme();
  const nav = useNavigate();

  return (
    <>
      <header style={{ borderBottom: "1px solid var(--tf-border)", position: "sticky", top: 0, background: "var(--tf-bg)", zIndex: 30 }}>
        <div className="tf-container" style={{ display: "flex", alignItems: "center", gap: "var(--tf-space-5)", height: 64 }}>
          <Link to="/" style={{ fontWeight: 700, fontSize: "var(--tf-text-lg)", color: "var(--tf-text)" }} aria-label="TicketFlow — accueil">
            Ticket<span style={{ color: "var(--tf-accent)" }}>Flow</span>
          </Link>
          <nav className="tf-row" style={{ marginLeft: "auto" }} aria-label="Navigation principale">
            <NavLink to="/recherche" style={({ isActive }) => ({ color: isActive ? "var(--tf-accent)" : "var(--tf-text-muted)", fontWeight: 600 })} className="tf-hide-mobile">Recherche</NavLink>
            <NavLink to="/compte" style={({ isActive }) => ({ color: isActive ? "var(--tf-accent)" : "var(--tf-text-muted)", fontWeight: 600 })}>Mon compte</NavLink>
            <button className="tf-btn tf-btn--secondary" onClick={toggle} aria-label={theme === "light" ? "Activer le mode sombre" : "Activer le mode clair"}>
              {theme === "light" ? "Sombre" : "Clair"}
            </button>
            {user
              ? <button className="tf-btn tf-btn--ghost" onClick={() => { logout(); nav("/"); }}>Déconnexion</button>
              : <button className="tf-btn tf-btn--primary" onClick={() => nav("/connexion")}>Connexion</button>}
          </nav>
        </div>
      </header>
      <main style={{ minHeight: "70vh", paddingBottom: "calc(var(--tf-space-10) + env(safe-area-inset-bottom))" }}>
        <div className="tf-container" style={{ paddingTop: "var(--tf-space-6)" }}>{children}</div>
      </main>
      <nav className="tf-tabbar" aria-label="Navigation mobile">
        <NavLink to="/" aria-current="page">Accueil</NavLink>
        <NavLink to="/recherche">Recherche</NavLink>
        <NavLink to="/compte">Compte</NavLink>
      </nav>
      <footer style={{ borderTop: "1px solid var(--tf-border)", padding: "var(--tf-space-6) 0" }} className="tf-muted">
        <div className="tf-container" style={{ fontSize: "var(--tf-text-sm)" }}>
          TicketFlow — billetterie événementielle au Togo. Paiements 100 % Mobile Money (Moov Money, Mixx by Yas). Devise : FCFA (XOF).
        </div>
      </footer>
    </>
  );
}
