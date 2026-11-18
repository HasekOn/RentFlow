---
name: rentflow-checks
description: Kompletní kontroly RentFlow jako v CI – testy, Pint, Larastan, build UI a audity – s hlášením výsledků. Použij po každé změně kódu, před předáním práce a před commitem. Běží jako samostatný subagent – v argumentu předej, co se změnilo (soubory, oblast).
argument-hint: "[co se změnilo – soubory, oblast]"
context: fork
agent: general-purpose
model: haiku
background: false
---

# Kontroly RentFlow

**Úkol: spusť kontroly, nic neopravuj, vrať hlášení (řádek na kontrolu + přesný výstup selhání).**
Co se změnilo (od volajícího): $ARGUMENTS

Pouštěj přes **PowerShell** z `C:\dev\rentflow` (PHP z Herdu, mimo sandbox podle `.claude/settings.local.json`).
Každý příkaz spusť jen jednou. Výpis selhání je **nad** souhrnem, proto u testů ber posledních 40 řádků.
Testy běží sériově (paratest v projektu není). Jednotlivý soubor nebo `--filter` jen na žádost volajícího.

```powershell
php artisan config:clear 2>&1 | Out-Null
php artisan test 2>&1 | Select-Object -Last 40
vendor/bin/pint --test 2>&1 | Select-Object -Last 5
composer analyse 2>&1 | Select-Object -Last 5
npm run build 2>&1 | Select-String "built in|error" | Select-Object -Last 3
```

`composer check` je totéž bez buildu (Pint + Larastan + testy) a zastaví se na první chybě. Pro hlášení pouštěj
příkazy zvlášť, ať je vidět výsledek každého.

Audity jako v CI (před mergem, před vydáním, po změně `composer.*` / `package*.json`, nebo když to volající zmíní):

```powershell
composer audit 2>&1 | Select-Object -Last 3; "composer audit exit=$LASTEXITCODE"   # 0 = bez zranitelnosti (opuštěné balíčky jen hlásí)
npm audit --omit=dev 2>&1 | Select-Object -Last 3
```

Opuštěné `dragon-code/*` (závislost `laravel-lang`) jsou známé a exit kód nemění (`audit.abandoned: report`).
Plný `npm audit` (s dev závislostmi) se nespouští – známý ReDoS v `picomatch` přes Vite zatím nemá opravu.

## Když něco selže

Neopravuj. Do hlášení dej přesný výstup selhání (u testů název testu, zprávu a soubor:řádek; u Pintu seznam
souborů; u Larastanu řádky s chybou).

Nápověda pro volajícího, kam se podívat:

- **Test:** volající opraví příčinu, ne test. API testy jsou záchranná síť do konce fáze 5 – když musí změnit
  aserci (jiný tvar odpovědi), napíše proč.
- **Pint:** `vendor/bin/pint <soubor>` opraví formát. `lang/` je z Pintu vyřazený (generuje ho laravel-lang).
  Když Pint „opravuje“ docblock nesmyslně (rozdělí větu), bývá v textu `@slovo` (např. `*@rentflow.cz`) – přeformulovat.
- **Larastan:** typy v PHPDoc (`list<…>`, `array{…}`, `Collection<int, Model>`), generika relací
  (`@return HasMany<Model, $this>`), `Builder<Model>` u scopů, žádné `?->` po non-null, `find()` + `abort(404)`
  místo `findOrFail()`. Baseline se nezakládá.
- **Build:** nové Tailwind třídy se objeví až po buildu – po změně Blade šablon je build nutný před kontrolou
  v prohlížeči. Chybějící komponenta bývá překlep v `<x-…>` nebo cesta v `@source`.
- **Windows:** `composer require balik:^1.0` spolkne `^` → `~1.0` nebo úprava `composer.json` a `composer update`.

## Hlášení

Jedním řádkem na kontrolu: počet testů a asercí, Pint, Larastan, build, audity (pokud běžely). Při chybě vlož
přesný výstup. Nikdy netvrď „prochází“, když kontrola neproběhla – napiš, co se nespustilo a proč
(např. `rentflow.test` 502, chybějící `node_modules`).
