/**
 * File d'attente IndexedDB pour les scans effectues HORS LIGNE.
 * Strategie : le billet est valide si son hash figure dans la liste blanche
 * synchronisee avant la coupure ; l'enregistrement du check-in est rejoue
 * des que le reseau revient (idempotent grace a un UUID de scan).
 */
const DB_NAME = "tf-scanner";
const STORE = "pending_scans";

function openDb() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, 1);
    req.onupgradeneeded = () => { req.result.createObjectStore(STORE, { keyPath: "scan_uuid" }); };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

export async function enqueue(scan) {
  const db = await openDb();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(STORE, "readwrite");
    tx.objectStore(STORE).add(scan);
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
}

export async function pendingCount() {
  const db = await openDb();
  return new Promise((resolve) => {
    const req = db.transaction(STORE).objectStore(STORE).count();
    req.onsuccess = () => resolve(req.result);
  });
}

/**
 * Rejeu par LOT (endpoint /checkin/sync accepte jusqu'a 500 entrees) :
 * handler(items) doit resoudre si le serveur a accepte ; sinon on garde tout.
 * Retourne le nombre restant en attente.
 */
export async function flushAll(handler) {
  const db = await openDb();
  const items = await new Promise((resolve) => {
    const req = db.transaction(STORE).objectStore(STORE).getAll();
    req.onsuccess = () => resolve(req.result);
  });
  if (!items.length) return 0;
  try {
    await handler(items);
    const tx = db.transaction(STORE, "readwrite");
    items.forEach((i) => tx.objectStore(STORE).delete(i.scan_uuid));
    await new Promise((r) => (tx.oncomplete = r));
    return 0;
  } catch { return items.length; } // reseau toujours indisponible : on garde la file
}
