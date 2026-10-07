---
name: rentflow-backend
description: Postup pro změnu backendu RentFlow – autorizace (policy + role), viditelnost dat (scope visibleTo), validace, logika v Actions, tenké API controllery, migrace a testy pro všechny role. Použij ve fázi 2 přepisu a kdykoli se mění byznys logika, oprávnění nebo datový model.
---

# Změna backendu (policy → scope → Action → controller → testy)

Cíl: jedna pravda o tom, **kdo smí co** (policy), **kdo co vidí** (scope `visibleTo`) a **co se děje** (Action).
API controllery i Livewire komponenty jen autorizují, validují, zavolají Action a vrátí výsledek.

## 0. Než začneš

- Najdi všechny vstupy do logiky: `grep -rn "Model::\|->update(\|->create(\|->delete(" app/` v controllerech,
  příkazech (`app/Console/Commands`), listenerech a (od fáze 5) Livewire.
- Specifikace oprávnění je matice v `README.md`. Rozpor kódu s maticí nehádej a napiš ho uživateli.
- Spusť testy oblasti (`php artisan test --filter=Payment`) a zapiš si výchozí stav.

## 1. Role a policy

- Role přes enum `App\Enums\Role` (`Landlord`, `Manager`, `Tenant`) a cast na `User`. Helpery na `User`:
  `isLandlord()`, `managesProperty(Property $p)`, `hasActiveLeaseOn(Property $p)`. Nesrovnávej stringy v kódu.
- Policy pro každý model (`app/Policies`, auto-discovery). Metody podle akcí (`viewAny`, `view`, `create`,
  `update`, `delete` + vlastní jako `markPaid`, `resolve`, `rate`, `assignManager`).
- Pravidla:
  - pronajímatel: jen vlastní nemovitosti a vše pod nimi (`property.landlord_id`)
  - správce: jen přiřazené nemovitosti (`property_manager`) a jen akce, které mu matice dává
  - nájemník: jen nemovitosti s **aktivní** smlouvou, platby a smlouvy jen své
- U vnořených zdrojů (měřič → nemovitost, komentář → tiket → nemovitost) policy deleguje na rodiče.
- `RoleMiddleware` nech jen jako hrubé síto. Rozhoduje policy.

## 2. Viditelnost dat – scope `visibleTo`

```php
/** @param Builder<Property> $query */
public function scopeVisibleTo(Builder $query, User $user): void
```

- Jeden scope na modelu (Property, Lease, Payment, Ticket, Document, Expense…), který vrací přesně to, co matice
  dovoluje vidět. Používá ho API `index` i Livewire seznamy. Z controllerů zmizí větvení `if ($user->role === …)`.
- Pod-dotazy (`whereIn` přes `property_manager`, aktivní smlouvy) piš jako `whereHas`/`whereIn` subquery,
  ne `pluck()` do PHP a zpět.

## 3. Validace

- API: `FormRequest` (`app/Http/Requests`). `authorize()` vrací `true`, autorizace je v policy.
- Livewire: stejná pravidla. Sdílej je statickou metodou (`StorePaymentRequest::baseRules()`) nebo je dej
  do Action (`CreatePayment::rules()`). Nekopíruj pravidla do dvou míst.
- Cizí klíče z klienta (`lease_id`, `property_id`, `assigned_to`) ověř i **vlastnictvím**, ne jen `exists`.
  Pravidlo `exists` s `where` nebo kontrola v Action přes `visibleTo`.
- Upload: `file`, `mimes` (whitelist), `max` v kB. Žádné `svg`/`html` od uživatelů, název souboru negeneruj z inputu.

## 4. Action

```php
namespace App\Actions\Payments;

final class MarkPaymentPaid
{
    public function handle(Payment $payment, CarbonImmutable $paidDate): Payment
    {
        return DB::transaction(function () use ($payment, $paidDate): Payment {
            // změna dat, přepočty, event PaymentMarkedPaid
        });
    }
}
```

- `app/Actions/<Oblast>/<Sloveso><Věc>.php`, jedna veřejná `handle()`, závislosti přes konstruktor (DI).
- Action **předpokládá**, že volající autorizoval. Transakce, události a přepočty (Trust Score) jsou uvnitř.
- Žádné `request()`, `auth()` ani HTTP odpovědi v Action. Vstup jsou typované parametry nebo validované pole.
- Akce nad více záznamy (generování plateb, CSV import) vrací souhrn (počty vytvořených, přeskočených, chyb).

## 5. API controller (dokud existuje React)

- `authorize` → `FormRequest` → Action → stejný `Resource` jako dřív. **Tvar JSON se nemění.**
- `index`: `Model::query()->visibleTo($user)` + `Filterable` + eager loading toho, co Resource čte.
- `find()` + `if ($x === null) { abort(404); }`.

## 6. Data a migrace

- Migrace jen přidávající (nové sloupce, tabulky, indexy, FK). Bez modelů a enumů, jen `Schema` / `DB::table()`.
  Data potřebná na produkci vznikají migrací, ne seederem.
- Indexy na sloupcích ve `where`/`orderBy` seznamů a na FK. U FK rozhodni `cascade` / `restrict` / `nullOnDelete`
  a napiš proč.
- Model se sloupcem s DB defaultem má `$attributes` se stejnou hodnotou (`shouldBeStrict`).
- Enum sloupce: cast na enum, validace `Rule::enum()`.

## 7. Testy (ke každé změně)

- Policy: tabulka rolí (pronajímatel vlastník / cizí pronajímatel / správce přiřazený / nepřiřazený / nájemník
  s aktivní / bez aktivní smlouvy) × akce. Data provider místo kopírování.
- Scope `visibleTo`: každá role vidí přesně svoje a nic cizího.
- Action: hlavní cesta, hranice (datum dnes / konec měsíce přes `Carbon::setTestNow()`), souběh, kde dává smysl.
- API: stávající testy zelené beze změny asercí. Nové testy na opravené díry (403/404 místo dat).
- Počet dotazů nezávisí na množství dat: dva běhy s 5 a 50 záznamy, porovnat `count(DB::getQueryLog())`.

## 8. Konec

Kontroly (skill `rentflow-checks`), odškrtnutí kroku v `docs/REWRITE_PLAN.md`, souhrn: co se změnilo v oprávněních
(tabulka role × akce před a po), seznam souborů, návrh commit zprávy. Necommituj.
