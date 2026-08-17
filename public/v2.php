<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
if ($requestPath === '/v2.php') {
    $query = (string)($_SERVER['QUERY_STRING'] ?? '');
    header('Location: /' . ($query === '' ? '' : '?' . $query), true, 302);
    exit;
}
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
  <meta name="robots" content="noindex,nofollow">
  <meta name="theme-color" content="#f7f8fb">
  <title>FinDesk v2</title>
  <link rel="icon" href="/assets/v2/findesk-mark.svg" type="image/svg+xml">
  <style>
    body.v2-booting [data-v2-workspace],
    body.v2-booting [data-v2-summary-screen],
    body.v2-booting [data-v2-training-screen],
    body.v2-booting [data-v2-quick-notes-screen],
    body.v2-booting [data-v2-create],
    body.v2-booting [data-v2-hall],
    body.v2-booting [data-v2-employee-screen],
    body.v2-booting [data-v2-entry-form],
    body.v2-booting [data-v2-preview-panel],
    body.v2-booting [data-v2-summary],
    body.v2-booting [data-v2-screen],
    body.v2-booting .v2-select-label,
    body.v2-booting [data-v2-workspace-select],
    body.v2-booting [data-v2-month] {
      display: none !important;
    }
  </style>
  <link rel="stylesheet" href="/assets/v2/app.css?v=20260817-admin-debt-tooltip-breakdown">
</head>
<body class="v2-booting v2-auth-mode">
  <main class="v2-shell" data-v2-app>
    <header class="v2-topbar">
      <div class="v2-brand">
        <img class="v2-mark" src="/assets/v2/findesk-mark.svg" alt="" aria-hidden="true">
        <div>
          <strong>FinDesk v2</strong>
          <span data-v2-month>Текущий месяц</span>
          <small class="v2-status-line" data-v2-status role="status">Загрузка</small>
        </div>
      </div>

      <nav class="v2-mode-nav" aria-label="Рабочие экраны">
        <button class="is-active" type="button" data-v2-screen="hall" aria-pressed="true">Холл</button>
        <button type="button" data-v2-screen="operational" aria-pressed="false">Журнал</button>
        <button type="button" data-v2-screen="quick-notes" aria-pressed="false">Заметки</button>
        <button type="button" data-v2-screen="summary" aria-pressed="false">Сводка</button>
        <button type="button" data-v2-screen="training" aria-pressed="false">Обучение</button>
      </nav>

      <button class="v2-mobile-month-button" type="button" data-v2-mobile-month-open hidden>
        <span>Месяц</span>
        <strong data-v2-mobile-month>Текущий месяц</strong>
      </button>

      <section class="v2-summary-strip" data-v2-summary aria-label="Текущие показатели">
        <div class="v2-summary-item v2-summary-cash">
          <span>Наличные</span>
          <strong data-v2-cash-now>—</strong>
        </div>
        <div class="v2-summary-item v2-summary-card">
          <span>Карта</span>
          <strong data-v2-card-total>—</strong>
        </div>
        <div class="v2-summary-item v2-summary-opening">
          <span>Вход месяца</span>
          <strong data-v2-opening-cash>—</strong>
        </div>
        <div class="v2-summary-item v2-summary-review">
          <span>На проверке</span>
          <button class="v2-summary-action" type="button" data-v2-other-review-jump>
            <strong data-v2-other-count>—</strong>
          </button>
        </div>
        <div class="v2-summary-item v2-summary-month-state">
          <span>Месяц</span>
          <button class="v2-summary-action" type="button" data-v2-month-toggle>
            <strong data-v2-month-state>Открыт</strong>
          </button>
        </div>
      </section>

      <div class="v2-workspace-controls">
        <label class="v2-select-label">
          <span>Пространство</span>
          <select data-v2-workspace-select aria-label="Рабочее пространство"></select>
        </label>
        <div class="v2-top-actions">
          <button class="v2-icon-button v2-mobile-finance-toggle" type="button" data-v2-mobile-finance-toggle title="Финансовый обзор" aria-label="Финансовый обзор" hidden>Обзор</button>
          <button class="v2-icon-button" type="button" data-v2-hall-open title="Вернуться в холл" aria-label="Вернуться в холл" hidden>В холл</button>
          <button class="v2-icon-button" type="button" data-v2-refresh title="Обновить" aria-label="Обновить">↻</button>
          <button class="v2-icon-button" type="button" data-v2-logout title="Выйти" aria-label="Выйти" hidden>Выйти</button>
        </div>
      </div>
    </header>

    <section class="v2-auth-state" data-v2-auth hidden>
      <div class="v2-auth-panel">
        <div class="v2-auth-brand-card" aria-label="FinDesk v2">
          <img class="v2-auth-mark" src="/assets/v2/findesk-mark.svg" alt="" aria-hidden="true">
          <div>
            <span>FinDesk v2</span>
            <h1>Рабочий вход в финансы</h1>
            <p>Один код на email открывает ваши пространства, журналы и отчеты.</p>
          </div>
          <dl class="v2-auth-facts" aria-label="Параметры входа">
            <div>
              <dt>Email</dt>
              <dd>код входа</dd>
            </div>
            <div>
              <dt>30 мин</dt>
              <dd>срок кода</dd>
            </div>
            <div>
              <dt>Защищено</dt>
              <dd>без пароля</dd>
            </div>
          </dl>
        </div>

        <form class="v2-auth-card" data-v2-auth-form>
          <div class="v2-auth-card-head">
            <strong>Войти в аккаунт</strong>
            <p>Введите email. Код придет письмом и будет действовать полчаса.</p>
          </div>
          <label>
            <span>Email</span>
            <input type="email" name="email" data-v2-auth-email autocomplete="email" placeholder="name@example.com" required>
          </label>
          <button class="v2-auth-primary" type="button" data-v2-auth-send>Получить код</button>
          <div class="v2-auth-code" data-v2-auth-code-block hidden>
            <label>
              <span>Код из письма</span>
              <input type="text" name="code" data-v2-auth-code inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" placeholder="6 цифр">
            </label>
            <button class="v2-auth-primary" type="submit" data-v2-auth-verify>Войти</button>
          </div>
          <p class="v2-auth-message" data-v2-auth-message role="status">Введите email, чтобы получить код входа.</p>
        </form>
      </div>
    </section>

    <section class="v2-create-state" data-v2-create hidden>
      <div class="v2-create-head">
        <div>
          <h1>Новое пространство</h1>
          <p>Базовые параметры можно изменить позже в настройках.</p>
        </div>
        <button type="button" data-v2-create-back hidden>Назад</button>
      </div>
      <form class="v2-create-form" data-v2-create-form>
        <label>
          <span>Название пространства</span>
          <input name="name" type="text" value="Рабочее пространство FinDesk v2" maxlength="190" required>
        </label>
        <label>
          <span>Входящий остаток наличных</span>
          <input name="opening_cash" type="text" inputmode="decimal" placeholder="1000.00">
        </label>
        <button type="submit">Создать пространство</button>
      </form>
    </section>

    <section class="v2-hall" data-v2-hall hidden>
      <div class="v2-hall-head">
        <div>
          <h1>Холл FinDesk</h1>
          <p>Выберите пространство, где сейчас работаете. Роль действует только внутри выбранного пространства.</p>
        </div>
        <button type="button" data-v2-hall-create-open>Создать пространство</button>
      </div>
      <section class="v2-invite-card" data-v2-invite-panel hidden>
        <div>
          <span data-v2-invite-kicker>Приглашение</span>
          <h2 data-v2-invite-title>Принять приглашение</h2>
          <p data-v2-invite-text>Войдите и подтвердите участие в рабочем пространстве.</p>
        </div>
        <div class="v2-invite-actions">
          <button type="button" data-v2-invite-accept>Принять</button>
          <button type="button" data-v2-invite-dismiss>Скрыть</button>
        </div>
      </section>
      <div class="v2-hall-grid" data-v2-hall-workspace-list></div>
    </section>

    <section class="v2-employee-mode" data-v2-employee-screen hidden>
      <div class="v2-employee-head">
        <div>
          <h1 data-v2-employee-title>Рабочее пространство сотрудника</h1>
          <p data-v2-employee-meta>Ограниченный режим без общей финансовой картины.</p>
        </div>
        <button type="button" data-v2-employee-hall>В холл</button>
      </div>
      <section class="v2-employee-summary" data-v2-employee-summary></section>
      <section class="v2-employee-offers" data-v2-employee-offers></section>
    </section>

    <section class="v2-quick-notes-screen" data-v2-quick-notes-screen hidden>
      <section class="v2-panel v2-quick-notes-list-panel">
        <div class="v2-panel-head">
          <div>
            <h1>Заметки</h1>
            <span data-v2-quick-notes-status>Черновики перед журналом</span>
          </div>
          <button class="v2-summary-refresh" type="button" data-v2-quick-note-new>Новая</button>
        </div>
        <div class="v2-quick-notes-list" data-v2-quick-notes-list></div>
      </section>

      <section class="v2-panel v2-quick-note-editor-panel">
        <div class="v2-quick-note-editor-head">
          <button class="v2-quick-note-back" type="button" data-v2-quick-note-back aria-label="Назад">‹</button>
          <label>
            <span>Дата заметки</span>
            <input type="date" data-v2-quick-note-date>
          </label>
        </div>
        <textarea data-v2-quick-note-text rows="12" placeholder="+1000 поступило от судовладельца&#10;-350 продукты&#10;-100 стоянка в марине"></textarea>
        <div class="v2-quick-note-actions">
          <button type="button" data-v2-quick-note-parse>Поделиться</button>
        </div>
      </section>
    </section>

    <section class="v2-detail-layer" data-v2-quick-note-layer hidden>
      <button class="v2-detail-backdrop" type="button" data-v2-quick-note-modal-close aria-label="Закрыть разбор заметки"></button>
      <section class="v2-panel v2-detail v2-quick-note-dialog" role="dialog" aria-modal="true" aria-labelledby="v2-quick-note-dialog-title">
        <div class="v2-panel-head">
          <div class="v2-detail-heading">
            <span class="v2-detail-kicker">Mr. Smith</span>
            <h2 id="v2-quick-note-dialog-title">Проверка заметки</h2>
            <span>Сначала подтвердите, потом строки попадут в журнал</span>
          </div>
          <button class="v2-detail-close" type="button" data-v2-quick-note-modal-close aria-label="Закрыть разбор заметки">&times;</button>
        </div>
        <div class="v2-quick-note-dialog-body">
          <section class="v2-quick-note-dialog-source">
            <h3>Исходная заметка</h3>
            <pre data-v2-quick-note-modal-raw></pre>
          </section>
          <section class="v2-quick-note-dialog-result">
            <h3>Предложение для журнала</h3>
            <div class="v2-quick-note-preview" data-v2-quick-note-preview>
              <div class="v2-quick-note-empty">Смит подготовит строки для проверки.</div>
            </div>
          </section>
        </div>
        <div class="v2-quick-note-dialog-actions">
          <button type="button" data-v2-quick-note-modal-close>Вернуться к заметке</button>
          <button type="button" data-v2-quick-note-convert disabled>Принять в журнал</button>
        </div>
      </section>
    </section>

    <section class="v2-workspace" data-v2-workspace hidden>
      <aside class="v2-rail" aria-label="Денежный поток">
        <button class="v2-flow is-active" type="button" data-v2-flow="cash">Кеш</button>
        <button class="v2-flow" type="button" data-v2-flow="card">Карта</button>
        <button class="v2-flow v2-all-feed-button" type="button" data-v2-all-feed-toggle aria-pressed="false">Вся лента</button>
        <button class="v2-flow v2-archive-button" type="button" data-v2-archive-open>Архив</button>
        <button class="v2-flow v2-report-view-button" type="button" data-v2-report-archive-toggle aria-pressed="false">Отчеты</button>
        <button class="v2-flow v2-report-rail-button" type="button" data-v2-report-selection-toggle>Новый отчет</button>
        <button class="v2-flow v2-quick-notes-rail-button" type="button" data-v2-quick-notes-open>Заметки</button>
        <button class="v2-flow v2-current-button" type="button" data-v2-current-month hidden>Текущий</button>
      </aside>

      <section class="v2-boards" aria-label="Оперативные записи">
        <div class="v2-mobile-tabs" role="tablist" aria-label="Вид">
          <button class="is-active" type="button" data-v2-view="write">Записи</button>
          <button type="button" data-v2-view="check">Проверка</button>
          <button type="button" data-v2-view="quick-notes">Заметки</button>
        </div>

        <div class="v2-horizontal">
          <section class="v2-panel v2-writing" data-v2-writing>
            <div class="v2-panel-head">
              <h1 data-v2-journal-title>Оперативный журнал</h1>
              <div class="v2-panel-head-actions">
                <span data-v2-count>0 записей</span>
                <button class="v2-small-action" type="button" data-v2-report-selection-toggle>Новый отчет</button>
              </div>
            </div>
            <div class="v2-report-selection-bar" data-v2-report-selection-bar hidden>
              <div class="v2-report-range-controls" data-v2-report-range-controls>
                <span>Лента отчета</span>
                <label>с <input type="date" data-v2-report-range-from></label>
                <label>по <input type="date" data-v2-report-range-to></label>
                <button type="button" data-v2-report-range-apply>Открыть</button>
              </div>
              <div class="v2-report-selection-actions">
                <span data-v2-report-selection-state>Выберите диапазон или несколько строк-отчетов</span>
                <div class="v2-report-selection-buttons">
                  <button type="button" data-v2-report-selection-preview disabled>Сводка</button>
                  <button type="button" data-v2-report-selection-cancel>Отмена</button>
                </div>
              </div>
            </div>
            <div class="v2-report-context-bar" data-v2-report-context hidden>
              <span data-v2-report-context-title>Текущая лента уже включена в отчет</span>
              <button type="button" data-v2-report-context-open>Открыть отчет</button>
            </div>
            <div class="v2-entry is-header v2-field-head v2-journal-field-head" data-v2-journal-header>
              <span>№</span>
              <span>Описание</span>
              <span>Сумма</span>
            </div>
            <div class="v2-feed" data-v2-feed aria-live="polite"></div>
          </section>

          <section class="v2-panel v2-check" data-v2-check>
            <div class="v2-panel-head">
              <h2 data-v2-check-title>Структурная проверка</h2>
              <span data-v2-check-meta>Те же записи</span>
            </div>
            <div class="v2-report-selection-spacer" data-v2-report-selection-spacer hidden aria-hidden="true"></div>
            <div class="v2-check-row is-header v2-field-head v2-check-field-head" data-v2-check-header>
              <span>№</span>
              <span>Дата</span>
              <span>Запись</span>
              <span>Поток</span>
              <span>Знак</span>
              <span>Сумма</span>
              <span>Движение</span>
              <span>Категория</span>
              <span>Учет</span>
              <span>Участник</span>
              <span>Статус</span>
              <span>Остаток</span>
            </div>
            <div class="v2-check-table" data-v2-check-table></div>
          </section>
        </div>
      </section>
    </section>

    <section class="v2-training-screen" data-v2-training-screen hidden>
      <section class="v2-panel v2-training-queue-panel" data-v2-dictionary-review>
        <div class="v2-panel-head">
          <div>
            <h1>Разбор записей</h1>
            <span data-v2-training-status>Спорные строки и словарь</span>
          </div>
          <button class="v2-summary-refresh" type="button" data-v2-training-refresh>Обновить</button>
        </div>
        <div class="v2-training-scroll" data-v2-training-queue></div>
      </section>

      <section class="v2-panel v2-training-decision-panel" data-v2-dictionary-decision-panel>
        <div class="v2-panel-head">
          <div>
            <h2>Что сделать со строкой</h2>
            <span>Выберите категорию или отложите</span>
          </div>
        </div>
        <div class="v2-training-scroll" data-v2-training-detail></div>
      </section>
    </section>

    <section class="v2-layer1-summary" data-v2-summary-screen hidden>
      <div class="v2-summary-tabs" role="tablist" aria-label="Вкладки сводки первого слоя">
        <button class="is-active" type="button" data-v2-summary-tab="information" role="tab" aria-selected="true">Информация</button>
        <button type="button" data-v2-summary-tab="sending" role="tab" aria-selected="false">Отправка</button>
        <button type="button" data-v2-summary-tab="printing" role="tab" aria-selected="false">Печать</button>
        <button type="button" data-v2-summary-tab="storage" role="tab" aria-selected="false">Хранение</button>
      </div>

      <section class="v2-panel v2-summary-panel" data-v2-summary-panel="information">
        <div class="v2-panel-head">
          <div>
            <h1>Сводка периода</h1>
            <span data-v2-layer1-summary-status>Финансовый результат</span>
          </div>
          <button class="v2-summary-refresh" type="button" data-v2-layer1-summary-refresh>Обновить</button>
        </div>
        <div class="v2-summary-scroll" data-v2-layer1-information></div>
      </section>

      <section class="v2-panel v2-summary-panel" data-v2-summary-panel="sending" hidden>
        <div class="v2-panel-head">
          <h2>Отправка</h2>
          <span>Основа данных еще не готова</span>
        </div>
        <div class="v2-summary-scroll">
          <div class="v2-summary-placeholder">Пакет отправки будет собран после фиксации структуры данных.</div>
        </div>
      </section>

      <section class="v2-panel v2-summary-panel" data-v2-summary-panel="printing" hidden>
        <div class="v2-panel-head">
          <h2>Печать</h2>
          <span>Основа данных еще не готова</span>
        </div>
        <div class="v2-summary-scroll">
          <div class="v2-summary-placeholder">Печатная форма будет подключена после фиксации структуры данных.</div>
        </div>
      </section>

      <section class="v2-panel v2-summary-panel" data-v2-summary-panel="storage" hidden>
        <div class="v2-panel-head">
          <div>
            <h2>Хранение</h2>
            <span data-v2-layer1-storage-status>Сохраненные снимки</span>
          </div>
          <div class="v2-summary-actions">
            <button class="v2-summary-refresh" type="button" data-v2-layer1-storage-refresh>Обновить</button>
            <button class="v2-summary-refresh" type="button" data-v2-layer1-storage-save>Сохранить снимок</button>
          </div>
        </div>
        <div class="v2-summary-scroll" data-v2-layer1-storage></div>
      </section>
    </section>

    <section class="v2-detail-layer" data-v2-entry-detail-layer hidden>
      <button class="v2-detail-backdrop" type="button" data-v2-entry-detail-backdrop aria-label="Закрыть детали записи"></button>
      <section class="v2-panel v2-detail" data-v2-entry-detail role="dialog" aria-modal="true" aria-labelledby="v2-detail-title">
        <div class="v2-panel-head">
          <div class="v2-detail-heading">
            <span class="v2-detail-kicker" data-v2-detail-kicker>Детали записи</span>
            <h2 id="v2-detail-title" data-v2-detail-title-text>Детали записи</h2>
            <span data-v2-selected-entry-id>Запись не выбрана</span>
          </div>
          <button class="v2-detail-close" type="button" data-v2-entry-detail-close aria-label="Закрыть детали записи">&times;</button>
        </div>
        <div class="v2-detail-body" data-v2-entry-detail-body>
          <div class="v2-detail-empty">Выберите запись для просмотра.</div>
          <div class="v2-detail-content" data-v2-detail-content hidden>
            <div class="v2-detail-raw" data-v2-detail-raw></div>
            <dl class="v2-detail-grid" data-v2-detail-fields></dl>
            <section class="v2-attachments" data-v2-attachments>
              <div class="v2-attachments-head">
                <h3>Файлы</h3>
                <span data-v2-attachment-status></span>
              </div>
              <div class="v2-attachment-list" data-v2-attachment-list>
                <div class="v2-attachment-empty" data-v2-attachment-empty>Файлов нет</div>
              </div>
              <form class="v2-attachment-form" data-v2-attachment-form>
                <input type="file" data-v2-attachment-input accept="application/pdf,image/png,image/jpeg,image/webp">
                <button type="submit" data-v2-attachment-upload>Прикрепить файл</button>
              </form>
            </section>
            <form class="v2-category-form" data-v2-category-form>
              <label>
                <span>Категория</span>
                <select data-v2-category-select></select>
              </label>
              <button type="submit" data-v2-category-save>Сохранить категорию</button>
            </form>
            <div class="v2-category-error" data-v2-category-error role="alert"></div>
            <div class="v2-closed-decision" data-v2-closed-month-decision hidden>
              <strong>Закрытый месяц</strong>
              <p>Выберите, как применить изменение категории.</p>
              <div class="v2-closed-decision-meta">
                <span>Было <b data-v2-closed-month-decision-from>—</b></span>
                <span>Стало <b data-v2-closed-month-decision-to>—</b></span>
              </div>
              <div class="v2-closed-decision-actions">
                <button type="button" data-v2-closed-month-decision-action="create_correction">Создать корректировку</button>
                <button type="button" data-v2-closed-month-decision-action="recalculate_chain">Пересчитать цепочку</button>
                <button type="button" data-v2-closed-month-decision-action="cancel">Отмена</button>
              </div>
            </div>
          </div>
        </div>
      </section>
    </section>

    <section class="v2-detail-layer" data-v2-source-layer hidden>
      <button class="v2-detail-backdrop" type="button" data-v2-source-backdrop aria-label="Закрыть источники"></button>
      <section class="v2-panel v2-detail v2-source-detail" data-v2-source-detail role="dialog" aria-modal="true" aria-labelledby="v2-source-title">
        <div class="v2-panel-head">
          <div class="v2-detail-heading">
            <span class="v2-detail-kicker">Записи, из которых сложилась сумма</span>
            <h2 id="v2-source-title" data-v2-source-title>Записи-источники</h2>
            <span data-v2-source-meta>Оперативные записи</span>
          </div>
          <button class="v2-detail-close" type="button" data-v2-source-close aria-label="Закрыть источники">&times;</button>
        </div>
        <div class="v2-detail-body" data-v2-source-body></div>
      </section>
    </section>

    <section class="v2-detail-layer" data-v2-report-fragment-layer hidden>
      <button class="v2-detail-backdrop" type="button" data-v2-report-fragment-close aria-label="Закрыть отчетный фрагмент"></button>
      <section class="v2-panel v2-detail v2-report-fragment-detail" role="dialog" aria-modal="true" aria-labelledby="v2-report-fragment-title">
        <div class="v2-panel-head">
          <div class="v2-detail-heading">
            <span class="v2-detail-kicker">Отчет</span>
            <h2 id="v2-report-fragment-title" data-v2-report-fragment-title>Сводка выбранных строк</h2>
            <span data-v2-report-fragment-meta>Выбранные записи</span>
          </div>
          <button class="v2-detail-close" type="button" data-v2-report-fragment-close aria-label="Закрыть отчетный фрагмент">&times;</button>
        </div>
        <div class="v2-report-fragment-actions">
          <span data-v2-report-fragment-status></span>
          <div class="v2-report-fragment-controls" data-v2-report-fragment-controls hidden>
            <a class="v2-report-fragment-button" href="#" target="_blank" rel="noopener" data-v2-report-fragment-html>Открыть HTML</a>
            <a class="v2-report-fragment-button" href="#" data-v2-report-fragment-download>Скачать</a>
            <a class="v2-report-fragment-button" href="#" data-v2-report-fragment-table>Таблица</a>
            <button type="button" data-v2-report-fragment-print>PDF / печать</button>
            <label>
              <span>Дата отчета</span>
              <input type="date" data-v2-report-fragment-close-date>
            </label>
            <button type="button" data-v2-report-fragment-close-date-save>Сохранить дату</button>
            <button type="button" data-v2-report-fragment-send>Готов к отправке</button>
            <button type="button" class="is-warning" data-v2-report-fragment-rebuild>Сохранить изменения отчета</button>
            <button type="button" class="is-warning" data-v2-report-fragment-revision>Вернуть на доработку</button>
            <button type="button" class="is-danger" data-v2-report-fragment-cancel>Отменить отчет</button>
          </div>
          <button type="button" data-v2-report-fragment-create disabled>Создать отчет к отправке</button>
        </div>
        <div class="v2-detail-body v2-report-fragment-body" data-v2-report-fragment-body></div>
      </section>
    </section>

    <section class="v2-detail-layer" data-v2-archive-layer hidden>
      <button class="v2-detail-backdrop" type="button" data-v2-archive-cancel aria-label="Закрыть архив"></button>
      <section class="v2-panel v2-compact-dialog" data-v2-archive-modal role="dialog" aria-modal="true" aria-labelledby="v2-archive-title">
        <div class="v2-panel-head">
          <div class="v2-detail-heading">
            <span class="v2-detail-kicker">Архив</span>
            <h2 id="v2-archive-title">Открыть месяц</h2>
          </div>
          <button class="v2-detail-close" type="button" data-v2-archive-close aria-label="Закрыть архив">&times;</button>
        </div>
        <div class="v2-dialog-body">
          <label>
            <span>Год</span>
            <select data-v2-archive-year></select>
          </label>
          <label>
            <span>Месяц</span>
            <select data-v2-archive-month></select>
          </label>
          <div class="v2-dialog-actions">
            <button type="button" data-v2-archive-load>Открыть</button>
            <button type="button" data-v2-archive-cancel>Отмена</button>
          </div>
        </div>
      </section>
    </section>

    <section class="v2-detail-layer" data-v2-unsaved-guard hidden>
      <button class="v2-detail-backdrop" type="button" data-v2-unsaved-cancel aria-label="Продолжить редактирование"></button>
      <section class="v2-panel v2-compact-dialog" role="dialog" aria-modal="true" aria-labelledby="v2-unsaved-title">
        <div class="v2-panel-head">
          <div class="v2-detail-heading">
            <span class="v2-detail-kicker">Есть несохраненные изменения</span>
            <h2 id="v2-unsaved-title">Уйти из записи?</h2>
          </div>
        </div>
        <div class="v2-dialog-body">
          <p>Сохранить измененную строку перед сменой месяца?</p>
          <div class="v2-dialog-actions">
            <button type="button" data-v2-unsaved-save>Сохранить</button>
            <button type="button" data-v2-unsaved-discard>Не сохранять</button>
            <button type="button" data-v2-unsaved-cancel>Отмена</button>
          </div>
        </div>
      </section>
    </section>

    <section class="v2-detail-layer" data-v2-closed-edit-layer hidden>
      <button class="v2-detail-backdrop" type="button" data-v2-closed-edit-cancel aria-label="Отменить правку закрытого месяца"></button>
      <section class="v2-panel v2-compact-dialog" role="dialog" aria-modal="true" aria-labelledby="v2-closed-edit-title">
        <div class="v2-panel-head">
          <div class="v2-detail-heading">
            <span class="v2-detail-kicker">Закрытый месяц</span>
            <h2 id="v2-closed-edit-title">Подтвердить правку</h2>
          </div>
        </div>
        <div class="v2-dialog-body">
          <p>Этот месяц закрыт. Правка пересчитает последующие остатки.</p>
          <div class="v2-dialog-actions">
            <button type="button" data-v2-closed-edit-confirm>Пересчитать</button>
            <button type="button" data-v2-closed-edit-cancel>Отмена</button>
          </div>
        </div>
      </section>
    </section>

    <form class="v2-inputbar" data-v2-entry-form>
      <label>
        <span>Дата</span>
        <input name="date" type="date" data-v2-date required>
      </label>
      <label class="v2-entry-field">
        <span>Запись</span>
        <input name="raw_text" type="text" data-v2-raw-text autocomplete="off" placeholder="+1000 поступило от судовладельца" required>
      </label>
      <div class="v2-entry-edit-actions" data-v2-entry-edit-actions hidden>
        <button type="button" class="v2-edit-icon" data-v2-entry-edit-save aria-label="Редактировать запись" title="Редактировать запись">Править</button>
        <button type="button" class="v2-edit-icon is-danger" data-v2-entry-delete aria-label="Удалить запись" title="Удалить запись">Удал.</button>
      </div>
      <button type="button" data-v2-preview>Проверить</button>
      <button type="submit" data-v2-submit>Сохранить</button>
    </form>

    <section class="v2-preview" data-v2-preview-panel hidden></section>
  </main>

  <script src="/assets/v2/app.js?v=20260817-report-table-all-income" defer></script>
</body>
</html>
