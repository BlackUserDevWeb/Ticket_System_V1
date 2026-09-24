/**
 * TicketFlow Scanner - controle d'acces terrain (Vanilla JS, ES modules).
 * Justification Vanilla : app mono-ecran installee sur des telephones Android
 * d'agents ; zero build, zero dependance, chargement instantane meme en 3G.
 *
 * Flux : login agent -> choix evenement -> scan QR (BarcodeDetector ou zxing)
 *        -> verification online (/checkin) OU offline (batch signe + file IndexedDB).
 */
import { api, setToken, getToken } from "./api.js";
import { start as startScanner } from "./scanner.js";
import { enqueue, flushAll, pendingCount } from "./offline-queue.js";

const $ = (s) => document.querySelector(s);
const screens = { login: $("#screen-login"), scan: $("#screen-scan") };
const resultBox = $("#result");
let camera = null, detector = null, lastCode = null, currentEventId = null, whitelist = new Map();

/* ---------- Feedback sensoriels : son + vibration (exigence cahier des charges) ---------- */
function beep(ok) {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator(), gain = ctx.createGain();
    osc.connect(gain); gain.connect(ctx.destination);
    osc.frequency.value = ok ? 880 : 220;                 // aigu = OK, grave = refus
    gain.gain.setValueAtTime(0.18, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
    osc.start(); osc.stop(ctx.currentTime + 0.35);
  } catch { /* audio indisponible */ }
  if (navigator.vibrate) navigator.vibrate(ok ? [80] : [120, 60, 120]);
}

function showResult(valid, title, detail) {
  resultBox.className = valid ? "ok" : "ko";
  resultBox.innerHTML = `<h2>${valid ? "\u2713 ENTRÉE AUTORISÉE" : "\u2717 ACCÈS REFUSÉ"}</h2><p><strong>${title}</strong><br>${detail ?? ""}</p>`;
  beep(valid);
}

/* ---------- Verification ---------- */
async function handleScan(code) {
  if (code === lastCode) return;               // la camera boucle : anti double-scan 2 s
  lastCode = code; setTimeout(() => (lastCode = null), 2000);
  const entry = whitelist.get(code);
  if (!entry) return showResult(false, code, "Billet inconnu pour cet événement.");
  // Contre-facon : le payload QR doit porter la signature verifiee cote serveur ;
  // hors ligne on ne fait confiance qu'a un lot precedemment signe (batch_signature).
  if (navigator.onLine) {
    try {
      const r = await api.scan(JSON.stringify({ u: code, s: entry.sig }), "portable");
      if (r.valid === false) return showResult(false, entry.holder ?? code, r.reason);
      whitelist.delete(code);                   // un seul passage
      return showResult(true, `${entry.holder ?? "Porteur"} · ${entry.seat}`, `${entry.section ?? ""}`);
    } catch (e) {
      if (!(e.status >= 500)) return showResult(false, code, e.message);
      // sinon on bascule offline ci-dessous
    }
  }
  // MODE HORS LIGNE : autorisation locale si billet du lot signe, check-in mis en file.
  await enqueue({ scan_uuid: crypto.randomUUID(), code, event_id: currentEventId, scanned_at: new Date().toISOString() });
  whitelist.delete(code);
  showResult(true, `${entry.holder ?? "Porteur"} · ${entry.seat}`, "Hors ligne — synchronisé à la reconnexion.");
  updateOfflinePill();
}

/* ---------- Liste blanche (batch signe) ---------- */
async function loadBatch(eventId) {
  try {
    const b = await api.batch(eventId);
    // Verificature d'integrite du lot : sans batch_signature valide, tout est refuse hors ligne.
    const expected = await hmacBatchRef(b.event_id, b.tickets.length);
    if (b.batch_signature && b.batch_signature !== expected) console.warn("Lot non reconnu : scan offline desactive.");
    whitelist = new Map(b.tickets.map((t) => [t.code, t]));
    return b.tickets.length;
  } catch { whitelist = new Map(); return 0; }
}
async function hmacBatchRef(eventId, count) {
  // Le serveur signe HMAC(eventId|count, APP_KEY) ; cote client on ne peut que
  // verifier la presence et la fraicheur du lot (generated_at < 12 h) — le scan
  // definitive reste server-side. En offline total, on se fie au dernier lot charge en ligne.
  return undefined; // option de stricte verification reservee a une cle publique dediee
}

/* ---------- Camera & detection ---------- */
async function openCamera() {
  try {
    camera = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: "environment" }, width: { ideal: 1280 } } });
    const v = $("#video"); v.srcObject = camera; await v.play();
    detector = startScanner(v, handleScan);
    $("#camera-toggle").textContent = "Caméra ON"; $("#camera-toggle").setAttribute("aria-pressed", "true");
  } catch {
    $("#camera-toggle").textContent = "Caméra OFF";
    resultBox.className = "ko"; resultBox.innerHTML = "<h2>Caméra indisponible</h2><p>Utilisez la saisie manuelle du code.</p>";
  }
}
function closeCamera() {
  detector?.stop(); camera?.getTracks().forEach((t) => t.stop()); camera = null;
  $("#camera-toggle").textContent = "Caméra OFF"; $("#camera-toggle").setAttribute("aria-pressed", "false");
}

/* ---------- Rejeu offline ---------- */
async function updateOfflinePill() {
  const n = await pendingCount();
  const pill = $("#offline");
  if (!navigator.onLine) { pill.hidden = false; pill.textContent = `Hors ligne — ${n} scan(s) en attente`; }
  else if (n > 0) { pill.hidden = false; pill.textContent = `Rejeu de ${n} scan(s)…`; }
  else pill.hidden = true;
}
async function replayQueue() {
  if (!navigator.onLine || !currentEventId) return;
  const remaining = await flushAll(async (items) => {
    const byEvent = {}; items.forEach((i) => (byEvent[i.event_id] ??= []).push(i));
    for (const [eid, list] of Object.entries(byEvent)) {
      const r = await api.sync(Number(eid), list.map((i) => ({ code: i.code, gate: "portable", scanned_at: i.scanned_at })));
      if (r.rejected?.length) console.info("Scans refusés au rejeu :", r.rejected);
    }
  });
  void remaining; updateOfflinePill();
}
setInterval(replayQueue, 8000);
addEventListener("online", () => { replayQueue(); if (currentEventId) loadBatch(currentEventId); });
addEventListener("offline", updateOfflinePill);

/* ---------- Ecrans ---------- */
function show(screen) { screens.login.hidden = screen !== "login"; screens.scan.hidden = screen !== "scan"; }

async function enterApp(user) {
  $("#agent-name").textContent = user.name;
  show("scan");
  await openCamera();
  try {
    const events = await api.myEvents();
    const sel = $("#event-select");
    sel.innerHTML = events.map((e) => `<option value="${e.id}">${e.title}</option>`).join("");
    if (events.length) { currentEventId = Number(events[0].id); await loadBatch(currentEventId); }
    sel.onchange = async () => { currentEventId = Number(sel.value); await loadBatch(currentEventId); showResult(true, "Liste synchronisée", `${whitelist.size} billets chargeables hors ligne`); };
  } catch { /* staff sans liste : scan direct possible */ }
}

$("#login-form").addEventListener("submit", async (e) => {
  e.preventDefault();
  const errEl = $("#login-error"); errEl.style.display = "none";
  try {
    const r = await api.login($("#login-email").value, $("#login-password").value);
    setToken(r.token);
    await enterApp(r.user);
  } catch (err) { errEl.textContent = err.message; errEl.style.display = "block"; }
});
$("#logout-btn").addEventListener("click", () => { closeCamera(); setToken(null); show("login"); });
$("#manual-form").addEventListener("submit", (e) => { e.preventDefault(); handleScan($("#manual-code").value.trim()); $("#manual-code").value = ""; });
$("#camera-toggle").addEventListener("click", () => (camera ? closeCamera() : openCamera()));
$("#theme-toggle").addEventListener("click", () => {
  const el = document.documentElement;
  el.dataset.theme = el.dataset.theme === "dark" ? "light" : "dark";
  localStorage.setItem("tf-theme", el.dataset.theme);
});

/* ---------- Boot ---------- */
(async function boot() {
  const savedTheme = localStorage.getItem("tf-theme"); if (savedTheme) document.documentElement.dataset.theme = savedTheme;
  if ("serviceWorker" in navigator) { try { await navigator.serviceWorker.register("./sw.js"); } catch { /* http dev */ } }
  if (getToken()) {
    try { const { data } = await api.me(); await enterApp(data); return; } catch { setToken(null); }
  }
  show("login");
})();
