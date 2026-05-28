# Language Policy And Audit

Date: 2026-05-27

Owner: Project Director

Status: policy fixed; audit is P1.

## Policy

Default interface language is English.

If the user's system/browser language is not in the supported list, FinDesk must use English.

Russian is a supported language, not the global fallback.

## Supported Languages

- `ru` - Русский
- `en` - English
- `de` - Deutsch
- `it` - Italiano
- `es` - Espanol
- `sr` - Srpski / MNE / HR
- `zh` - Chinese Mandarin, rendered as `zh-Hans`

## Current Code Position

Language selection is defined in:

- `public/app.php`
- `public/assets/i18n.js`

Current `normalizeLanguage()` fallback is `en`.

Current selection persistence:

- primary key: `finDeskLanguage`
- legacy key: `captainFinLanguage`
- closed prompt key: `finDeskLanguagePromptClosed`

Current PWA behavior:

- installed PWA starts at `/app.php`;
- language persists through `localStorage`;
- service worker registers on non-localhost production and clears old `findesk-*` caches;
- service worker does not provide a full offline translation cache.

## Audit Required

Owner: Frontend/UX, then QA Release Engineer.

Scope:

- confirm every supported language appears in both top language strip and Settings;
- confirm unsupported browser/system language falls back to English;
- confirm saved language survives refresh, logout/login, and PWA standalone reopen;
- confirm old `captainFinLanguage` still migrates/read-falls back correctly;
- confirm closing the language strip does not prevent changing language in Settings;
- inventory hardcoded Russian/English strings in `public/app.php` and `public/assets/app.js`;
- check mobile layout at `390x844` for German, Italian, Spanish, Serbian, and Chinese;
- check `document.documentElement.lang`, page title, and Apple PWA title after language change;
- check service-worker/cache behavior after language change and deploy cache version change.

## Acceptance

P1 audit passes if:

- fallback is English for unsupported system languages;
- all seven language choices are reachable;
- selected language persists in PWA;
- no critical mobile overflow blocks primary MVP actions;
- hardcoded-string inventory is recorded for later localization work.

This audit is not a production deploy blocker for the already approved business-MVP product gate unless QA finds a language/PWA issue that blocks login, navigation, or money capture.
