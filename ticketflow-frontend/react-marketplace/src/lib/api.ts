/**
 * Client HTTP minimaliste (fetch natif — pas d'axios : moins de poids,
 * mêmes fonctionnalités utiles ici). Gère le token Sanctum et l'erreur
 * standardisée du backend ({ message, errors? }).
 */
const TOKEN_KEY = "tf-token";

export const api = {
  token: () => localStorage.getItem(TOKEN_KEY),
  setToken: (t: string | null) => (t ? localStorage.setItem(TOKEN_KEY, t) : localStorage.removeItem(TOKEN_KEY)),

  async request<T>(path: string, options: RequestInit = {}): Promise<T> {
    const headers: Record<string, string> = {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(options.headers as Record<string, string>),
    };
    const tok = api.token();
    if (tok) headers.Authorization = `Bearer ${tok}`;

    const res = await fetch(`/api/v1${path}`, { ...options, headers });
    if (res.status === 204) return undefined as T;
    const body = await res.json().catch(() => ({}));
    if (!res.ok) {
      throw Object.assign(new Error(body.message ?? "Erreur réseau"), { status: res.status, errors: body.errors });
    }
    return body as T;
  },
  get: <T>(p: string) => api.request<T>(p),
  post: <T>(p: string, data?: unknown) => api.request<T>(p, { method: "POST", body: JSON.stringify(data ?? {}) }),
  put: <T>(p: string, data?: unknown) => api.request<T>(p, { method: "PUT", body: JSON.stringify(data ?? {}) }),
  del: <T>(p: string) => api.request<T>(p, { method: "DELETE" }),
};

/**
 * Formatage monétaire conforme au cahier des charges : « 12 500 FCFA ».
 * Groupement manuel avec espace insécable U+00A0 (Intl fr-FR moderne utilise
 * U+202F narrow-nbsp, rendu incohérent sur les vieux terminaux Android Togolais).
 */
export function formatXOF(amount: number | string): string {
  const n = typeof amount === "string" ? parseInt(amount, 10) : amount;
  const grouped = String(Math.abs(n)).replace(/\B(?=(\d{3})+(?!\d))/g, "\u00a0");
  return (n < 0 ? "-" : "") + grouped + "\u00a0FCFA";
}
