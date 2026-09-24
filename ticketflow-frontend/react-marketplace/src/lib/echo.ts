/**
 * Laravel Echo + Reverb (WebSocket) → mise à jour temps réel du plan de sièges.
 * Chargé une seule fois ; no-op si VITE_REVERB_APP_KEY absent (le siège se
 * rafraîchit alors par polling — dégradation Graceful, essentiel avec un
 * réseau mobile instable au Togo).
 */
import Echo from "laravel-echo";
import Pusher from "pusher-js";

const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;

declare global { interface Window { Pusher: typeof Pusher; Echo?: Echo<any> } }
window.Pusher = Pusher;

export const echo = key
  ? new Echo({
      broadcaster: "reverb",
      key,
      wsHost: (import.meta.env.VITE_REVERB_HOST as string) ?? "localhost",
      wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
      forceTLS: location.protocol === "https:",
      enableStats: false,
      // Reconnexion exponentielle : les coupures réseau sont fréquentes en soirée d'événement.
      reconnectionDelay: 1000,
      reconnectionDelayMax: 10000,
    })
  : null;

if (echo) window.Echo = echo;
