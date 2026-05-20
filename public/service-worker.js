const CACHE_NAME = 'quick-ledger-predeploy-20260520-v6';
self.addEventListener('install', event => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
