const CACHE_NAME = 'captain-fin-20260520-v9';
self.addEventListener('install', event => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
