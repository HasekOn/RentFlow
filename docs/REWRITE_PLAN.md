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
- [x] CI: `php-version: '8.5'`, `node-version: '24'` v `.github/workflows/tests.yml`
  - Akce zvednuté na `actions/checkout@v7`, `actions/cache@v6`, `actions/setup-node@v7`; `composer.json` `php: ^8.5`.
  - Workflow je **dočasně vypnutý** (jen `workflow_dispatch`). Zapnout spouštěče push/PR (v komentáři
    v souboru) nejpozději ve fázi 6 spolu s úpravou jobů (bez React jobu, `npm run build`, `composer analyse`).
- [x] Merge `chore/upgrade-deps` → `main`, založit větev `v2`
  - Merge hotový (PR #1), větev `v2` existuje (i na `origin`).
- [x] `CLAUDE.md`, `.claude/` (settings, skills) a tento plán

## Fáze 1 – audit (jen čtení, nic neměnit)

- [x] `docs/REVIEW.md`: bezpečnost, autorizace, N+1, validace, uploady, mrtvý kód, chybějící testy
  - 58 nálezů (4 critical, 13 high, 21 medium, 20 low; #58 doplněn při plánování fáze 2). Všechna podezření níže
    ověřena (tabulka v REVIEW). Otázky z REVIEW jsou rozhodnuté ve fázi 2 („Rozhodnutí z auditu“).
- [x] `docs/FEATURES.md`: inventář funkcí **každé** React stránky (tlačítka, filtry, řazení, stránkování, modaly,
      prázdné stavy, toasty, polling, rozdíly podle role). Podle něj se ve fázi 5 kontroluje, že nic nechybí.
  - Obsahuje i API, které React nevolá, a návrh URL včetně záložek detailu nemovitosti jako samostatných URL.

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

Postup pro každý krok: skill `rentflow-backend`. Každý krok je jeden PR do `v2` (větev `v2-<krok>`).

React už v `v2` není (smazaný v 2.1), tvar JSON se kvůli němu držet nemusí. API a jeho testy jsou ale záchranná síť
do konce fáze 5: změnu tvaru odpovědi nebo asserce (typicky oprava autorizace, kdy role dostane méně dat nebo 403)
napiš do PR i s testem. O API se rozhodne ve fázi 6.

Čísla `#` jsou nálezy z `docs/REVIEW.md`. U kroku jsou uvedené nálezy, které krok opravuje, a každý z nich musí
pokrýt test.

### Rozhodnutí z auditu (7. 10. 2026, otázky z REVIEW)

- **Správce:**
  - Oprávnění správce se odvozují z přiřazení k nemovitosti (`property_manager`), ne z globální role.
    Policies a scopes se ptají, jestli je uživatel přiřazený k této nemovitosti, a nepíšou `role === 'manager'`.
  - Promote = přiřazení uživatele k nemovitosti pronajímatele.
  - Demote odebere jen přiřazení k nemovitostem toho pronajímatele.
  - `users.role = manager` se přepočítává podle toho, jestli má uživatel aspoň jedno přiřazení (kvůli
    kompatibilitě API; rozhodnuto ještě s Reactem).

  (#14, #24)
- **Nájemník do smlouvy:** existující účet se vyhledá podle přesného e-mailu. Seznam všech uživatelů pronajímatel
  nedostane. Pozvánka e-mailem přijde později, mimo fázi 2. (#4)
- **Smlouvy:** na jedné nemovitosti nesmí být překrývající se aktivní smlouvy. Kontroluje se při vytvoření, úpravě
  dat a reaktivaci. (#22, #53)
- **Tikety:** autor smí ve stavu `new` upravit název a popis. Ostatní pole upravuje jen pronajímatel a přiřazený
  správce. (#46)
- **Nemovitost:** `properties.size` a `disposition` jsou volitelné, takže migrace na `nullable`. Je to výjimka
  z pravidla „migrace jen přidávající“: `->change()` bez ztráty dat. (#29)
- **URL:** přihlášení a odhlášení zůstávají na `/login` a `/logout` (Laravel standard, skills s tím počítají).
  Ostatní URL jsou česky (`/registrace`, `/zapomenute-heslo`, `/nemovitosti` …). Mapování je v `docs/FEATURES.md`.

### Kroky

- [x] **2.1 Nástroje a základ** (#26, #57)
  - `larastan/larastan` (level 5, postupně 7), `laravel-lang/common` (dev), skripty `composer analyse`
    a `composer check` jako v JaTime, `pint.json`.
  - `AppServiceProvider`: `Model::shouldBeStrict()` mimo produkci, `Carbon::setLocale('cs')`.
  - `config/app.php`: `timezone` `Europe/Prague`, `APP_LOCALE=cs`, `lang/cs`.
  - Rate limiter přihlášení přijde s webovým loginem ve fázi 4 (#25).
  - **Smazání `frontend/`** (přesunuto z fáze 6). React zůstává jako předloha na větvi `main` (spuštění přes
    `git worktree`, recept ve skillu `rentflow-page`) a v `docs/FEATURES.md`. Úprava `CLAUDE.md` (přechodné období
    do konce fáze 5), CORS pro SPA (#57), React job v CI, `unlighthouse.config.ts`, README a skills bez `localhost:5173`.
    Kořenový `package.json` a `vite.config.js` zůstávají pro Blade.
  - **Údržba závislostí** jako u JaTime: `composer outdated --direct`, `npm outdated`, `composer audit`,
    `npm audit --omit=dev`. Patch a minor aktualizovat, majory jen navrhnout.
  - Poznámky (18. 11. 2026):
    - Larastan level 5 bez baseline: 9 chyb opraveno (generika relací `Meter::readings`, `Ticket::images`,
      `@property` u `TicketImage`, logout v `AuthController` maže token z hlavičky + nový test).
    - `$attributes` s DB defaulty u 12 modelů. Se strict, `Europe/Prague` a fakerem `cs_CZ` prošlo všech 117 testů
      beze změny asercí.
    - `pint.json` vynechává `lang/` (generuje `lang:add` / `lang:update`). `composer.json`
      `audit.abandoned: report` jako JaTime: `dragon-code/*` z laravel-lang jsou opuštěné, jen se hlásí.
    - `composer update`: framework 13.43 (spodní hranice `^13.43`), Symfony 8.1.11, Sanctum 4.5, všech
      9 advisories opraveno. npm: Vite 8.3.8, Tailwind 4.3.5, laravel-vite-plugin 3.2.1.
    - Plný `npm audit` (s dev) hlásí ReDoS v `picomatch` přes Vite. Oprava zatím není, produkční závislosti jsou čisté.
    - **Majory (návrh, neprovedeno):** PHPUnit 12 → 13 (JaTime je na 13.4) jako samostatný malý krok.
    - **Návrhy na později:** odebrat `laravel/sail` (Herd), `axios` a `resources/js/bootstrap.js` ve fázi 3,
      `composer dev` (`artisan serve` + `vite`) je v rozporu s během jen přes Herd.
    - Větev `v2/<krok>` git nedovolí vedle `v2`, proto `v2-<krok>` (opraveno v `CLAUDE.md`).
    - **Skills podle JaTime (skupina „Hned“):**
      - `rentflow-checks` běží jako subagent (Haiku) a nic neopravuje,
      - `rentflow-review` je subagent spouštěný uživatelem, se stavem větve, checklistem „Čistota kódu“
        a migracemi/rollbackem,
      - nový `rentflow-step` (jeden krok plánu od stavu repa po shrnutí),
      - `db-snapshot.php` pracuje s aktivní DB z konfigurace, má `save-default` / `load-default`
        (`database/default.sqlite`) a při existující záloze skončí kódem 2.
    - Ostatní skills z JaTime jsou rozplánované do fází: po 2.2 `CodeHygieneTest`, po fázi 2 `rentflow-security`,
      ve fázi 3 `cdp.mjs`, `rentflow-design` a `ThemeTest`, ve fázích 5–7 dlouhé texty a `rentflow-release`.
      `jatime-module` se nepřebírá (RentFlow nemá vypínatelné moduly).
- [x] **2.2 Enumy a role** (#21, #40, #58)
  - `App\Enums\Role` (+ cast na `User`), enumy pro statusy (property, lease, payment, ticket) a typy.
  - `User::$fillable` bez `role` a `trust_score`. Nastavují se jen explicitně v registraci, u správců
    a v `TrustScoreService`.
  - Registrace jen `landlord` nebo `tenant`.
  - *Hotovo 18.&nbsp;11.&nbsp;2026:*
    - 14 enumů v `app/Enums` (i `ExpenseType`, `InventoryCondition`, `MeterType`, `RatingCategory`,
      `PropertyImageType`, `DocumentVisibility` místo `Document::VISIBILITY_OPTIONS`) a `Rule::enum()` ve validaci.
    - Vědomě bez `label()`, tónu a ikon. Přijdou s UI ve fázi 3/5.
    - `RoleMiddleware` vrací jen `Access denied.` (#40). `User::isLandlord()` / `isManager()` / `isTenant()`.
    - Cast odhalil tiše rozbitá porovnání (stahování dokumentů nájemníkem a správcem, stav smlouvy při ukončení,
      seeder). Opravena a pokrytá testy.
    - Nově 422 při neplatné `visibility` u uploadu dokumentu (dřív tichý fallback na `landlord_only`).
    - Seeder má data dál jako stringy (cast je převede). `findOrFail` zůstává mimo dotčené řádky, řeší 2.5+.
    - Po review: testy scheduleru (`SchedulerCommandsTest`), hodnocení aktivní smlouvy, smlouvy na nemovitost
      v rekonstrukci a stahování dokumentů správcem.
- [x] **Po 2.2: dvojitá registrace listenerů** (nález z review 2.2, existovalo už předtím, v `docs/REVIEW.md` není)
  - Každý listener je registrovaný dvakrát: v `$listen` v `app/Providers/EventServiceProvider.php` i přes
    auto-discovery (`php artisan event:list` ukazuje `Listener` i `Listener@handle`).
  - Dopad: nový tiket pošle pronajímateli 2 notifikace a 2 e-maily, `PaymentMarkedPaid` dvakrát přepočítá Trust Score
    a dvakrát notifikuje.
  - Oprava: smazat `EventServiceProvider` (discovery stačí), nebo vypnout discovery. Test, že jedna událost = jedna
    notifikace (`Notification::fake()` + `assertSentToTimes(…, 1)`).
  - *Hotovo 18.&nbsp;11.&nbsp;2026:*
    - `EventServiceProvider` smazán a `withProviders` odebráno z `bootstrap/app.php`. Zůstává výchozí auto-discovery
      Laravelu 13, stejně jako JaTime.
    - `User::recalculateTrustScore()` bere službu z kontejneru, aby šel počet přepočtů ověřit testem.
    - `EventListenersTest`: registrace každého listeneru právě jednou a 1 notifikace u `TicketCreated`,
      `TicketResolved` a `LeaseCreated`. U `PaymentMarkedPaid` 1 přepočet. Notifikaci neposílá, protože
      `SendPaymentMarkedNotification` je zatím prázdný placeholder (výše uvedené „dvakrát notifikuje“ neplatilo).
    - Testy byly před opravou červené (2×), po ní zelené.
- [ ] **Po 2.2: `CodeHygieneTest`** (vzor `tests/Feature/CodeHygieneTest.php` z JaTime)
  - Hlídá: ladicí výpisy (`dd`, `dump`, `ray`, `var_dump`), `TODO`/`FIXME`, holé stringy rolí a statusů
    v `app/` (`'landlord'`, `'paid'` … místo enumů z 2.2) a nepoužité Blade komponenty a partials (od fáze 3).
  - Výjimky jen vědomě, s komentářem v testu. Checklist „Čistota kódu“ v `rentflow-review` odkáže na test.
- [ ] **2.3 Soubory** (#1, #2, #3, #15, #43, #55)
  - Dokumenty, PDF smluv a fotky tiketů na privátní disk, existující soubory přesunout (příkaz nebo migrace).
  - Whitelist typů (`mimes` + `max`, žádné SVG ani HTML).
  - `DocumentPolicy` + `Document::visibleTo` (role, vlastnictví nebo přiřazení, `visibility`).
  - Stažení jen autorizovanou routou s `Content-Disposition: attachment` a `nosniff`.
  - PDF smlouvy streamem nebo z privátního disku, `visibility` přes `Rule::in`.
  - Cesty (`invoice_path`, `photo_proof`) nebrat od klienta.
- [ ] **2.4 Uživatelé** (#4, #5, #19)
  - `UserPolicy` + `User::visibleTo`:
    - pronajímatel: nájemníci jeho smluv a správci jeho nemovitostí,
    - správce: nájemníci spravovaných nemovitostí,
    - nájemník: jen sám sebe.
  - Vyhledání účtu podle přesného e-mailu pro novou smlouvu.
  - `users/{user}/ratings` do controlleru s policy, trust score jen přes `UserPolicy::view`.
  - **Vědomě:** smlouvu s novým nájemníkem (účtem, který pronajímatel ještě nezná) půjde založit až v Livewire
    stránce Smlouvy (hledání podle e-mailu). React se po 2.1 už nespouští, takže se to netýká.

**Kroky 2.5–2.10 jsou policies a `visibleTo()` po oblastech.** Každá akce má testy pro všechny role, cizí data
(jiný pronajímatel), nepřiřazeného správce a nájemníka bez aktivní smlouvy (#38).

- [ ] **2.5 Nemovitosti a fotky** (#6, #18, #20, #35, #39)
  - `PropertyPolicy` (včetně `restore`), `Property::visibleTo` (správce přes přiřazení), `PropertyImagePolicy`.
  - Společná kontrola `view` nemovitosti pro čtecí endpointy pod ní (#18, oblasti dál v 2.6–2.10).
  - Resource podle role: nájemník jen svou smlouvu, `purchase_price` jen vlastník.
  - Smazání nemovitosti s aktivní smlouvou vrátí 422.
- [ ] **2.6 Měřiče a odečty** (#7, #8)
  - `MeterPolicy` a `MeterReadingPolicy`: zápis odečtu vlastník a přiřazený správce, úpravy a mazání vlastník.
- [ ] **2.7 Inventář, oznámení, náklady** (#9, #16)
  - `InventoryItemPolicy`, `NoticePolicy`, `ExpensePolicy` + `Expense::visibleTo` (jen vlastník).
  - Skupinu `role:landlord,manager` v `routes/api.php` nahradit policies.
- [ ] **2.8 Smlouvy a hodnocení** (#13, #22, #30)
  - `LeasePolicy` + `Lease::visibleTo`, `RatingPolicy` (vlastník nemovitosti).
  - Kontrola překryvu aktivních smluv. `tenant_id` jen nájemník nebo správce.
  - `end_date` se porovnává s uloženým `start_date`.
- [ ] **2.9 Platby** (#12, #17, #41)
  - `PaymentPolicy` + `Payment::visibleTo`. Správce nevidí platby, částky smluv ani finance na dashboardu.
  - `lease_id` jen z vlastních smluv.
  - Mazání zaplacené platby vrátí 422 (stav), ne 403.
- [ ] **2.10 Tikety, komentáře, fotky** (#10, #11, #23, #24, #46)
  - `TicketPolicy` v pořadí: autor, nájemník s aktivní smlouvou, přiřazený správce, vlastník.
  - `Ticket::visibleTo`, `create(User, Property)`, `TicketCommentPolicy`, `TicketImagePolicy`.
  - `assigned_to` jen správce přiřazený k nemovitosti.
  - Autor upraví název a popis ve stavu `new`.
- [ ] **2.11 Správci** (#14)
  - Promote a demote podle rozhodnutí výše.
  - Přepočet `users.role` po přiřazení a odebrání.
  - Testy na správce u dvou pronajímatelů.
- [ ] **2.12 Data a datumy** (#27, #28, #29)
  - Sloupce `date` ukládat bez času (cast nebo mutator a úprava existujících hodnot).
  - Rozsahy `>=` a `<` ve filtrech, dashboardu a CSV importu.
  - `size` a `disposition` nullable.
- [ ] **2.13 Actions a byznys logika** (#31–#37, #53, #54)
  - Actions pro zápisové operace: nemovitost, smlouva + PDF, platby a generování, CSV import, tiket + komentáře,
    měřiče a odečty, hodnocení, správci. Controllery jen volají Action.
  - Transakce, events po commitu, notifikace `ShouldQueue`.
  - Generování plateb jen pro platné smlouvy, jedna Action pro příkaz i API.
  - Notifikace o konci smlouvy jen jednou, robustní kontrola plateb po splatnosti.
  - Přepočet trust score po hodnocení a změně platby.
  - CSV import páruje podle VS platby.
  - Přechody stavů smlouvy a tiketu na jednom místě.
- [ ] **2.14 Výkon, peníze, indexy, robustnost, úklid** (#42, #44, #45, #47–#52, #56)
  - Indexy na FK (přidávající migrace), agregace místo dotazů v cyklu a test na počet dotazů.
  - Peníze přes `SUM` v DB nebo v haléřích.
  - Validace filtrů, `/health` bez detailů výjimek.
  - `toArray()` notifikací (klíče, česky), výběrové scopy bez stránkování.
  - Typ nákladu `utility` (rozhodnout), `download_url`, mrtvý kód.
- [ ] **Hotovo, když:** `composer check` je zelený, Larastan bez baseline (nebo s malou a zdůvodněnou) a všechny
      nálezy kroků 2.1–2.14 mají opravu s testem.
- [ ] **Po fázi 2: skill `rentflow-security`** podle `jatime-security` a hned první běh (ověří opravy z REVIEW).
  - Audit celé aplikace jako subagent spouštěný uživatelem. Každý nález s `soubor:řádek` a scénářem zneužití.
  - A autentizace: Sanctum tokeny a jejich expirace, od fáze 4 session a limiter `login` (#25).
  - B autorizace: matice z README × každá API metoda a Livewire akce. Izolace pronajímatelů místo „více škol“,
    správce přes `property_manager`, nájemník jen s aktivní smlouvou.
  - C vstupy: uploady (privátní disk, `mimes`), CSV import (vzorce, kódování), DomPDF (uživatelské texty v PDF),
    `$fillable` bez `role` a `trust_score`.
  - D HTTP: `SecurityHeaders`, CORS (vypnutý od 2.1), trust proxies.
  - E data a provoz: fronta `database` a e-maily notifikací, logy, tajemství v repu, audity závislostí.
  - F osobní údaje: nájemníci, citlivé dokumenty, hodnocení a Trust Score.

**Nálezy mimo fázi 2:**

| Nález | Fáze | Co |
| --- | --- | --- |
| #25 | 4 | Rate limiter `login`, změna hesla odhlásí ostatní zařízení. Expirace Sanctum tokenů podle rozhodnutí o API ve fázi 6. |
| review 2.2 | 5 | České názvy atributů ve validaci (`lang/cs/validation.php`, `attributes`): teď „Vybraná stav je neplatná“. |
| review 2.2 | 6 | `days_overdue` v `PaymentResource` je float (`diffInDays` v Carbon 3 vrací desetinné číslo). |

Krok se dá spustit i skillem `/rentflow-step 2.x <název kroku>` (stav repa, plán, kontroly, odškrtnutí, shrnutí).

```text
Fáze 2 z docs/REWRITE_PLAN.md, krok „2.x <název kroku>“. Postupuj podle skillu rentflow-backend.
Oprav nálezy z docs/REVIEW.md uvedené u kroku a každý pokryj testem. Tvar JSON odpovědí API neměň
(výjimka: oprava autorizace, napiš ji). Nejdřív plán (soubory, testy), pak implementace, kontroly (rentflow-checks).
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
- [ ] **Skill `rentflow-design`** podle `jatime-design` (vzniká spolu s komponentami a tokeny, `paths:
      resources/views/**, resources/css/**`).
  - Tabulka komponent `<x-…>` s props, tokeny `@theme`.
  - Závazná pravidla: karty, nebo tabulka, zalamování textů od lidí, `maxlength` = `max:` z validace, dotyk 44 px,
    tlačítka a dialogy, toasty, prázdné stavy, čísla, data a česká typografie, tmavý režim.
  - Přístupnost a gotchas Blade.
  - Bez modulových tříd JaTime (`.nb-*`, `.rp-*`, `.dq-*`, `.tr-*`). Navíc stavy nemovitosti, smlouvy, platby
    a tiketu z enumů, peníze, Trust Score, grafy a záložky detailu nemovitosti.
  - Porovnání se schváleným mockupem RentFlow.
- [ ] **`ThemeTest`** (vzor JaTime): žádné natvrdo hex barvy v Blade a CSS, `tracking-[…]` jen přes token.
- [ ] **`cdp.mjs` v `rentflow-browser`** (vzor `jatime-browser`): headless Edge přes CDP bez pluginu a bez npm
      balíčků, jeden skript na scénář s malým JSON výstupem, `checks.overflow` a `checks.contrast`.
  - Úpravy: `http://rentflow.test` (bez HTTPS), `login()` s heslem `password` a selektory z fáze 4, klíč
    tmavého režimu z fáze 3.
  - Skill pak poběží jako subagent s argumentem „stránky a role“. Vestavěný prohlížeč jen na přání (odpadne hack
    s `requestAnimationFrame`).

```text
Fáze 3 z docs/REWRITE_PLAN.md, krok „<název kroku>“. Vzor je JaTime v C:\dev\jatime (resources/views/components,
resources/css/app.css, layouts/app.blade.php, app/Support/helpers.php). Převezmi strukturu a chování komponent,
vzhled uprav pro RentFlow podle schváleného návrhu. Nepřidávej závislosti mimo plán.
```

## Fáze 4 – autentizace (session)

- [ ] Přihlášení `/login`, registrace `/registrace`, odhlášení `/logout`, zapomenuté heslo `/zapomenute-heslo`
      (vzor `LoginController` z JaTime, rate limiter `login` podle e-mailu a IP, #25)
- [ ] Middleware `role:` pro web (redirect/403 stránka místo JSON), `$middleware->authenticateSessions()`
- [ ] Profil a změna hesla (`/nastaveni`), změna hesla odhlásí ostatní zařízení (#25)
- [ ] Sanctum a API nechat beze změny (API zůstává do rozhodnutí ve fázi 6)

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
- [ ] **České názvy atributů ve validaci** (review 2.2): `lang/cs/validation.php` → `attributes` (`status` → „stav“,
  `type` → „typ“ …) a rod v hlášce `enum`/`in` („Vybraná stav je neplatná“ → např. „Hodnota pole stav je
  neplatná.“). Ověřit na formulářích fáze 5 i v API 422.
- [ ] **Scénář „dlouhé texty“** v `rentflow-browser` (vzor `jatime-browser/dlouhe-texty`, po převodu stránek):
  - `analyze.js`: ořezání, překryv, prvek mimo kartu, dotyk pod 44 px, zalomení po písmenech.
  - `run.mjs`: role × šířky 360–1920 a souhrn.
  - `data.php`: limity RentFlow – adresy a popisy nemovitostí, názvy a texty tiketů, komentáře, jména, názvy
    dokumentů, poznámky plateb, částky do maxima `decimal`.
  - `scenarios.mjs` po stránkách fáze 5.
  - Spolu s `LongTextTest`, který hlídá `wrap-anywhere` u textů od lidí.

```text
Fáze 5 z docs/REWRITE_PLAN.md, stránka „<název>“. Postupuj podle skillu rentflow-page.
Předloha: git show main:frontend/src/pages/<…>.tsx a sekce stránky v docs/FEATURES.md. Nic z inventáře nesmí chybět;
vědomé vynechání napiš. Nejdřív plán, pak implementace, testy, rentflow-checks a rentflow-browser.
```

## Fáze 6 – úklid

- Smazání `frontend/`, proxy, CORS pro SPA (#57), React jobu v CI a `unlighthouse.config.ts` se přesunulo
  do kroku 2.1.
- [ ] Rozhodnout API: ponechat (veřejné API s tokeny a testy), nebo odstranit i se Sanctum. Podle toho nastavit CORS
      (`config/cors.php`, teď vypnutý).
- [ ] `days_overdue` v `PaymentResource` jako celé číslo (review 2.2: Carbon 3 vrací z `diffInDays` float, např.
      `10.707…`). Podle rozhodnutí o API, s testem. Stejný výpočet pro UI fáze 5 přes jeden helper.
- [ ] CI: zapnout spouštěče push/PR, přidat `npm run build` v kořeni a audity (`composer analyse` už je od 2.1)
- [ ] Odstranit worktree s React předlohou (`herd unlink rentflow-main`, `git worktree remove ../rentflow-main`)
- [ ] README (architektura, instalace přes Herd, screenshoty), `CHANGELOG.md`

## Fáze 7 – kvalita a nasazení

- [ ] Playwright E2E na hlavní toky (přihlášení všech rolí, nemovitost, smlouva, platba, tiket s komentářem)
- [ ] Unlighthouse (výkon, přístupnost) na hlavních stránkách: nový `unlighthouse.config.ts` pro `rentflow.test`
      a české URL (starý pro React smazán ve 2.1)
- [ ] Rozhodnout produkci (Coolify na Hetzneru jako JaTime? SQLite vs MariaDB), Dockerfile, zálohy
- [ ] Rozhodnout `ShouldQueue` pro notifikace + queue worker v produkci (teď se posílají synchronně při requestu)
- [ ] Skill `rentflow-release` podle `jatime-release`, až po rozhodnutí o produkci.
  - Verze + `CHANGELOG.md` + README.
  - Připravenost na produkci: `env()` mimo config, `autoload-dev` v běhovém kódu, migrace bez modelů, simulace
    startu `migrate` → `optimize` → smoke, rollback.
  - Návrh popisu PR. Sekce „Migrace a rollback“ z `rentflow-review` se sem přesune.
