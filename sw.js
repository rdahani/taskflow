/* TaskFlow — service worker minimal (cache des assets statiques) */
const CACHE = 'taskflow-sw-v1';
const ASSETS = [
  './assets/css/style.css',
  './assets/css/inter.css',
  './assets/js/app.js'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(ASSETS).catch(() => {}))
  );
  self.skipWaiting();
});

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
  if (url.origin !== self.location.origin) return;
  if (url.pathname.endsWith('.php') && !url.pathname.endsWith('index.php')) {
    return;
  }
  event.respondWith(
    fetch(req).catch(() => caches.match(req).then((r) => r || caches.match('./index.php')))
  );
});
