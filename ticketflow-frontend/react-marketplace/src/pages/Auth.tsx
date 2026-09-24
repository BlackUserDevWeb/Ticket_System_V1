import { useState } from "react";
import { useNavigate } from "react-router-dom";
import Layout from "../components/Layout";
import { useAuth } from "../hooks/useAuth";

/** Connexion / inscription en un seul composant (le backend renvoie token + user). */
export default function Auth({ mode = "login" }: { mode?: "login" | "register" }) {
  const { login, register } = useAuth();
  const nav = useNavigate();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [name, setName] = useState("");
  const [phone, setPhone] = useState("");
  const [err, setErr] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true); setErr(null);
    try {
      if (mode === "login") await login(email, password);
      else await register({ name, email, phone, password, password_confirmation: password });
      nav("/compte");
    } catch (ex) {
      const ex2 = ex as Error & { errors?: Record<string, string[]> };
      setErr(ex2.errors?.email?.[0] ?? ex2.errors?.password?.[0] ?? ex2.message ?? "Erreur");
    } finally { setBusy(false); }
  };

  return (
    <Layout>
      <div className="tf-card" style={{ maxWidth: 420, margin: "var(--tf-space-8) auto" }}>
        <h1>{mode === "login" ? "Connexion" : "Créer un compte"}</h1>
        {err && <p role="alert" className="tf-muted" style={{ color: "var(--tf-danger)" }}>{err}</p>}
        <form onSubmit={submit} className="tf-stack">
          {mode === "register" && (
            <>
              <div><label className="tf-label" htmlFor="reg-name">Nom complet</label>
                <input id="reg-name" className="tf-input" value={name} onChange={(e) => setName(e.target.value)} required /></div>
              <div><label className="tf-label" htmlFor="reg-phone">Téléphone (+228)</label>
                <input id="reg-phone" className="tf-input" value={phone} onChange={(e) => setPhone(e.target.value)} inputMode="tel" required /></div>
            </>
          )}
          <div><label className="tf-label" htmlFor="auth-email">Email</label>
            <input id="auth-email" className="tf-input" type="email" value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="email" required /></div>
          <div><label className="tf-label" htmlFor="auth-pwd">Mot de passe</label>
            <input id="auth-pwd" className="tf-input" type="password" value={password} onChange={(e) => setPassword(e.target.value)} autoComplete={mode === "login" ? "current-password" : "new-password"} required minLength={8} /></div>
          <button className="tf-btn tf-btn--primary" disabled={busy}>{busy ? "…" : mode === "login" ? "Se connecter" : "S'inscrire"}</button>
        </form>
        <p className="tf-muted" style={{ marginTop: "var(--tf-space-5)", fontSize: "var(--tf-text-sm)" }}>
          {mode === "login" ? "Pas encore de compte ? " : "Déjà client ? "}
          <a href={mode === "login" ? "/inscription" : "/connexion"}>{mode === "login" ? "Inscription" : "Connexion"}</a>
        </p>
      </div>
    </Layout>
  );
}
