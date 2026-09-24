/**
 * Service Worker — PWA installable + fonctionnement HORS LIGNE du scanner.
 * Strategies :
 *  - NetworkFirst pour l'API (donnees fraiches si reseau, cache sinon) ;
 *  - CacheFirst pour les assets (tokens.css, js, icone) -> demarrage instantane ;
 *  - Pre-cache de la coquille applicative a l'installation.
 */
const SHELL = "tf-scanner-shell-v1";
const ASSETS = [
  "./index.html", "./manifest.webmanifest",
  "./js/app.js", "./js/api.js", "./js/scanner.js", "./js/offline-queue.js",
  "../design-system/tokens.css", "../design-system/icons.svg",
];

self.addEventListener("install", (e) => {
  e.waitUntil(caches.open(SHELL).then((c) => c.addAll(ASSETS)).then(() => self.skipWaiting()));
});
self.addEventListener("activate", (e) => {
  e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k !== SHELL).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener("fetch", (e) => {
  const url = new URL(e.request.url);
  if (url.pathname.includes("/api/")) {
    // NetworkFirst : timeout 4 s (rezo mobile togolais lent) puis repli cache.
    e.respondWith(
      Promise.race([e.fetch? fetch(e.request): fetch(e.request), new Promise((_, rej) => setTimeout(() => rej("timeout"), 4000))])
        .then((res) => { const clone = res.clone(); caches.open(SHELL).then((c) => c.put(e.request, clone)); return res; })
        .catch(() => caches.match(e.request).then((r) => r || Response.json({ message: "Hors ligne" }, { status: 503 })))
    );
    return;
  }
  // CacheFirst pour tout le reste (assets statiques).
  e.respondWith(caches.match(e.request).then((hit) => hit || fetch(e.request).then((res) => {
    const clone = res.clone(); caches.open(SHELL).then((c) => c.put(e.request, clone)); return res;
  }).catch(() => caches.match("./index.html"))));
});
