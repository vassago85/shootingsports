/**
 * Shooting Sports PWA service worker.
 *
 * Intentionally minimal. The Expo Progressive Web Apps guide warns that
 * an aggressive cache will pin shooters to a stale JS bundle, with no
 * way to request an update short of clearing site data. We therefore:
 *
 *   1. Never cache anything matching /api/ (JSON API lives at /api/v1).
 *   2. Never cache cross-origin requests (Google Fonts, map tiles).
 *   3. Serve navigations and same-origin GETs network-first, falling
 *      back to the last cached copy only when offline.
 *
 * Bump CACHE_NAME whenever the shell semantics change so the install
 * step evicts the previous version. Expo bundle filenames are already
 * content-hashed (`_expo/static/js/web/*.js`), so a fresh deploy will
 * populate new cache entries alongside the old ones; the activate step
 * drops the stale cache.
 */

const CACHE_NAME = 'shooting-sports-v1';

self.addEventListener('install', (event) => {
  // Take over from any previous worker as soon as this one installs,
  // so a user who already has the PWA open picks up the new bundle
  // on next navigation rather than two reloads later.
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)),
      );
      await self.clients.claim();
    })(),
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;

  // Only GETs are safe to cache; writes (POST /me/saved-events,
  // DELETE /me/device-tokens) must always hit the network.
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);

  // Cross-origin assets are owned by the other origin's cache policy.
  // We stay out of the way (no CORS headaches, no stale third-party data).
  if (url.origin !== self.location.origin) {
    return;
  }

  // The JSON API must never be cached by the shell. Stale /api responses
  // show yesterday's match list, which is worse than an error.
  if (url.pathname.startsWith('/api/')) {
    return;
  }

  event.respondWith(networkFirst(request));
});

/**
 * Network-first with a cached fallback. A fresh network response
 * repopulates the cache so the next offline navigation still works.
 */
async function networkFirst(request) {
  const cache = await caches.open(CACHE_NAME);

  try {
    const response = await fetch(request);

    // Only cache "clean" 200 OK responses; opaque / redirected / error
    // responses are not useful offline and can lock us into a broken state.
    if (response && response.status === 200 && response.type === 'basic') {
      cache.put(request, response.clone()).catch(() => {
        // Quota or storage errors are non-fatal — the fetch still returns.
      });
    }

    return response;
  } catch (error) {
    const cached = await cache.match(request);

    if (cached) {
      return cached;
    }

    // Navigation requests get the cached root as a last-resort shell so
    // the app can still boot into "offline, pull to retry" UI instead of
    // the browser's bare chrome-dino page.
    if (request.mode === 'navigate') {
      const root = await cache.match('/');

      if (root) {
        return root;
      }
    }

    throw error;
  }
}
