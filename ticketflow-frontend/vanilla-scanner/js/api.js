/** Client API partage du scanner — cible les routes reelles de ticketflow-backend (routes/api.php, prefixe /api/v1). */
const BASE = "/api/v1";

export function getToken() { return localStorage.getItem("tf-token"); }
export function setToken(t) { t ? localStorage.setItem("tf-token", t) : localStorage.removeItem("tf-token"); }

export async function request(path, options = {}) {
  const headers = { Accept: "application/json", "Content-Type": "application/json", ...(options.headers || {}) };
  const tok = getToken();
  if (tok) headers.Authorization = "Bearer " + tok;
  const res = await fetch(BASE + path, { ...options, headers });
  if (res.status === 401) throw Object.assign(new Error("Session expirée"), { status: 401 });
  const body = await res.json().catch(() => ({}));
  if (!res.ok) throw Object.assign(new Error(body.message || "Erreur serveur"), { status: res.status, errors: body.errors });
  return body;
}

export const api = {
  /** POST /auth/login -> { token, user } (Sanctum). */
  login: (email, password) => request("/auth/login", { method: "POST", body: JSON.stringify({ email, password }) }),
  me: () => request("/auth/me"),
  /** Evenements de l'agent : organizer (events propres) ou staff (via checkin live). */
  myEvents: async () => {
    const user = await request("/auth/me").then((r) => r.data);
    if (user.role === "organizer") {
      const r = await request("/organizer/events");
      return Array.isArray(r) ? r : r.data ?? [];
    }
    // Staff : la liste est fournie par l'organisateur principal ; on retombe sur le scan manuel.
    return [];
  },
  /** GET /checkin/batch/{eventId} -> liste blanche signee pour le mode hors-ligne. */
  batch: (eventId) => request(`/checkin/batch/${eventId}`),
  /** POST /checkin -> scan online unitaire. payload = contenu brut du QR. */
  scan: (payload, gate) => request("/checkin", { method: "POST", body: JSON.stringify({ payload, gate }) }),
  /** POST /checkin/sync -> rejeu des scans hors-ligne (entries: [{code, gate, scanned_at}]). */
  sync: (event_id, entries) => request("/checkin/sync", { method: "POST", body: JSON.stringify({ event_id, entries }) }),
};
