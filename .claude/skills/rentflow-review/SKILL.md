---
name: rentflow-review
description: Důkladné review větve RentFlow před mergem (do v2 nebo main) – správnost, oprávnění rolí, bezpečnost Livewire i API, data a výkon, čistota, testy, migrace a parita s React předlohou. Fáze 1 jen čte a testuje, opravy až po schválení nálezů. Použij, když uživatel chce review před mergem nebo PR.
---

# Review větve před mergem

Cílová větev je `v2` (kroky přepisu), nebo `main` (merge celého přepisu či samostatné opravy). Zjisti ji
na začátku. Dál v textu je `<cíl>`.

## Fáze 1 – jen čtení a testování (nic neměň, necommituj)

1. `git switch <větev>`, `git pull`, `git fetch`. `git log HEAD..origin/<cíl>` musí být prázdné (jinak nahlas, nemerguj).
2. Kontext: krok v `docs/REWRITE_PLAN.md`, sekce v `docs/FEATURES.md`, případně nálezy v `docs/REVIEW.md`.
   Vědomá rozhodnutí nehlas jako chyby.
3. Rozsah: `git diff --stat <cíl>...HEAD` a pak celý diff, včetně změn mimo hlavní úkol (layout, sidebar, sdílené
   komponenty, CSS, `routes/api.php`, testy).
4. Automatické kontroly: skill `rentflow-checks` včetně auditů. Když se diff dotkl API, i React část.
5. Proklikání a útoky z konzole: skill `rentflow-browser` (se zálohou DB a obnovou na konci).
6. Migrace: `php artisan migrate:status`, nové migrace jen přidávají, rollback starého kódu nad novou DB projde.

### Oblasti review

- **A Správnost:** shoda s plánem a inventářem funkcí. Hraniční stavy: smlouva končí dnes nebo o půlnoci, platba
  splatná posledního dne v měsíci, soft-deleted nemovitost nebo smlouva, nájemník se dvěma smlouvami, správce, který
  je zároveň nájemníkem, souběh dvou karet, záznam smazaný mezitím.
- **B Oprávnění a bezpečnost:**
  - policy v **každé** Livewire akci i API metodě, shoda s maticí v README, `visibleTo` místo ručního větvení
  - `#[Locked]` u id, validace všeho z klienta, vlastnictví cizích klíčů (`lease_id`, `property_id`, `assigned_to`)
  - mass assignment (`$fillable` bez `landlord_id`/`role` z requestu), XSS (`{!! !!}`, `x-html`, `Js::from`, odkazy)
  - soubory: whitelist `mimes`, `max`, privátní disk, download jen přes autorizovanou routu, žádný path z inputu
  - CSV import: velikost, kódování, vzorce na začátku buňky, chyby řádků bez pádu celé dávky
  - rate limiting přihlášení a citlivých akcí, CSRF, `SecurityHeaders`
- **C Data a výkon:** indexy, FK `restrict`/`cascade`, výchozí hodnoty = `$attributes`, N+1, počet dotazů
  a velikost Livewire odpovědí (čísla!), zbytečné překreslování, peníze bez floatů, časová zóna `Europe/Prague`.
- **D Čistota:** logika v Actions (ne v komponentě ani controlleru), mrtvý a duplicitní kód, zastaralé komentáře,
  logika v Blade, magické stringy rolí a statusů (enumy), konzistence s design systémem `<x-…>`.
- **E Testy:** negativní cesty (jiná role, cizí data, nepřiřazený správce, nájemník bez smlouvy, `#[Locked]`),
  hranice dnů se `setTestNow`, testy, které nic neověřují, duplicity, API testy beze změny asercí.
- **F Kompatibilita a nasazení:** dokud existuje `frontend/`, tvar JSON API se nemění. Migrace jen přidávající,
  `env()` mimo config, nové proměnné prostředí, `autoload-dev` v runtime kódu, CSP.
- **G Parita s Reactem** (stránky z fáze 5): každá položka z `docs/FEATURES.md` je hotová, nebo je vědomě
  vynechaná se zdůvodněním.

### Výstup fáze 1 (pak se zastav a čekej)

1. Verdikt: mergnout / mergnout po opravách / nemergovat.
2. Tabulka nálezů: `# | závažnost (blokující / doporučené / kosmetické) | oblast | soubor:řádek | problém | návrh opravy`.
3. Proklikané scénáře: `scénář | role | šířka | OK / chyba` (+ screenshoty chyb).
4. Výsledky kontrol a naměřené dotazy.
5. Migrace a rollback.
6. Otevřené otázky a odchylky od plánu, které nejsou chyba.

## Fáze 2 – opravy (jen schválená čísla)

Ke každé opravě test, pokud dává smysl. UI ověř v prohlížeči. Na konci kontroly, obnova DB, seznam souborů
po skupinách a návrh commit zpráv. Necommituj.
