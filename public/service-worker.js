const CACHE_NAME = 'findesk-v2-20260814-iphone-safe-area-c';
self.addEventListener('install', event => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil(
  caches.keys()
    .then(keys => Promise.all(
      keys
        .filter(key => key.startsWith('findesk-') && key !== CACHE_NAME)
        .map(key => caches.delete(key))
    ))
    .then(() => self.clients.claim())
));
