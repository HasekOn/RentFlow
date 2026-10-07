---
name: rentflow-page
description: Převod jedné React stránky RentFlow (frontend/src/pages) na Livewire 4 + Blade + Alpine s novým designem – inventář funkcí, mapování API na Actions a scopes, route, komponenta, šablona z design systému, testy pro všechny role a kontrola parity v prohlížeči. Použij ve fázi 5 přepisu pro každou stránku nebo záložku.
---

# Převod stránky z Reactu na Livewire

## 0. Předpoklady (jinak se zastav a napiš)

- Backend oblasti je hotový podle fáze 2: policy, scope `visibleTo` a Actions. Chybí → nejdřív skill
  `rentflow-backend`, ne obcházení v komponentě.
- Design systém a layout z fáze 3 existují (`resources/views/components`, `layouts/app.blade.php`).
- Stránka má sekci v `docs/FEATURES.md`. Když chybí, nejdřív ji doplň z React kódu.

## 1. Pochop předlohu

- Přečti `frontend/src/pages/<…>.tsx`, komponenty, které importuje, volání v `frontend/src/api/*.ts`
  a typy v `frontend/src/types/index.ts`.
- Doplň nebo ověř checklist v `docs/FEATURES.md`: každé tlačítko, filtr, řazení, stránkování, modal, potvrzení,
  prázdný stav, toast, chybová hláška, polling a **rozdíly podle role** (co vidí pronajímatel, správce, nájemník).
- Spusť starou appku pro porovnání: ve `frontend/` `npm run dev` (`.env.local` s `API_URL=http://rentflow.test`),
  pak `http://localhost:5173`.

## 2. Mapování a návrh (plán ke schválení)

Tabulka: `funkce | API volání v Reactu | Action / scope / policy v Laravelu | Livewire metoda nebo vlastnost`.
Navrhni rozdělení na komponenty a URL. Vědomé změny chování (zjednodušení, oprava UX) napiš zvlášť.

Konvence URL a komponent:

| React | Livewire route | Komponenta |
| --- | --- | --- |
| `/` | `/` | `Dashboard` |
| `/properties`, `/properties/:id` | `/nemovitosti`, `/nemovitosti/{property}` | `Properties`, `PropertyDetail` (+ záložky `Property\Meters`, `Property\Inventory`, `Property\Expenses`, `Property\Documents`, `Property\Notices`, `Property\Managers`, `Property\Images`) |
| `/leases`, `/leases/:id` | `/smlouvy`, `/smlouvy/{lease}` | `Leases`, `LeaseDetail` |
| `/payments` | `/platby` | `Payments` |
| `/tickets`, `/tickets/:id` | `/tikety`, `/tikety/{ticket}` | `Tickets`, `TicketDetail` |
| `/people`, `/people/:id` | `/lide`, `/lide/{user}` | `People`, `PersonDetail` |
| `/documents` | `/dokumenty` | `Documents` |
| `/settings` | `/nastaveni` | `Settings` |

Pojmenované routy anglicky (`properties.index`, `properties.show`). URL a texty česky.

## 3. Route

- `routes/web.php` ve skupině `auth`. Route model binding + `->can('view', 'property')` jako první síto.
- Role, kterým stránka vůbec nepatří (např. nájemník a `/lide`), dostanou 403 už na routě.

## 4. Komponenta (`app/Livewire`, class-based)

- `#[Title('Nemovitosti – RentFlow')]`, docblock nahoře: URL, role, co stránka umí (vzor `MyLeave` v JaTime).
- `mount()`: `$this->authorize('view', $property)`. Id záznamů, se kterými se pracuje, ukládej jako `#[Locked] int`,
  model si v akci načti znovu přes `find()` + `abort(404)` a **znovu autorizuj**.
- **Každá akce**: `$this->authorize(...)` → `$this->validate()` → Action → toast → zavření modalu serverovým eventem.
- Seznamy: `Model::query()->visibleTo(auth()->user())`, eager loading, `WithPagination`, filtry a hledání
  jako `#[Url]` vlastnosti (sdílitelný odkaz). `updated*` na filtrech → `resetPage()`.
- Formuláře: Livewire Form objekty (`app/Livewire/Forms`), pokud má stránka víc než 3 pole. Pravidla sdílená
  s FormRequestem nebo Action.
- Uploady: `WithFileUploads`, validace `mimes`/`max` v komponentě i v Action, ukládání na privátní disk.
- Velké stránky dělit: detail nemovitosti = rodič + záložky jako samostatné komponenty načítané líně
  (`lazy`), každá s vlastní autorizací. Rodič předává jen id (`#[Locked]`).
- Polling (notifikace): `wire:poll.60s` jen tam, kde ho měl React (Livewire ho v neaktivní záložce sám zpomalí).
- Grafy: Alpine komponenta + knihovna z plánu, data z Livewire přes `Js::from()`, barvy z `@theme` tokenů.

## 5. Šablona (`resources/views/livewire`)

- Jen design systém `<x-…>`. Nový vzhled podle schváleného návrhu, žádné jednorázové styly, když komponenta existuje.
- Mobil první: 390 px bez vodorovného přetečení, dotykové plochy ≥ 44 px, tabulky na mobilu jako karty.
- Přístupnost: nadpisy v pořadí, `label` u polí, `aria-*` u přepínačů a menu, viditelný fokus, fokus po zavření
  dialogu zpět na spouštěč.
- Texty česky a česká typografie (`cz_plural`, nezlomitelné mezery, `cz_money`, `cz_date`). Prázdné stavy a chybové
  hlášky lidsky.
- Odkaz v sidebaru jen pro role, které stránku vidí.

## 6. Testy (`tests/Feature/Livewire/<Stránka>Test.php`)

- Render pro každou roli, která stránku vidí. 403 pro ostatní. 404 pro cizí nebo neexistující záznam.
- Každá akce: úspěch (stav v DB, event, toast), jiná role 403, cizí data, `#[Locked]` (`$set` → výjimka),
  validace (hraniční hodnoty).
- Seznam: filtr, hledání, stránkování. Vidí jen `visibleTo`.
- Počet dotazů při renderu nezávisí na množství dat (5 vs. 50 záznamů).

## 7. Ověření a konec

1. Kontroly (skill `rentflow-checks`).
2. Prohlížeč (skill `rentflow-browser`): všechny role, 390 a 1440 px, útoky z konzole a **porovnání s React
   stránkou** bod po bodu podle `docs/FEATURES.md`.
3. V `docs/FEATURES.md` odškrtni převedené funkce. V `docs/REWRITE_PLAN.md` odškrtni stránku.
4. React stránku nemaž (to je fáze 6).
5. Souhrn: tabulka parity (funkce → OK / změněno / vynecháno + proč), seznam souborů, návrh commit zprávy.
   Necommituj.
