---
name: rentflow-security
description: Bezpečnostní audit celé aplikace RentFlow (ne jen diffu větve) – autentizace (Sanctum tokeny, od fáze 4 session), autorizace každé API metody a Livewire akce podle matice rolí, vstupy a výstupy (uploady, CSV import, PDF), HTTP konfigurace, data a provoz, osobní údaje nájemníků. Jen čte a testuje, nic neopravuje; opravy až po schválení čísel nálezů v hlavní konverzaci. Použij po dokončení fáze přepisu, před nasazením nebo jednou za čtvrt roku.
argument-hint: "[oblast A–F | utoky] – prázdné = celá appka bez útoků přes HTTP"
disable-model-invocation: true
context: fork
background: false
effort: high
---

# Bezpečnostní audit RentFlow

Zadání: $ARGUMENTS
(prázdné = oblasti A–F; `A`–`F` = jen vybrané oblasti; `utoky` = navíc útoky přes HTTP na `rentflow.test`)

## Kontext (stav repa při spuštění)

Větev: !`git branch --show-current`
Poslední commit: !`git log -1 --oneline`
Pracovní strom: !`git status --short || true`

## Pravidla

- **Nic neměň, necommituj.** Běžíš jako samostatný subagent bez kontextu konverzace a nemůžeš volat skilly.
  Postupy čti jako soubory:
  - `CLAUDE.md` (pravidla projektu – přečti celý);
  - `README.md`, sekce „Role a oprávnění“ (matice = specifikace oprávnění);
  - `docs/REWRITE_PLAN.md`: aktuální fáze, „Rozhodnutí z auditu“ a tabulka „Nálezy mimo fázi 2“ (vědomě
    odložené věci);
  - `.claude/skills/rentflow-checks/SKILL.md` (audity závislostí);
  - `.claude/skills/rentflow-browser/SKILL.md` (jen při `utoky`: záloha DB → útoky → obnova DB).
- Audituješ **celou aplikaci na aktuální větvi**, ne diff. Hledej skutečně zneužitelné problémy, ne obecné rady.
- Vědomá rozhodnutí a odložené nálezy z plánu nehlas jako nové chyby. Patří sem například:
  - notifikace synchronně a fronta až ve fázi 7;
  - `SecurityHeaders`, web login, session a limiter `login` až ve fázi 4 (#25);
  - CORS vypnutý do rozhodnutí o API ve fázi 6;
  - SQLite lokálně.

  Uveď je jen v řádku „odložené – stav ověřen“. Když si nejsi jistý, jestli je něco záměr, dej to do otevřených
  otázek.
- Každý nález musí mít **důkaz**: `soubor:řádek` a konkrétní scénář zneužití (kdo, co pošle, co získá).
  Bez scénáře nejde o nález, maximálně o doporučení. Kde to jde, ověř scénář testem nebo požadavkem.
- Šetři kontext: hledej přes `git grep -n`, soubory čti cíleně, ne celé složky.
- PHP, Composer a npm pouštěj přes **PowerShell** (v Git Bash `php` na PATH není).

## Postup

1. **Mapa útočné plochy:**
   - `routes/api.php` (`php artisan route:list --path=api --json` vypíše middleware), `routes/web.php`;
   - middleware v `bootstrap/app.php` a `app/Http/Middleware/*`;
   - controllery `app/Http/Controllers/*`, FormRequesty `app/Http/Requests/*`, policies `app/Policies/*`,
     scopes `visibleTo` v `app/Models/*`, Actions `app/Actions/*`;
   - od fáze 5 **všechny veřejné metody** komponent `app/Livewire/*` a traitů `app/Livewire/Concerns/*`
     (`git grep -n "public function" -- app/Livewire`).
2. **Automatické kontroly:** `composer audit` a `npm audit --omit=dev` (postup z rentflow-checks).
3. **Oblasti A–F** podle checklistu níže.
4. Jen při `utoky`: **útoky přes HTTP** se zálohou DB před a obnovou po (`db-snapshot.php`, záloha samostatně,
   exit 2 → stop). `rentflow.test` vrací 502 → zastav se a napiš.
   - **API:** `MODE=api bash .claude/skills/rentflow-browser/smoke.sh <účet> password /cesta …` a `curl`
     s tokenem demo účtu.
   - Jako nájemník a správce volej akce vlastníka a podvrhuj cizí id v URL i v těle:
     - `property_id`, `lease_id`, `tenant_id`, `assigned_to`, `meter_id`;
     - `role` a `trust_score` v registraci a profilu.
   - Nahraj soubor s nepovolenou příponou nebo typem (SVG, HTML, PHP přejmenované na `.pdf`) a CSV se vzorci
     a neplatným UTF-8.
   - **Od fáze 5** navíc Livewire z konzole: `Livewire.find(id).call('…')` a přepis `#[Locked]` vlastností.
   - Očekávaný výsledek je 403/404/422 a beze změny v DB. Ověř to přes `db-snapshot.php check` nebo dotazem.

## Checklist

### A Autentizace a tokeny

- **Přihlášení a registrace** (`AuthController`):
  - stejná hláška pro neexistující účet a špatné heslo;
  - registrace přijme jen `landlord` / `tenant`;
  - throttle na `login` a `register` (zatím globální `ThrottleRequests` v `bootstrap/app.php`, limiter `login`
    podle e-mailu a IP je #25 ve fázi 4).
- **Sanctum** (`config/sanctum.php`):
  - expirace tokenů (`expiration`), rozhodnutí je ve fázi 6;
  - `abilities`;
  - odhlášení maže jen aktuální token;
  - změna hesla (`ChangePassword`) ověří současné heslo, pravidla síly a smaže ostatní tokeny.
- **Od fáze 4 web login:**
  - `session()->regenerate()` po přihlášení, `invalidate()` a `regenerateToken()` po odhlášení;
  - `Auth::logoutOtherDevices` po změně hesla;
  - `config/session.php` a `.env.example`: `secure`, `http_only`, `same_site`, `lifetime`, `domain`.
- **Pozvánka nájemníka** (`TenantInvitationNotification`): odkaz neobsahuje heslo ani token s neomezenou platností.

### B Autorizace (nejdůležitější)

- **Matice z README × každá API metoda** (a od fáze 5 každá Livewire akce). U každé ověř:
  - policy v metodě (ne jen route middleware `role:`);
  - `index` přes `visibleTo($user)`;
  - vlastnictví cizích klíčů z klienta (`property_id`, `lease_id`, `tenant_id`, `assigned_to`, `meter_id`),
    ne jen `exists`;
  - `find()` cizího id vrací 403/404, ne data;
  - od fáze 5 `#[Locked]` u id.

  Výstup jako tabulka `route nebo komponenta::metoda | role | policy | scope | vlastnictví FK | OK?`. Zapiš do ní
  jen problémy a sporné řádky.
- **Izolace pronajímatelů** (místo „více škol“): pronajímatel nikdy nevidí ani nemění nic pod cizí nemovitostí.
  Platí i pro vnořené zdroje (měřiče a odečty, inventář, dokumenty, oznámení, fotky, tikety, komentáře, platby,
  hodnocení), uživatele (`/users`, hodnocení nájemníka), dashboard a graf, CSV import a PDF smlouvy.
- **Správce:**
  - jen nemovitosti přiřazené přes `property_manager`, jen akce z matice (žádné kauce, PDF, finance, náklady);
  - správce, který je jinde nájemníkem, nesmí díky roli `manager` vidět víc ve svém bytě;
  - povýšení a sesazení správce jen vlastníkem.
- **Nájemník:**
  - jen nemovitosti s **aktivní** smlouvou, platby a smlouvy jen své;
  - po skončení smlouvy přístup končí (dokumenty, tikety, měřiče);
  - neveřejné dokumenty (`visibility`) nevidí.
- **Stavy a přechody se vynucují na serveru:**
  - zaplacená platba se nesmaže a neoznačí znovu;
  - překryv smluv;
  - reaktivace smlouvy v rekonstrukci;
  - tiket upraví autor jen ve stavu `new`, řešitel je jen přiřazený správce.
- **Scheduler a listenery** posílají notifikace jen oprávněným příjemcům (vlastník, přiřazení správci, nájemník
  smlouvy) a obsah notifikace neprozradí cizí data.

### C Vstupy a výstupy

- **Validace** všeho z klienta: typy, rozsahy, `Rule::enum()`, `exists` s omezením, délky textů, data. Řazení
  a filtry v `app/Traits/Filterable.php`: whitelist sloupců, pole v query, neplatné datum.
- **Mass assignment:**
  - `$fillable` bez `role`, `trust_score`, `landlord_id`;
  - žádné `fill($request->all())`, `update($request->all())` ani nevalidované klíče do Action.
- **Raw SQL** (`selectRaw`, `orderByRaw`, `whereRaw`, `DB::raw`, `DB::statement`): jen konstanty nebo vázané
  parametry, žádná interpolace hodnot z klienta.
- **Soubory** (`App\Support\PrivateFile`):
  - jen privátní disk;
  - whitelist `mimes` + `max` (`Document::MIMES`, `PrivateFile::IMAGE_MIMES`), žádné SVG ani HTML;
  - název souboru negenerovaný z inputu;
  - cesta (`*_path`) není v JSON a nejde podvrhnout;
  - stažení autorizovanou routou s `attachment`, `nosniff` a CSP `sandbox`;
  - `Storage::url()` a disk `public` se pro soubory uživatelů nepoužívají.
- **CSV import** (`BankImportService`, `ImportBankPayments`):
  - limit velikosti a počtu řádků, kódování (neplatné UTF-8);
  - vzorce na začátku buňky (`=`, `+`, `-`, `@`) se nikam neexportují bez ošetření;
  - chybný řádek nespadne celou dávku;
  - páruje jen smlouvy vlastních nemovitostí.
- **DomPDF** (`resources/views/pdf/*`, `config/dompdf.php`):
  - uživatelské texty jen přes `{{ }}`;
  - `isRemoteEnabled` a `isPhpEnabled` vypnuté, `chroot`;
  - žádné vzdálené obrázky z uživatelského obsahu.
- **XSS:**
  - `{!! !!}` v Blade (PDF, e-maily, od fáze 3 komponenty);
  - `x-html`, `@js` / `Js::from`;
  - odkazy s uživatelským obsahem (`javascript:`);
  - texty tiketů, komentářů, oznámení a notifikací (`title`, `message` v DB).
- **Neplatné UTF-8** (`RejectInvalidUtf8`) a velké vstupy: limity délek, `max` u polí a souborů.

### D HTTP a konfigurace

- `SecurityHeaders` (od fáze 4): CSP (`frame-ancestors`, `form-action`, `base-uri`, `object-src`), HSTS,
  `Referrer-Policy`, `X-Content-Type-Options`, `Permissions-Policy`.
- CORS (`config/cors.php`): vypnutý nebo jen konkrétní originy, žádné `*` s `supports_credentials`.
- Trust proxies (`bootstrap/app.php`): jen reverzní proxy produkce, ne `*` bez důvodu. Žádné natvrdo `http://`.
- **Chybové odpovědi** (renderery v `bootstrap/app.php`):
  - produkce bez stack trace a textu výjimek (`APP_DEBUG`);
  - `/health` a `/up` bez detailů;
  - 403 neprozradí existenci cizího záznamu víc, než matice dovoluje.
- Throttle: globální limit API, citlivé akce (přihlášení, registrace, změna hesla, upload, CSV import).
- Od fáze 4: CSRF u webových rout, Livewire upload endpoint (`WithFileUploads`: pravidla a velikost i dočasných
  souborů).

### E Data, provoz, závislosti

- **Tajemství v repu:** `git grep -nIiE "password|secret|token|api[_-]?key|APP_KEY=" -- ":!tests" ":!*.lock"`.
  `.env.example`, demo hesla `password` ze seederu a `CLAUDE.md` nejsou nález. `.env`, `database/*.sqlite*`
  a `storage/app/private` musí být v `.gitignore`.
- **Notifikace:** e-maily a DB notifikace jdou jen oprávněným příjemcům. `toArray()` neukládá víc osobních údajů,
  než zvoneček potřebuje. Fronta `database` a worker jsou fáze 7.
- **Logy:** `git grep -n "Log::\|report(\|logger("`. Žádná hesla, tokeny, obsah CSV ani osobní údaje navíc.
- **`.github/workflows`:** minimální `permissions:`, pinované akce, žádná tajemství v logu.
- **Seed a demo data:** demo účty (`password`) se na produkci nedostanou (seeder jen lokálně, `migrate --seed`
  ne v deploy skriptu).
- **Závislosti:** výsledky `composer audit` a `npm audit --omit=dev`, zastaralé balíčky s bezpečnostními
  opravami. Známé výjimky jsou v rentflow-checks.

### F Osobní údaje

- **Inventář osobních údajů v DB** (projdi migrace):
  - uživatelé (jméno, e-mail, telefon …);
  - smlouvy (nájemné, kauce, VS);
  - platby;
  - dokumenty (smlouvy, předávací protokoly, doklady);
  - fotky;
  - tikety a komentáře;
  - hodnocení a Trust Score;
  - notifikace.

  U každého napiš, kdo ho podle kódu vidí, a jestli to odpovídá matici.
- **Hodnocení a Trust Score:** kdo je vidí (jen pronajímatelé se vztahem k nájemníkovi?). Nájemník nevidí
  hodnocení jiných nájemníků. Výpočet nejde ovlivnit podvrženými daty.
- **Citlivé dokumenty:** `visibility`, přístup po skončení smlouvy, smazání souboru z disku při smazání záznamu.
- **Smazání a deaktivace účtu:** co zůstává (soft delete nemovitosti a smlouvy, soubory na disku, notifikace,
  tokeny). Pokud funkce chybí (smazání účtu, export údajů, text o zpracování osobních údajů), napiš to jako
  otevřenou otázku – v plánu zatím nejsou.

## Výstup (pak se zastav)

1. Verdikt jednou větou: celkový stav bezpečnosti a jestli je něco k okamžité opravě.
2. Tabulka nálezů: `# | závažnost (kritická / vysoká / střední / nízká) | oblast | soubor:řádek | scénář zneužití | návrh opravy | test, který to pokryje`.
3. Tabulka sporných API metod a Livewire akcí z oblasti B.
4. Odložené nálezy z plánu: `nález | fáze | stav ověřen`, jen jedním řádkem na nález.
5. Výsledky auditů závislostí (počty a balíčky).
6. Při `utoky`: `útok | role | výsledek | DB beze změny?` a potvrzení obnovy DB.
7. Krátce, co je v pořádku (max. 5 řádků, ať je jasné, co bylo zkontrolované).
8. Otevřené otázky (možná záměr, rozhodne uživatel).

Opravy až po schválení čísel nálezů. Dělají se v hlavní konverzaci podle `CLAUDE.md`: ke každé opravě test
negativní cesty, na konci kontroly (rentflow-checks) a návrh commit zprávy.
