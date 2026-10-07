---
name: rentflow-checks
description: Kompletní kontroly RentFlow jako v CI – testy, Pint, Larastan, build nového UI i starého React frontendu a audity – s hlášením výsledků. Použij po každé změně kódu, před předáním práce a před commitem.
---

# Kontroly RentFlow

Pouštěj přes **PowerShell** z `C:\dev\rentflow` (PHP z Herdu, mimo sandbox podle `.claude/settings.local.json`).

## Vždy

```powershell
php artisan test 2>&1 | Select-Object -Last 5
vendor/bin/pint --test 2>&1 | Select-Object -Last 5
composer analyse 2>&1 | Select-Object -Last 5
npm run build 2>&1 | Select-String "built in|error" | Select-Object -Last 3
```

- `composer analyse` existuje až od fáze 2 (Larastan). Do té doby napiš „Larastan: zatím není v projektu“.
- `npm run build` v kořeni builduje nové UI (Blade + Livewire). Po změně Blade šablon ho spusť vždy, protože nové
  Tailwind třídy se objeví až po buildu.

## Starý React frontend (jen dokud existuje `frontend/`)

Pouštěj, když se změnilo něco ve `frontend/`, v `package*.json`, nebo **v API** (controllery, resources, routy,
requesty), protože React z API čte:

```powershell
Push-Location frontend
npx tsc -b 2>&1 | Select-Object -Last 5
npm run lint 2>&1 | Select-Object -Last 3
npm run build 2>&1 | Select-String "built in|error" | Select-Object -Last 3
Pop-Location
```

ESLint má známá varování (pravidla React Compileru a `any` přepnutá na `warn`). Nepočítej je jako chybu,
ale nepřidávej nová. Chyba (`error`) je chyba.

## Před vydáním nebo mergem navíc

```powershell
composer audit 2>&1 | Select-Object -Last 3
npm audit --omit=dev 2>&1 | Select-Object -Last 3
```

## Když něco selže

- **Test:** spusť jen soubor (`php artisan test tests/Feature/PaymentTest.php`) a oprav příčinu, ne test.
  Když API test musí změnit aserci (jiný tvar odpovědi), zastav se a napiš proč. React na tvaru závisí.
- **Pint:** `vendor/bin/pint <soubor>` opraví formát. Když Pint „opravuje“ docblock nesmyslně (rozdělí větu),
  hledej `@slovo` v textu (např. `*@rentflow.cz`) a přeformuluj ho.
- **Larastan:** typy v PHPDoc (`list<…>`, `array{…}`, `Collection<int, Model>`), `Builder<Model>` u scopů,
  žádné `?->` po non-null, `find()` + `abort(404)` místo `findOrFail()`.
- **Build:** chybějící třída nebo komponenta v Blade je většinou překlep v `<x-…>` nebo cesta v `@source`.
- **Windows:** `composer require balik:^1.0` spolkne `^` → `~1.0` nebo úprava `composer.json` a `composer update`.

## Hlášení

Jedním řádkem na kontrolu: počet testů a asercí, Pint, Larastan, build (+ React, pokud běžel), audity.
Při chybě vlož přesný výstup. Nikdy netvrď „prochází“, když kontrola neproběhla. Napiš, co se nespustilo a proč.
