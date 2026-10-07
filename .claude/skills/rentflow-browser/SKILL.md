---
name: rentflow-browser
description: Ruční ověření RentFlow v prohlížeči na http://rentflow.test (Herd) – záloha a příprava lokální DB, demo účty všech rolí, proklikání na mobilu 390 px a desktopu 1440 px, útoky z konzole, porovnání s původní React stránkou, konzole a log, měření dotazů. Použij u každé změny UI nebo chování, které testy samy nepokryjí, a při review před mergem.
---

# Proklikání RentFlow v prohlížeči

## 1. Příprava (vždy v tomto pořadí)

1. `npm run build` v kořeni. Testuje se s produkčními assety a CSP (ne `npm run dev`).
2. Herd běží: `curl -s -o /dev/null -w "%{http_code}" http://rentflow.test/up` → 200. Při 502 se zastav a napiš
   uživateli (Inteligentní řízení aplikací ve Windows nebo Herd, ne chyba appky).
3. **Záloha DB** (PowerShell, z kořene repa): `php .claude/skills/rentflow-browser/db-snapshot.php backup`
4. Data: `php artisan migrate:fresh --seed --force`.
5. **Na konci vždy obnov:** `php .claude/skills/rentflow-browser/db-snapshot.php restore` (zkontroluje integritu
   a zálohu smaže). Soubory `database/database.sqlite-wal` / `-shm` nemaž ručně, drží je PHP-FPM.

## 2. Účty a role (seed, heslo `password`)

| Role | Účet | Na co se dívat |
| --- | --- | --- |
| Pronajímatel | `landlord@rentflow.cz` | vše u vlastních nemovitostí, finance, správci |
| Správce | `manager@rentflow.cz` | jen přiřazené nemovitosti, tikety, odečty, žádné finance |
| Nájemník | `marie@rentflow.cz` | jen svůj byt (aktivní smlouva), své platby, tikety |
| Nájemník (jiný byt) | `tomas@rentflow.cz` | na cizí data od Marie nesmí dosáhnout |

Další nájemníci jsou v README. Pro „cizího pronajímatele“ si v testu nebo tinkeru vytvoř druhého pronajímatele
s vlastní nemovitostí a zkoušej přístup křížem.

## 3. Prohlížeč

Výchozí je vestavěný prohlížeč (`mcp__Claude_Browser__*`): `preview_start` s `url: "http://rentflow.test/login"`.
Šířky: `resize_window` 390×844 (mobil) a 1440×900 (desktop). Na konci `preset: "desktop"`.

Přihlášení a odhlášení přes JS (lokální `.test` host, účty ze seedu):

```js
// přihlášení (na /login)
document.getElementById('email').value = 'landlord@rentflow.cz';
document.getElementById('password').value = 'password';
document.querySelector('form[action$="/login"]').submit();
// odhlášení (na libovolné stránce appky)
const t = document.querySelector('meta[name="csrf-token"]').content, f = document.createElement('form');
f.method = 'POST'; f.action = '/logout'; f.innerHTML = `<input name="_token" value="${t}">`;
document.body.appendChild(f); f.submit();
```

(Login formulář musí mít `id="email"`, `id="password"` a `action` končící `/login`, layout `<meta name="csrf-token">`.
Když se to změní, uprav i tenhle skill.)

**Panel bývá skrytý** (okno za jiným oknem) → screenshoty timeoutují a `requestAnimationFrame` neběží, takže Alpine
nedokončí `x-show` / `x-transition` a `$nextTick` se nespustí. Na začátku každé stránky proto:

```js
window.requestAnimationFrame = cb => setTimeout(() => cb(performance.now()), 16);
```

Stav ověřuj měřením v JS (`getBoundingClientRect`, `document.activeElement`, `aria-*`, počty prvků) a screenshot
ber jen jako doplněk. Statusy Livewire požadavků:

```js
window.__st = []; const of = window.fetch;
window.fetch = async (...a) => { const r = await of(...a); window.__st.push(r.status); return r; };
```

## 4. Co proklikat (každá role, 390 i 1440 px)

- Hlavní toky stránky: vytvořit, upravit, smazat, filtry, hledání, stránkování, prázdné stavy, toasty, dialogy
  (Esc zavře, fokus se vrátí).
- Viditelnost: každá role vidí jen to, co matice v README dovoluje (menu, tlačítka, sloupce, finance).
- Mobil: žádné vodorovné přetečení (`document.documentElement.scrollWidth > innerWidth`), dotykové plochy ≥ 44 px.
- Přístupnost: pořadí Tab, viditelný fokus, `aria-pressed`/`aria-expanded`/`aria-controls`, fokus po smazání.
- **Útoky z konzole** jako nejnižší role, která stránku vidí:
  - `$wire.call('akce', cizíId)`, `$wire.$set('zamčenáVlastnost', …)`, podvržené hodnoty formuláře
  - cizí id v URL (`/nemovitosti/{cizí}`, `/smlouvy/{cizí}`, `/tikety/{cizí}`) → 403/404
  - stažení dokumentu cizí nemovitosti a přímý přístup na `/storage/documents/…` → nesmí projít
  - upload `.php`, `.svg` se skriptem, `.html`, přejmenovaný soubor a příliš velký soubor
  - XSS (`<script>`, `javascript:`, `"` v atributech) v adrese, popisu, komentáři tiketu, jménu
  Po každém pokusu stránku znovu načti. Odmítnutý `$set` na `#[Locked]` vlastnost zůstane ve stavu klienta
  a další požadavky pak vrací 500. Výsledek ověř i v DB.
- **Parita s Reactem** (fáze 5): ve `frontend/` `npm run dev` a `http://localhost:5173` vedle sebe. Projdi položky
  stránky v `docs/FEATURES.md` a u každé napiš OK / změněno / chybí.
- Konzole: `read_console_messages` (`onlyErrors`, `pattern: "Content Security Policy"`) a log
  `storage/logs/laravel.log` (`grep -o "local\.[A-Z]*: [^{]\{0,140\}"`).

Rychlá kontrola mnoha stránek bez prohlížeče:
`bash .claude/skills/rentflow-browser/smoke.sh landlord@rentflow.cz password / /nemovitosti /platby`
API (dokud existuje): `MODE=api bash .claude/skills/rentflow-browser/smoke.sh marie@rentflow.cz password /properties /users`

## 5. Měření dotazů a velikosti odpovědí

Dočasný PHPUnit test **mimo repo** (scratchpad), spuštěný z kořene repa:
`php vendor/phpunit/phpunit/phpunit <cesta-k-testu>.php`. V testu `DB::enableQueryLog()`, pro GET `$this->get()`,
pro akce `Livewire::test()->call()` a `strlen($component->html())`. Typická data: pronajímatel s 20 nemovitostmi,
40 smlouvami, 500 platbami a 100 tikety. Uveď čísla před změnou a po ní.

## 6. Výstup

Tabulka `scénář | role | šířka | OK / chyba` + cesty ke screenshotům chyb, výsledky útoků, nalezené chyby z konzole
a logu, parita s Reactem. Na konci potvrď obnovení DB (`db-snapshot.php check`).
