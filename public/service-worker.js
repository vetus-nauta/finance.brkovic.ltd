const CACHE_NAME = 'findesk-20260609-cash-block-close-routes43';
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
