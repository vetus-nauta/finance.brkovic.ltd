<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FinDesk reset</title>
  <style>
    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: #f6f8fc;
      color: #101828;
    }
    main {
      width: min(520px, calc(100vw - 32px));
      padding: 28px;
      border-radius: 28px;
      background: rgba(255,255,255,.86);
      box-shadow: 0 24px 70px rgba(32,42,58,.14);
    }
    h1 { margin: 0 0 10px; font-size: 28px; }
    p { color: #667085; line-height: 1.45; }
    a, button {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 48px;
      padding: 0 18px;
      border: 0;
      border-radius: 16px;
      background: #0a84ff;
      color: #fff;
      font-weight: 800;
      text-decoration: none;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <main>
    <h1>Очищаю локальный кеш FinDesk</h1>
    <p>Сейчас удалю старый service worker и открою приложение заново.</p>
    <button id="resetBtn" type="button">Открыть FinDesk</button>
  </main>
  <script>
    async function resetAndOpen() {
      try {
        if ('serviceWorker' in navigator) {
          const registrations = await navigator.serviceWorker.getRegistrations();
          await Promise.all(registrations.map(registration => registration.unregister()));
        }
        if (window.caches && caches.keys) {
          const keys = await caches.keys();
          await Promise.all(keys.filter(key => key.indexOf('findesk-') === 0).map(key => caches.delete(key)));
        }
      } catch (error) {}

      try {
        localStorage.removeItem('findesk-language-prompt-closed');
      } catch (error) {}

      window.location.replace('/?fresh=' + Date.now());
    }

    document.getElementById('resetBtn').addEventListener('click', resetAndOpen);
    resetAndOpen();
  </script>
</body>
</html>
