# RentFlow v2 – plán přepisu na Blade + Livewire

Cíl: místo React SPA (`frontend/`) bude jedna Laravel aplikace na Blade + Livewire 4 + Alpine + Tailwind 4
(stack a vzory jako JaTime). Dostane nový moderní vzhled, české UI, zpevněný backend a zabezpečení.
Běží na `http://rentflow.test` přes Herd.

**Zásada:** backend se zpevňuje dřív než UI, dokud ho hlídá 117 API testů. Pak se stránky přepisují jedna po druhé.
Jeden krok nebo stránka = jedna session Claude Code = jeden PR do větve `v2`.

Stav: `- [ ]` čeká, `- [x]` hotovo. Poznámky piš pod krok.

---

## Fáze 0 – příprava

- [x] Aktualizace závislostí: Laravel 13, PHPUnit 12, Vite 8, ESLint 10 (větev `chore/upgrade-deps`)
- [ ] CI: `php-version: '8.5'`, `node-version: '24'` v `.github/workflows/tests.yml`
- [ ] Merge `chore/upgrade-deps` → `main`, založit větev `v2`
- [x] `CLAUDE.md`, `.claude/` (settings, skills) a tento plán

## Fáze 1 – audit (jen čtení, nic neměnit)

- [ ] `docs/REVIEW.md`: bezpečnost, autorizace, N+1, validace, uploady, mrtvý kód, chybějící testy
- [ ] `docs/FEATURES.md`: inventář funkcí **každé** React stránky (tlačítka, filtry, řazení, stránkování, modaly,
      prázdné stavy, toasty, polling, rozdíly podle role). Podle něj se ve fázi 5 kontroluje, že nic nechybí.

**Podezření z prvního průchodu (ověřit, nebrat jako hotový fakt):**

- `GET /api/v1/users` vrací **každému** přihlášenému (i nájemníkovi) všechny uživatele včetně e-mailu, telefonu
  a trust score. README přitom říká, že nájemník People nevidí.
- `GET /api/v1/users/{user}/ratings` je bez autorizace, takže kdokoli vidí hodnocení kohokoli.
- Zápis do expenses, inventory, documents a ratings má `role:landlord,manager`, ale matice v README dává správci ❌.
  Ověř kontroly v controllerech.
- Dokumenty se ukládají na disk `public` (`DocumentController::store`), takže jdou stáhnout přes `/storage/…`
  bez přihlášení, včetně `visibility = landlord_only`.
- `PropertyPolicy::restore()` vrací `false`, ale `PropertyController::restore` autorizuje přes `update`.
- `Filterable`: `date_to` přes `<=` s datem bez času vynechá celý poslední den.
- `AppServiceProvider` je prázdný: chybí `shouldBeStrict`, rate limiter přihlášení a `Carbon::setLocale('cs')`.
  Časová zóna je `UTC`.
- Role jako string bez enumu, `RoleMiddleware` vrací JSON 403 i mimo API.

```text
Fáze 1 z docs/REWRITE_PLAN.md – audit. Nic neměň v kódu.
1) Projdi celý backend (app/, routes/, database/, config/, tests/) a ulož nálezy do docs/REVIEW.md:
   tabulka # | závažnost (critical/high/medium/low) | oblast | soubor:řádek | problém | návrh opravy.
   Ověř i „Podezření z prvního průchodu“ v plánu. Matice oprávnění v README je specifikace.
2) Projdi frontend/src (pages, components, api, contexts) a vytvoř docs/FEATURES.md: pro každou stránku
   seznam funkcí jako checklist (- [ ]), u každé funkce role, které ji vidí, a API volání, které používá.
   Na konci tabulka React route → navržená česká URL v Livewire.
Na konci shrnutí: počty nálezů podle závažnosti a 5 nejdůležitějších.
```

## Fáze 2 – backend (API a testy musí zůstat zelené)

Postup pro každou oblast: skill `rentflow-backend`. Tvar JSON odpovědí API se nemění (React ho ještě používá).

- [ ] Nástroje: `larastan/larastan` (level 5 → postupně 7), `laravel-lang/common` (dev), skripty `composer analyse`
      a `composer check` jako v JaTime, `pint.json`
- [ ] `AppServiceProvider`: `Model::shouldBeStrict()` mimo produkci, `Carbon::setLocale('cs')`, rate limiter
      přihlášení. `config/app.php`: `timezone` `Europe/Prague`, `APP_LOCALE=cs`, `lang/cs`
- [ ] `App\Enums\Role` (+ cast na `User`), enumy pro statusy (property, lease, payment, ticket) a typy
- [ ] Scope `visibleTo(User $user)` na Property, Lease, Payment, Ticket, Document… a jejich použití v API `index`
- [ ] Policies pro všechny modely a oprava rozporů s maticí v README. Testy na každou roli.
- [ ] Opravy critical/high z `docs/REVIEW.md` (dokumenty na privátní disk + autorizovaný download, `/users`, ratings…)
- [ ] Actions pro zápisové operace (vytvořit/upravit/smazat nemovitost, smlouva + PDF, označit platbu, generovat
      platby, CSV import, tiket + komentáře, měřiče a odečty, správci…). Controllery jen volají Action.
- [ ] Indexy a FK podle nálezů, migrace jen přidávající
- [ ] `composer check` zelený, Larastan bez baseline (nebo s malou a zdůvodněnou)

```text
Fáze 2 z docs/REWRITE_PLAN.md, krok „<název kroku>“. Postupuj podle skillu rentflow-backend.
Tvar JSON odpovědí API neměň. Nejdřív plán (soubory, testy), pak implementace, kontroly (rentflow-checks).
Na konci odškrtni krok v plánu.
```

## Fáze 3 – Livewire, design systém, layout

- [ ] `livewire/livewire` ^4 (stejná verze jako JaTime), Alpine přes Livewire, `@tailwindcss/forms`, fonty přes
      `@fontsource-variable/*` (bez CDN)
- [ ] Návrh vzhledu: 2–3 klíčové obrazovky (přehled, seznam a detail nemovitosti) jako mockup ke schválení
- [ ] `resources/css/app.css` s `@theme` tokeny (barvy, typografie, radius, stíny) pro RentFlow
- [ ] Blade komponenty převzaté z JaTime a upravené: `button`, `card`, `input`, `select`, `textarea`, `field`,
      `checkbox`, `toggle`, `modal`, `confirm-dialog`, `toast`, `empty-state`, `page-header`, `stat`, `pill`,
      `status-badge`, `filter-bar`, `filter-select`, `filter-tabs`, `icon`, `avatar`, `money`, `progress`,
      `error-page`, `logo`
- [ ] `app/Support/helpers.php` (`cz_plural`, `cz_z`, `cz_money`, `cz_date`, `search_key`, `search_matches`)
- [ ] Layout `layouts/app.blade.php` (sidebar podle role, mobilní menu, uživatelské menu, zvoneček notifikací,
      `<meta name="csrf-token">`), chybové stránky 403/404/419/429/500/503
- [ ] `SecurityHeaders` middleware (CSP bez `script-src`/`style-src` jako JaTime), `trustProxies`

```text
Fáze 3 z docs/REWRITE_PLAN.md, krok „<název kroku>“. Vzor je JaTime v C:\dev\jatime (resources/views/components,
resources/css/app.css, layouts/app.blade.php, app/Support/helpers.php). Převezmi strukturu a chování komponent,
vzhled uprav pro RentFlow podle schváleného návrhu. Nepřidávej závislosti mimo plán.
```

## Fáze 4 – autentizace (session)

- [ ] Přihlášení, registrace, odhlášení, zapomenuté heslo (vzor `LoginController` z JaTime, rate limiter `login`)
- [ ] Middleware `role:` pro web (redirect/403 stránka místo JSON), `$middleware->authenticateSessions()`
- [ ] Profil a změna hesla (`/nastaveni`), změna hesla odhlásí ostatní zařízení
- [ ] Sanctum a API nechat beze změny (React běží dál)

## Fáze 5 – stránky (každá = PR, skill `rentflow-page`)

Pořadí podle závislostí. URL česky. Detail nemovitosti rozdělit na záložky jako samostatné komponenty.

- [ ] Přehled `/` (dashboard podle role, grafy: ApexCharts nebo Chart.js – rozhodnout u prvního grafu)
- [ ] Nemovitosti `/nemovitosti` (seznam, filtry, vytvoření/úprava)
- [ ] Detail nemovitosti `/nemovitosti/{property}` – přehled a fotky
- [ ] Detail nemovitosti – měřiče a odečty
- [ ] Detail nemovitosti – inventář
- [ ] Detail nemovitosti – náklady
- [ ] Detail nemovitosti – dokumenty
- [ ] Detail nemovitosti – oznámení
- [ ] Detail nemovitosti – správci
- [ ] Smlouvy `/smlouvy` + detail `/smlouvy/{lease}` (PDF, hodnocení nájemníka)
- [ ] Platby `/platby` (označit zaplaceno, generování, CSV import)
- [ ] Tikety `/tikety` + detail `/tikety/{ticket}` (komentáře, fotky, stavy)
- [ ] Lidé `/lide` + detail `/lide/{user}` (Trust Score, hodnocení, povýšení na správce)
- [ ] Dokumenty `/dokumenty`
- [ ] Notifikace (zvoneček, označit přečtené, `wire:poll`)
- [ ] Nastavení `/nastaveni`

```text
Fáze 5 z docs/REWRITE_PLAN.md, stránka „<název>“. Postupuj podle skillu rentflow-page.
Předloha: frontend/src/pages/<…>.tsx a sekce stránky v docs/FEATURES.md. Nic z inventáře nesmí chybět;
vědomé vynechání napiš. Nejdřív plán, pak implementace, testy, rentflow-checks a rentflow-browser.
```

## Fáze 6 – úklid

- [ ] Smazat `frontend/`, proxy, CORS konfiguraci pro SPA a `unlighthouse.config.ts` přepsat na nové URL
- [ ] Rozhodnout API: ponechat (veřejné API s tokeny a testy), nebo odstranit i se Sanctum
- [ ] CI: odstranit frontend job, přidat `npm run build` v kořeni, `composer analyse`, audity
- [ ] README (architektura, instalace přes Herd, screenshoty), `CHANGELOG.md`

## Fáze 7 – kvalita a nasazení

- [ ] Playwright E2E na hlavní toky (přihlášení všech rolí, nemovitost, smlouva, platba, tiket s komentářem)
- [ ] Unlighthouse (výkon, přístupnost) na hlavních stránkách
- [ ] Rozhodnout produkci (Coolify na Hetzneru jako JaTime? SQLite vs MariaDB), Dockerfile, zálohy
- [ ] Skill `rentflow-release` podle `jatime-release`
