<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex,nofollow">
  <meta name="theme-color" content="#f7f8fb">
  <title>FinDesk v2</title>
  <link rel="stylesheet" href="/assets/v2/app.css?v=20260705-sprint04r-1">
</head>
<body>
  <main class="v2-shell" data-v2-app>
    <header class="v2-topbar">
      <div class="v2-brand">
        <span class="v2-mark" aria-hidden="true">F</span>
        <div>
          <strong>FinDesk v2</strong>
          <span data-v2-month>Current month</span>
        </div>
      </div>

      <div class="v2-workspace-controls">
        <label class="v2-select-label">
          <span>Workspace</span>
          <select data-v2-workspace-select aria-label="Workspace"></select>
        </label>
        <button class="v2-icon-button" type="button" data-v2-refresh title="Refresh" aria-label="Refresh">↻</button>
      </div>
    </header>

    <section class="v2-status-line" data-v2-status role="status">Loading</section>

    <section class="v2-auth-state" data-v2-auth hidden>
      <h1>Sign in required</h1>
      <p>Open FinDesk from an authenticated session to use the v2 operational journal.</p>
    </section>

    <section class="v2-create-state" data-v2-create hidden>
      <form class="v2-create-form" data-v2-create-form>
        <label>
          <span>Workspace name</span>
          <input name="name" type="text" value="FinDesk v2 Workspace" maxlength="190" required>
        </label>
        <label>
          <span>Opening cash</span>
          <input name="opening_cash" type="text" inputmode="decimal" placeholder="1000.00">
        </label>
        <button type="submit">Create workspace</button>
      </form>
    </section>

    <section class="v2-summary-strip" data-v2-summary aria-label="Current figures">
      <div>
        <span>Cash now</span>
        <strong data-v2-cash-now>—</strong>
      </div>
      <div>
        <span>Card spent</span>
        <strong data-v2-card-total>—</strong>
      </div>
      <div>
        <span>Opening cash</span>
        <strong data-v2-opening-cash>—</strong>
      </div>
      <div>
        <span>Other review</span>
        <button class="v2-summary-action" type="button" data-v2-other-review-jump>
          <strong data-v2-other-count>—</strong>
        </button>
      </div>
    </section>

    <section class="v2-workspace" data-v2-workspace hidden>
      <aside class="v2-rail" aria-label="Flow">
        <button class="v2-flow is-active" type="button" data-v2-flow="cash">Cash</button>
        <button class="v2-flow" type="button" data-v2-flow="card">Card</button>
      </aside>

      <section class="v2-boards" aria-label="Operational records">
        <div class="v2-mobile-tabs" role="tablist" aria-label="View">
          <button class="is-active" type="button" data-v2-view="write">Write</button>
          <button type="button" data-v2-view="detail">Details</button>
          <button type="button" data-v2-view="check">Check</button>
        </div>

        <div class="v2-horizontal">
          <section class="v2-panel v2-writing" data-v2-writing>
            <div class="v2-panel-head">
              <h1>Operational journal</h1>
              <span data-v2-count>0 records</span>
            </div>
            <div class="v2-feed" data-v2-feed aria-live="polite"></div>
          </section>

          <section class="v2-panel v2-detail" data-v2-entry-detail>
            <div class="v2-panel-head">
              <h2>Entry details</h2>
              <span data-v2-selected-entry-id>None selected</span>
            </div>
            <div class="v2-detail-body" data-v2-entry-detail-body>
              <div class="v2-detail-empty">Select a record to review.</div>
              <div class="v2-detail-content" data-v2-detail-content hidden>
                <div class="v2-detail-raw" data-v2-detail-raw></div>
                <dl class="v2-detail-grid" data-v2-detail-fields></dl>
                <section class="v2-attachments" data-v2-attachments>
                  <div class="v2-attachments-head">
                    <h3>Attachments</h3>
                    <span data-v2-attachment-status></span>
                  </div>
                  <div class="v2-attachment-list" data-v2-attachment-list>
                    <div class="v2-attachment-empty" data-v2-attachment-empty>No attachments</div>
                  </div>
                  <form class="v2-attachment-form" data-v2-attachment-form>
                    <input type="file" data-v2-attachment-input accept="application/pdf,image/png,image/jpeg,image/webp">
                    <button type="submit" data-v2-attachment-upload>Attach file</button>
                  </form>
                </section>
                <form class="v2-category-form" data-v2-category-form>
                  <label>
                    <span>Category</span>
                    <select data-v2-category-select></select>
                  </label>
                  <button type="submit" data-v2-category-save>Save category</button>
                </form>
                <div class="v2-category-error" data-v2-category-error role="alert"></div>
                <div class="v2-closed-decision" data-v2-closed-month-decision hidden>
                  <strong>Closed month</strong>
                  <p>Choose how to apply this category change.</p>
                  <div class="v2-closed-decision-meta">
                    <span>From <b data-v2-closed-month-decision-from>—</b></span>
                    <span>To <b data-v2-closed-month-decision-to>—</b></span>
                  </div>
                  <div class="v2-closed-decision-actions">
                    <button type="button" data-v2-closed-month-decision-action="create_correction">Create correction</button>
                    <button type="button" data-v2-closed-month-decision-action="recalculate_chain">Recalculate chain</button>
                    <button type="button" data-v2-closed-month-decision-action="cancel">Cancel</button>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section class="v2-panel v2-check" data-v2-check>
            <div class="v2-panel-head">
              <h2>Structured check</h2>
              <span>Same records</span>
            </div>
            <div class="v2-check-table" data-v2-check-table></div>
          </section>
        </div>
      </section>
    </section>

    <form class="v2-inputbar" data-v2-entry-form>
      <label>
        <span>Date</span>
        <input name="date" type="date" data-v2-date required>
      </label>
      <label class="v2-entry-field">
        <span>Record</span>
        <input name="raw_text" type="text" data-v2-raw-text autocomplete="off" placeholder="+1000 снял с карты" required>
      </label>
      <button type="button" data-v2-preview>Check</button>
      <button type="submit" data-v2-submit>Save</button>
    </form>

    <section class="v2-preview" data-v2-preview-panel hidden></section>
  </main>

  <script src="/assets/v2/app.js?v=20260705-sprint04r-1" defer></script>
</body>
</html>
