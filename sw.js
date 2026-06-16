/* X-Plore service worker — installable PWA shell.
 * Strategy: cache-first for static assets only.
 * Dynamic file listings (index.php) always go to the network so they stay fresh. */
const CACHE = 'xplore-v1';
const ASSETS = [
  'asset/manifest.json',
  'asset/favicon-32x32.png',
  'asset/favicon-512x512.png',
  'asset/android-icon-192x192.png',
  'asset/apple-icon-180x180.png'
];

self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open(CACHE)
      .then((c) => c.addAll(ASSETS))
      .catch(() => {})           // a missing asset must not block install
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  // Serve only static assets from cache; let everything else hit the network.
  if (e.request.method === 'GET' && url.pathname.includes('/asset/')) {
    e.respondWith(caches.match(e.request).then((r) => r || fetch(e.request)));
  }
});
