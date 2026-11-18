---
name: rentflow-checks
description: Kompletní kontroly RentFlow jako v CI – testy, Pint, Larastan, build UI a audity – s hlášením výsledků. Použij po každé změně kódu, před předáním práce a před commitem.
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

- `composer check` = Pint + Larastan + testy najednou (jako v CI).
- `npm run build` v kořeni builduje nové UI (Blade + Livewire). Po změně Blade šablon ho spusť vždy, protože nové
  Tailwind třídy se objeví až po buildu.

## Před vydáním nebo mergem navíc

```powershell
composer audit 2>&1 | Select-Object -Last 3
npm audit --omit=dev 2>&1 | Select-Object -Last 3
```

## Když něco selže

- **Test:** spusť jen soubor (`php artisan test tests/Feature/PaymentTest.php`) a oprav příčinu, ne test.
  Když API test musí změnit aserci (jiný tvar odpovědi), zastav se a napiš proč.
  API testy jsou záchranná síť do konce fáze 5, aserce se neoslabují.
- **Pint:** `vendor/bin/pint <soubor>` opraví formát. Když Pint „opravuje“ docblock nesmyslně (rozdělí větu),
  hledej `@slovo` v textu (např. `*@rentflow.cz`) a přeformuluj ho.
- **Larastan:** typy v PHPDoc (`list<…>`, `array{…}`, `Collection<int, Model>`), `Builder<Model>` u scopů,
  žádné `?->` po non-null, `find()` + `abort(404)` místo `findOrFail()`.
- **Build:** chybějící třída nebo komponenta v Blade je většinou překlep v `<x-…>` nebo cesta v `@source`.
- **Windows:** `composer require balik:^1.0` spolkne `^` → `~1.0` nebo úprava `composer.json` a `composer update`.

## Hlášení

Jedním řádkem na kontrolu: počet testů a asercí, Pint, Larastan, build, audity.
Při chybě vlož přesný výstup. Nikdy netvrď „prochází“, když kontrola neproběhla. Napiš, co se nespustilo a proč.
