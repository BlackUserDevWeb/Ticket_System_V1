import { useEffect, useState } from "react";

/** Compte à rebours du hold de 5 minutes (expiration côté serveur = ReleaseExpiredHolds cron). */
export function useCountdown(expiresAtIso: string | null) {
  const [seconds, setSeconds] = useState<number | null>(null);
  useEffect(() => {
    if (!expiresAtIso) { setSeconds(null); return; }
    const end = new Date(expiresAtIso).getTime();
    const tick = () => setSeconds(Math.max(0, Math.round((end - Date.now()) / 1000)));
    tick();
    const id = setInterval(tick, 1000);
    return () => clearInterval(id);
  }, [expiresAtIso]);
  return seconds;
}
