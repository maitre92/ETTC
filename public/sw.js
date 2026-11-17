/* Service worker d'ETTC (applications installées : site et administration). Une page lue hors connexion répond
   « Pas de connexion » au lieu de l'écran d'erreur du navigateur ; rien n'est mis en cache, tout vient du serveur. */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

const OFFLINE_PAGE = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
    + '<title>Pas de connexion — ETTC</title><style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
    + 'font-family:system-ui,sans-serif;background:#f4f9fd;color:#111c2b;text-align:center;padding:24px}main{max-width:360px}'
    + 'img{width:120px}h1{font-size:1.3rem;margin:16px 0 8px}button{margin-top:16px;border:0;border-radius:10px;padding:12px 22px;'
    + 'background:#0c68b2;color:#fff;font-weight:700;font-size:1rem}</style></head><body><main>'
    + '<img src="/assets/pwa/icon-192.png" alt="ETTC" onerror="this.remove()"><h1>Pas de connexion internet</h1>'
    + '<p>Vérifiez votre connexion (données mobiles ou Wi-Fi), puis réessayez.</p>'
    + '<button onclick="location.reload()">Réessayer</button></main></body></html>';

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.mode !== 'navigate' || request.method !== 'GET') return;
    event.respondWith(fetch(request).catch(() => new Response(OFFLINE_PAGE, { headers: { 'Content-Type': 'text/html; charset=utf-8' } })));
});
