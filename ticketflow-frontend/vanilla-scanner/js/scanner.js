/**
 * Detection QR : BarcodeDetector natif si disponible (Android Chrome),
 * sinon telechargement dynamic du polyfill `zxing` depuis CDN (fallback
 * 3G acceptable car mis en cache par le Service Worker apres 1re visite).
 * Retourne le code brut ("TF-XXXX-..." ou URL contenant ?code=).
 */
let detectorPromise = null;
let zxingPromise = null;

function extractCode(raw) {
  if (!raw) return null;
  const m = raw.match(/TF-[A-Z0-9-]{6,}/i);
  if (m) return m[0].toUpperCase();
  try { const u = new URL(raw); return u.searchParams.get("code"); } catch { return raw.trim(); }
}

export function start(videoEl, onCode) {
  let stopFns = [];
  let stopped = false;

  if ("BarcodeDetector" in window && BarcodeDetector.getSupportedFormats) {
    detectorPromise = BarcodeDetector.getSupportedFormats()
      .then((f) => (f.includes("qr_code") ? new BarcodeDetector({ formats: ["qr_code"] }) : null));
    detectorPromise.then((det) => det && loop(det));
  } else {
    loadZxing();
  }

  function loop(det) {
    const tick = async () => {
      if (stopped) return;
      try {
        const codes = await det.detect(videoEl);
        if (codes.length) { const c = extractCode(codes[0].rawValue); if (c) onCode(c); }
      } catch { /* frame en cours de lecture : on ignore */ }
      setTimeout(tick, 350);
    };
    tick();
  }

  async function loadZxing() {
    try {
      const { BrowserMultiFormatReader } = await import("https://unpkg.com/@zxing/library@0.21.3/esm/index.js");
      const reader = new BrowserMultiFormatReader();
      reader.decodeFromVideoElement(videoEl, (_r, result) => {
        if (result && !stopped) { const c = extractCode(result.getText()); if (c) onCode(c); }
      });
      stopFns.push(() => reader.stopContinuousDecode());
    } catch { /* pas de detecteur : saisie manuelle possible */ }
  }

  return { stop() { stopped = true; stopFns.forEach((f) => f()); } };
}
