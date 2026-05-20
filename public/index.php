<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

  <title>Quick Ledger — Personal & Group Finance Tracker</title>
  <meta name="description" content="Quick Ledger is a fast web app for personal finance, group expense tracking, reports, proforma offers and simple company tools.">
  <meta name="keywords" content="expense tracker, group expense tracker, finance tracker, budget tracker, proforma invoice, web app accounting, yacht expenses tracker, crew finance tracking">

  <link rel="canonical" href="https://finance.brkovic.ltd/">
  <link rel="manifest" href="/manifest.webmanifest">

  <meta name="theme-color" content="#f5f7fb">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="Quick Ledger">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <link rel="icon" href="/favicon.ico" sizes="any">
  <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16x16.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
<meta property="og:title" content="Quick Ledger — Personal & Group Finance Tracker">
  <meta property="og:description" content="Fast personal and group finance tracking web app by brkovic.ltd.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://finance.brkovic.ltd/">

  <link rel="stylesheet" href="/assets/app.css?v=20260520-06">
</head>
<body>
  <main class="ql-shell">
    <section class="hero-card glass">
      <div class="brand-pill">brkovic.ltd · web app</div>
      <h1>Quick Ledger</h1>
      <p class="lead">Fast personal and group finance tracking. Built for quick entries on the move.</p>

      <div class="hero-actions">
        <a class="primary-btn" href="/app.php">Open App</a>
        <button class="ghost-btn" type="button" data-open-install="auto">Install Web App</button>
      </div>

      <div class="install-grid">
        <button type="button" data-open-install="ios">iOS Web App</button>
        <button type="button" data-open-install="android">Google / Android</button>
        <button type="button" data-open-install="windows">Windows</button>
      </div>

      <p class="soft-note">
        This is a web app. It works like an app, but does not use your phone storage like a native app.
        Your data will be stored safely in your account and available after login.
      </p>
    </section>

    <section class="seo-card glass">
      <h2>Simple finance tools, ready for groups</h2>
      <p>
        Track cash, non-cash payments, income, expenses, documents, reports, group totals,
        proforma offers and company tools in one clean interface.
      </p>
    </section>

    <footer class="ql-footer">
      <button type="button" class="footer-link" data-open-donate>Donate</button>
      <span>Quick Ledger by brkovic.ltd</span>
    </footer>
  </main>

  <div id="installModal" class="modal hidden" aria-hidden="true">
    <div class="modal-card glass">
      <button class="modal-close" type="button" data-close-modal>×</button>
      <div id="installContent"></div>
    </div>
  </div>

  <div id="donateModal" class="modal hidden" aria-hidden="true">
    <div class="modal-card glass">
      <button class="modal-close" type="button" data-close-modal>×</button>
      <h3>Donate</h3>
      <p>Donation widget slot is ready. Volet / ADV Cash script will be loaded here later only after user action.</p>
      <div id="donate-widget-slot"></div>
    </div>
  </div>

  <script src="/assets/i18n.js?v=20260503-62"></script>
  <script src="/assets/donate.js?v=20260503-01"></script>
  <script src="/assets/notifications.js?v=20260503-01"></script>
  <script src="/assets/app.js?v=20260520-06"></script>
</body>
</html>
