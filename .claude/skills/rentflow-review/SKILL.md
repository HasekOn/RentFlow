---
name: rentflow-review
description: Důkladné review větve RentFlow před mergem (krok přepisu do v2, celý přepis do main) – správnost, oprávnění rolí, bezpečnost Livewire i API, data a výkon, čistota kódu, testy, migrace a rollback, parita s React předlohou. Jen čte a testuje, nic neopravuje; opravy až po schválení čísel nálezů v hlavní konverzaci. Použij, když uživatel chce review před mergem nebo PR.
argument-hint: "[cílová větev: v2 (výchozí) | main]"
disable-model-invocation: true
context: fork
background: false
effort: high
---

# Review větve před mergem

Cílová větev: $ARGUMENTS (prázdné = `v2`, kam jdou kroky přepisu; `main` jen pro merge celého přepisu nebo
samostatnou opravu). Dál v textu je `<cíl>`. Kontext níže je proti `v2` – při cíli `main` si ho zopakuj sám.

## Kontext (stav repa při spuštění)

Aktuální větev: !`git branch --show-current`

Pracovní strom:
!`git status --short || true`

Commity oproti v2:
!`git log --oneline v2..HEAD || true`

Rozsah změn oproti v2:
!`git diff --stat v2...HEAD || true`

## Jen čtení a testování (nic neměň, necommituj)

Běžíš jako samostatný subagent a nemůžeš volat další skilly. Postupy proto **načti jako soubory** a proveď sám:
`.claude/skills/rentflow-checks/SKILL.md` (kontroly) a `.claude/skills/rentflow-browser/SKILL.md` (proklikání,
záloha a obnova DB). Pravidla projektu jsou v `CLAUDE.md` – přečti ho celý. Specifikace oprávnění je matice
v `README.md` (sekce „Role a oprávnění“).

1. Nečistý strom nebo jiná větev, než se čeká → nepřepínej, nahlas to a pracuj nad aktuální větví.
   `git fetch`; `git log HEAD..origin/<cíl>` musí být prázdné (jinak nahlas, nemerguj).
2. Kontext úkolu: krok v `docs/REWRITE_PLAN.md` (rozhodnutí z auditu, poznámky pod krokem), čísla nálezů
   v `docs/REVIEW.md`, které krok opravuje, a ve fázi 5 sekce stránky v `docs/FEATURES.md`.
   Vědomá rozhodnutí nehlas jako chyby.
3. Rozsah: `git diff --stat <cíl>...HEAD` (výše) a pak celý diff, včetně změn mimo hlavní úkol (layout, sidebar,
   sdílené komponenty, CSS, `routes/api.php`, `bootstrap/app.php`, testy, skills, CLAUDE.md).
4. Automatické kontroly včetně auditů (postup z rentflow-checks).
5. Ověření chování:
   - **API** (fáze 2): `MODE=api bash .claude/skills/rentflow-browser/smoke.sh <účet> password /cesta …` pro
     pronajímatele, správce, Marii i Tomáše + útoky na cizí id (`/properties/{cizí}`, `/leases/{cizí}` …).
   - **UI** (od fáze 3): proklikání a útoky z konzole podle rentflow-browser – se zálohou DB a obnovou na konci.
6. Migrace a rollback (část níže).

### Oblasti review

- **A Správnost:** shoda s plánem, rozhodnutími z auditu a inventářem funkcí. Hraniční stavy: smlouva začíná nebo
  končí dnes / o půlnoci (`Europe/Prague`), platba splatná posledního dne v měsíci, soft-deleted nemovitost nebo
  smlouva, nájemník se dvěma smlouvami, správce, který je jinde nájemníkem, překryv aktivních smluv, souběh dvou
  karet, záznam smazaný mezitím.
- **B Oprávnění a bezpečnost:**
  - policy v **každé** API metodě i Livewire akci, shoda s maticí v README, `visibleTo()` místo větvení podle role
  - role křížem: pronajímatel vlastník / **cizí pronajímatel** / správce přiřazený / **nepřiřazený** / nájemník
    s aktivní smlouvou / **bez aktivní smlouvy** / správce, který je jinde nájemníkem
  - `#[Locked]` u id, validace všeho z klienta, vlastnictví cizích klíčů (`lease_id`, `property_id`, `assigned_to`,
    `tenant_id`) – ne jen `exists`
  - mass assignment (`$fillable` bez `role`, `trust_score`, `landlord_id` z requestu), XSS (`{!! !!}`, `x-html`,
    `Js::from`, odkazy s `javascript:`), obsah PDF smlouvy (DomPDF) z uživatelských textů
  - soubory: whitelist `mimes`, `max`, privátní disk, stažení jen autorizovanou routou, žádná cesta z inputu
  - CSV import: velikost, kódování, vzorce na začátku buňky, chyby řádků bez pádu celé dávky
  - rate limiting přihlášení a citlivých akcí, CSRF, `SecurityHeaders`, CORS (vypnutý od 2.1)
- **C Data a výkon:** indexy, FK `restrict`/`cascade`/`nullOnDelete`, `$attributes` = DB defaulty, N+1 (strict
  mode chytí jen kolekce > 1 – zkus seedovaná data), počet dotazů nezávislý na množství dat (čísla!), velikost
  Livewire odpovědí, peníze přes `SUM` nebo haléře (ne float), data `>=` / `<`.
- **D Čistota:** checklist níže.
- **E Testy:** negativní cesty z bodu B, `#[Locked]`, validace, hranice dnů se `Carbon::setTestNow()`, testy, které
  nic neověřují, duplicity, API testy beze změny asercí (změna jen zdůvodněná).
- **F Nasazení:** migrace jen přidávající (výjimka jen podle plánu, např. #29 `->change()`), starý kód nad novou DB,
  data přes migraci (ne seeder), `env()` jen v `config/`, nové proměnné prostředí nahlas, `autoload-dev` a factory
  mimo běhový kód, CSP.
- **G Parita s Reactem** (stránky z fáze 5): každá položka z `docs/FEATURES.md` je hotová, nebo je vědomě
  vynechaná se zdůvodněním. Předloha: `git show main:frontend/src/pages/…`, spuštění přes worktree (skill
  `rentflow-page`).

### Čistota kódu (závazný checklist – i před předáním každého kroku, viz rentflow-step)

Strojově hlídá `tests/Feature/CodeHygieneTest.php`: ladicí výpisy a `TODO`, holé hodnoty rolí a stavů v `app/`,
nepoužité třídy v `app/` (od fáze 3 i Blade komponenty a partials). Výjimky jen v testu s komentářem. Zbytek níž
je na úsudku.

- **DRY:** opakovaná logika do Action (`app/Actions/<Oblast>`), scope `visibleTo()`, policy, traitu
  (`app/Livewire/Concerns`), Blade komponenty nebo partialu. Než napíšeš nové, hledej existující: `TrustScoreService`,
  `BankImportService`, `Filterable`, scopy modelů, `cz_*` helpery (od fáze 3), `<x-…>` komponenty.
- **Enumy a konstanty:** žádné holé řetězce rolí a statusů (`'landlord'`, `'paid'`, `'active'` …) v PHP ani Blade,
  i v `$attributes` modelu → `Role::Landlord->value` (od kroku 2.2). Popisek, tón a ikona stavu patří do enumu, ne
  do šablony. Limity a velikosti jako konstanta, hlášky s limitem z konstanty.
- **Vzory repa:** autorizace v každé akci, `#[Locked]` u id, `find()` + `abort(404)` (ne `findOrFail()`,
  `abort_if()`), validace všeho z klienta, eager loading, `$attributes` = DB defaulty, data `>=` / `<`,
  transakce u vícekrokových změn, eventy po commitu, idempotence (dvojklik nic nezdvojí, generování plateb
  dvakrát nic nezdvojí).
- **Velikost:** controller jen autorizace → validace → Action → Resource. Livewire komponenta nad ~400 řádků nebo
  metoda nad ~60 → rozdělit. Žádný mrtvý ani zakomentovaný kód, nepoužité importy, proměnné, parametry.
- **Blade a CSS** (od fáze 3): jen design systém `<x-…>` a tokeny, logika v PHP (ne v `@php`), celé třídy
  vypsané doslova.
- **Testy:** bez duplicit (factory stavy, data providery, pomocné metody s jinými názvy než metody `TestCase`).
- **Texty:** čeština, nezlomitelné mezery v datech, u částek a po jednopísmenných předložkách.

### Migrace a rollback

- `php artisan migrate:status` a nové migrace větve: jen `Schema` / `DB::table()`, žádné modely ani enumy.
- Rollback úvahou nad diffem (u nových sloupců, tabulek a změn typu): poběží kód z `<cíl>` nad DB s novými
  migracemi? Když to není jasné, simulace se zálohou DB (rentflow-browser): na `<cíl>` (jen s čistým stromem)
  `migrate`, smoke všech rolí, log bez nových chyb, obnova DB.
- Produkce zatím není rozhodnutá (fáze 7) – nasazovací specifika (Docker, proxy) nehodnoť.

### Výstup (pak se zastav)

1. Verdikt: mergnout / mergnout po opravách / nemergovat.
2. Tabulka nálezů: `# | závažnost (blokující / doporučené / kosmetické) | oblast | soubor:řádek | problém | návrh opravy`.
3. Ověřené scénáře: `scénář | role | šířka (UI) / endpoint (API) | OK / chyba` (+ cesty ke screenshotům chyb).
4. Výsledky kontrol a naměřené dotazy.
5. Migrace a rollback.
6. Otevřené otázky a odchylky od plánu, které nejsou chyba.
7. Potvrzení obnovy DB (`db-snapshot.php check`), pokud se zálohovalo.

Opravy až po schválení čísel nálezů – dělají se v hlavní konverzaci podle `CLAUDE.md` (ke každé opravě test,
UI ověřit v prohlížeči, na konci kontroly, seznam souborů po skupinách a návrh commit zpráv). Necommituj.
