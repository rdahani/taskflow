/* TaskFlow — service worker (cache assets statiques uniquement) */
const CACHE = 'taskflow-sw-v2';
const STATIC_EXTS = ['.css', '.js', '.woff', '.woff2', '.ttf', '.otf',
                     '.png', '.jpg', '.jpeg', '.gif', '.webp', '.ico', '.svg'];

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // Ignorer les autres origines
  if (url.origin !== self.location.origin) return;

  // N'intercepter QUE les assets statiques (CSS, JS, fonts, images)
  const isStatic = STATIC_EXTS.some((ext) => url.pathname.endsWith(ext));
  if (!isStatic) return; // laisser le navigateur gérer les pages PHP

  event.respondWith(
    caches.match(req).then((cached) => {
      if (cached) return cached;
      return fetch(req).then((response) => {
        if (response && response.status === 200) {
          const clone = response.clone();
          caches.open(CACHE).then((cache) => cache.put(req, clone));
        }
        return response;
      });
    })
  );
});
