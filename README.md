# 🏠 RentFlow — Property Management SaaS

**RentFlow** je webová aplikace pro správu pronájmu nemovitostí. Umožňuje pronajímatelům, správcům a nájemníkům
efektivně spravovat byty, nájemní smlouvy, platby, závady a komunikaci — vše z jednoho místa.

> **Bakalářská práce** — 2025/2026

---

## 📋 Obsah

- [Přehled funkcionality](#-přehled-funkcionality)
- [Architektura](#-architektura)
- [Technologie](#-technologie)
- [Datový model](#-datový-model)
- [Role a oprávnění](#-role-a-oprávnění)
- [Instalace a spuštění](#-instalace-a-spuštění)
- [Seed data](#-seed-data)
- [API přehled](#-api-přehled)
- [Automatizace](#-automatizace)
- [Testování](#-testování)
- [CI/CD](#-cicd)
- [Struktura projektu](#-struktura-projektu)

---

## ✨ Přehled funkcionality

### Správa nemovitostí (Portfolio)

- CRUD operace nad nemovitostmi s galérií fotek (upload, lightbox, mazání)
- Stav nemovitosti: volný → obsazený → rekonstrukce (automatická změna)
- Detail s kartami: overview, management (meters, tickets, expenses, inventory, documents, notices)

### Nájemní smlouvy (Leases)

- Vytváření smluv s automatickým propojením tenant ↔ property
- Generování PDF smlouvy (DomPDF)
- Automatická expirace smluv po uplynutí `end_date`
- Blokace pronájmu nemovitosti v rekonstrukci

### Platby (Payments)

- Automatické generování měsíčních plateb (nájem + zálohy na služby)
- Mark as Paid s přepočtem Trust Score
- CSV import z bankovního výpisu (párování přes variabilní symbol)
- Automatická detekce overdue plateb

### Helpdesk (Tickets)

- Nájemník nahlásí závadu → komunikace přes komentáře
- Fotogalerie na ticketu + přílohy v komentářích
- Životní cyklus: new → in_progress → resolved / rejected
- Editace: priorita, kategorie, přiřazení správce

### Měřiče (Meters)

- Registrace měřičů (voda, elektřina, plyn, teplo) s sériovými čísly
- Odečty s historií a realistickými přírůstky
- Editace a mazání měřičů (landlord)

### Dokumenty

- Upload souborů (PDF, DOC, XLS, obrázky) k nemovitosti
- Download s korektní příponou, mazání
- Typy: smlouva, pojistka, revize, energetický štítek, faktura...

### Nástěnka (Notices)

- Pronajímatel vytváří oznámení pro nájemníky
- Aktivace / deaktivace oznámení
- Viditelné v management tabu property detailu

### Hodnocení (Ratings)

- Pronajímatel hodnotí nájemníka po ukončení smlouvy
- 4 kategorie: stav bytu, komunikace, dodržování pravidel, celkově
- Všechny kategorie v jednom formuláři s hvězdičkami
- Souhrnná karta s dialog pro detail

### Trust Score

- Automatický výpočet spolehlivosti nájemníka (0–100)
- Vzorec: (platební morálka × 0.6) + (průměr hodnocení × 0.4)
- Přepočet při označení platby, po ratingu

### Notifikace

- Zvoneček v sidebaru s počtem nepřečtených
- Typy: nová smlouva, nový ticket, vyřešený ticket, platba přijata, overdue
- Mark as read, mark all read, auto-poll 60s

### Manager Flow

- Landlord povýší tenanta na managera
- Přiřazení managera ke konkrétním nemovitostem (pivot tabulka)
- Manager dual dashboard: Manager View + My Tenancy
- Manager může: řešit tickety, logovat meter odečty (jen přiřazené), vytvářet tickety

---

## 🏗 Architektura

```
┌─────────────────────────────────────────────────────────┐
│                      Browser                             │
│  React 19 + TypeScript + Tailwind CSS + Vite             │
└──────────────────────┬──────────────────────────────────┘
                       │ HTTP (Axios)
                       │ /api/v1/*
┌──────────────────────▼──────────────────────────────────┐
│                  Laravel 12 API                          │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────────┐ │
│  │Controllers│ │ Policies │ │Resources │ │  Events    │ │
│  └──────────┘ └──────────┘ └──────────┘ └────────────┘ │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────────┐ │
│  │ Requests │ │ Services │ │  Traits  │ │Notifications│ │
│  └──────────┘ └──────────┘ └──────────┘ └────────────┘ │
└──────────────────────┬──────────────────────────────────┘
                       │ Eloquent ORM
┌──────────────────────▼──────────────────────────────────┐
│              MariaDB 10.11 Database                       │
│  15+ tables, soft deletes, compound indexes              │
└─────────────────────────────────────────────────────────┘
```

### Frontend → Backend komunikace

```
Vite Dev Server (:5173)
  ├── /login, /register → React pages (public)
  ├── /api/* → proxy → Laravel (:8000)
  └── /* → ProtectedRoute → AppLayout → page
```

### Auth Flow

```
1. User → /login
2. POST /api/v1/login → Sanctum token + user data
3. Token → localStorage + Axios interceptor
4. Každý request → Authorization: Bearer {token}
5. 401 response → interceptor → clear token → redirect /login
```

---

## 🛠 Technologie

| Vrstva            | Technologie        | Verze                       |
|-------------------|--------------------|-----------------------------|
| **Backend**       | Laravel (PHP)      | 12.x (PHP 8.4)              |
| **Frontend**      | React + TypeScript | 19.x + 5.7                  |
| **Styling**       | Tailwind CSS       | 4.0                         |
| **Build**         | Vite               | 6.x                         |
| **Database**      | MariaDB            | 10.11                       |
| **Auth**          | Laravel Sanctum    | SPA tokens                  |
| **PDF**           | DomPDF             | via barryvdh/laravel-dompdf |
| **Charts**        | Recharts           | Bar + Pie charts            |
| **HTTP Client**   | Axios              | s interceptory              |
| **Code Style BE** | Laravel Pint       | PSR-12                      |
| **Code Style FE** | Prettier           | custom config               |
| **CI/CD**         | GitHub Actions     | PHPUnit + Pint              |

---

## 📊 Datový model

```
User (landlord/manager/tenant)
  └── vlastní Property
        ├── má Lease → propojuje s User (tenant)
        │     ├── má Payments (příjmy)
        │     └── má Ratings (hodnocení → Trust Score)
        ├── má Expenses (výdaje)
        ├── má Tickets → má TicketComments + TicketImages
        ├── má Meters → má MeterReadings
        ├── má PropertyImages
        ├── má InventoryItems
        ├── má Documents
        ├── má Notices
        └── má Managers (M:N pivot property_manager)
```

### Hlavní entity

| Model        | Popis               | Klíčové atributy                              |
|--------------|---------------------|-----------------------------------------------|
| **User**     | Uživatel systému    | role, trust_score, phone                      |
| **Property** | Nemovitost          | address, disposition, status, purchase_price  |
| **Lease**    | Nájemní smlouva     | rent_amount, deposit, variable_symbol, status |
| **Payment**  | Platba              | type, amount, due_date, paid_date, status     |
| **Ticket**   | Hlášení závady      | category, priority, status, assigned_to       |
| **Meter**    | Měřič energie       | meter_type, serial_number, location           |
| **Rating**   | Hodnocení nájemníka | category, score (1-5), comment                |
| **Document** | Nahraný soubor      | document_type, name, file_path                |
| **Notice**   | Oznámení nástěnky   | title, content, is_active                     |

---

## 👥 Role a oprávnění

### Landlord (Pronajímatel)

- ✅ Plný přístup ke všem vlastním nemovitostem
- ✅ CRUD: properties, leases, payments, expenses, inventory, documents, notices
- ✅ Hodnocení nájemníků, generování PDF smluv
- ✅ Povýšení/degradace managerů, přiřazení ke properties
- ✅ Dashboard: finance grafy, obsazenost, statistiky
- ✅ CSV import plateb, generování měsíčních plateb

### Manager (Správce)

- ✅ Vidí přiřazené properties + property kde je tenant
- ✅ Řeší tickety (status, komentáře) pro přiřazené properties
- ✅ Loguje meter odečty na přiřazených properties
- ✅ Vytváří tickety pro přiřazené properties
- ✅ Dual dashboard: Manager View + My Tenancy
- ❌ Nevidí finance (expenses, cashflow)
- ❌ Nemůže editovat properties, inventory, documents

### Tenant (Nájemník)

- ✅ Vidí svůj byt, platby, smlouvy (read-only)
- ✅ Vytváří tickety + komentáře s fotkami
- ✅ Vidí management tab (meters, inventory) — read-only
- ✅ Dashboard: moje platby, tickety, trust score
- ❌ Nevidí People, expenses, jiné nájemníky
- ❌ Nemůže editovat žádná data kromě svého profilu

### Matice oprávnění

| Akce                 | Landlord | Manager | Tenant |
|----------------------|:--------:|:-------:|:------:|
| Properties CRUD      |    ✅     |    ❌    |   ❌    |
| View property detail |    ✅     |   ✅*    |   ✅*   |
| Create lease         |    ✅     |    ❌    |   ❌    |
| Create ticket        |    ✅     |   ✅*    |  ✅**   |
| Resolve ticket       |    ✅     |   ✅*    |   ❌    |
| Log meter reading    |    ✅     |   ✅*    |   ❌    |
| View payments        |    ✅     |    ❌    | ✅ own  |
| Mark paid            |    ✅     |    ❌    |   ❌    |
| Upload documents     |    ✅     |    ❌    |   ❌    |
| Rate tenant          |    ✅     |    ❌    |   ❌    |
| View People          |    ✅     |    ✅    |   ❌    |
| Promote/demote       |    ✅     |    ❌    |   ❌    |

\* jen přiřazené properties | \*\* jen s aktivní smlouvou

---

## 🚀 Instalace a spuštění

### Požadavky

- PHP 8.4+ s extensions: mbstring, pdo_mysql, gd
- Composer 2.x
- Node.js 20+ s npm
- MariaDB 10.11+ nebo MySQL 8+

### 1. Klonování

```bash
git clone https://github.com/your-repo/rentflow.git
cd rentflow
```

### 2. Backend

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Uprav `.env`:

```env
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rentflow
DB_USERNAME=root
DB_PASSWORD=
```

```bash
php artisan migrate
php artisan storage:link
php artisan db:seed
php artisan serve
```

### 3. Frontend

```bash
cd frontend
npm install
npm run dev
```

### 4. Otevření

Aplikace běží na **http://localhost:5173**

---

## 🌱 Seed data

Seeder vytvoří kompletní testovací prostředí:

```bash
php artisan migrate:fresh --seed
```

### Přihlašovací údaje (heslo: `password`)

| Role        | Jméno             | Email                |
|-------------|-------------------|----------------------|
| 🏠 Landlord | Jan Novák         | landlord@rentflow.cz |
| 🔧 Manager  | Petr Svoboda      | manager@rentflow.cz  |
| 👤 Tenant   | Marie Dvořáková   | marie@rentflow.cz    |
| 👤 Tenant   | Tomáš Černý       | tomas@rentflow.cz    |
| 👤 Tenant   | Eva Procházková   | eva@rentflow.cz      |
| 👤 Tenant   | Lukáš Veselý      | lukas@rentflow.cz    |
| 👤 Tenant   | Kateřina Kučerová | katerina@rentflow.cz |

### Co seed vytvoří

| Entita     | Počet | Detail                                               |
|------------|-------|------------------------------------------------------|
| Properties | 6     | 4 obsazené, 1 volná, 1 v rekonstrukci                |
| Leases     | 6     | 4 aktivní, 1 ukončená (s ratings), 1 terminovaná     |
| Payments   | ~60   | Rent + utilities + deposits, mix paid/unpaid/overdue |
| Tickets    | 6     | Mix new/in_progress/resolved s komentáři v češtině   |
| Meters     | 14+   | Voda + elektřina + plyn/teplo, 6 měsíců odečtů       |
| Documents  | 8-12  | Pojistky, revize, protokoly                          |
| Notices    | 4     | 3 aktivní, 1 neaktivní                               |
| Images     | 18+   | SVG placeholder fotky per property                   |
| Inventory  | 30+   | Spotřebiče + nábytek s českými názvy                 |
| Ratings    | 4     | 4 kategorie pro ukončenou smlouvu                    |

---

## 📡 API přehled

Všechny endpointy pod prefixem `/api/v1/`. Autentizace přes Sanctum Bearer token.

### Auth

| Method | Endpoint    | Popis                              |
|--------|-------------|------------------------------------|
| POST   | `/register` | Registrace (role: landlord/tenant) |
| POST   | `/login`    | Přihlášení → token                 |
| POST   | `/logout`   | Odhlášení (auth)                   |

### Properties

| Method | Endpoint           | Popis                        |
|--------|--------------------|------------------------------|
| GET    | `/properties`      | Seznam (filtrováno dle role) |
| POST   | `/properties`      | Vytvoření (landlord)         |
| GET    | `/properties/{id}` | Detail                       |
| PUT    | `/properties/{id}` | Editace (landlord)           |
| DELETE | `/properties/{id}` | Soft delete (landlord)       |

### Leases

| Method | Endpoint                    | Popis                                           |
|--------|-----------------------------|-------------------------------------------------|
| GET    | `/leases`                   | Seznam (filtry: status, property_id, tenant_id) |
| POST   | `/leases`                   | Vytvoření (guard: ne renovation)                |
| GET    | `/leases/{id}/generate-pdf` | Download PDF smlouvy                            |

### Payments

| Method | Endpoint                     | Popis                         |
|--------|------------------------------|-------------------------------|
| GET    | `/payments`                  | Seznam (filtry: status, type) |
| PUT    | `/payments/{id}/mark-paid`   | Označit jako zaplaceno        |
| POST   | `/payments/import-csv`       | CSV import z banky            |
| POST   | `/payments/generate-monthly` | Vygenerovat měsíční platby    |

### Tickets

| Method | Endpoint                 | Popis                                       |
|--------|--------------------------|---------------------------------------------|
| GET    | `/tickets`               | Seznam (filtry: status, priority, category) |
| POST   | `/tickets/{id}/comments` | Přidat komentář (+ příloha)                 |
| POST   | `/tickets/{id}/images`   | Přidat fotku k ticketu                      |

### Další

| Method | Endpoint                     | Popis                  |
|--------|------------------------------|------------------------|
| GET    | `/properties/{id}/meters`    | Měřiče nemovitosti     |
| GET    | `/properties/{id}/documents` | Dokumenty              |
| GET    | `/properties/{id}/notices`   | Oznámení               |
| GET    | `/notifications`             | Notifikace uživatele   |
| GET    | `/tenants/{id}/trust-score`  | Trust score detail     |
| GET    | `/health`                    | Health check (veřejný) |

---

## ⚙️ Automatizace

### Scheduled Commands

| Command                           | Frekvence     | Co dělá                                                                 |
|-----------------------------------|---------------|-------------------------------------------------------------------------|
| `rentflow:generate-payments`      | 1. den měsíce | Vytvoří rent + utility platby pro aktivní leases                        |
| `rentflow:check-overdue-payments` | Denně         | unpaid → overdue + notifikace + trust score recalc                      |
| `rentflow:check-expiring-leases`  | Denně         | Notifikace o expirujících + auto-expire prošlých + property → available |

### Spuštění scheduleru

```bash
# Produkce (cron)
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1

# Development (ruční)
php artisan rentflow:generate-payments
php artisan rentflow:check-overdue-payments
php artisan rentflow:check-expiring-leases
```

### Automatické akce

| Trigger                  | Akce                                                  |
|--------------------------|-------------------------------------------------------|
| Vytvoření lease          | Property status → occupied                            |
| Ukončení/terminace lease | Property status → available (pokud žádná jiná active) |
| Mark payment as paid     | Trust score recalc + notifikace                       |
| Payment overdue (5+ dní) | Status → overdue + notifikace tenant                  |
| Lease end_date < today   | Status → ended (auto-expire)                          |

---

## 🧪 Testování

### PHPUnit Feature Tests

```bash
php artisan test
```

**Pokrytí:**

| Test Suite    | Testy    | Aserce                                             |
|---------------|----------|----------------------------------------------------|
| AuthTest      | 12       | Registrace, login, logout, rate limiting, validace |
| PropertyTest  | 15       | CRUD, soft delete, restore, filtry, autorizace     |
| LeaseTest     | 12       | CRUD, PDF generování, status změny                 |
| PaymentTest   | 14       | CRUD, mark paid, CSV import, overdue               |
| TicketTest    | 22       | CRUD, komentáře, manager přiřazení, statusy        |
| DashboardTest | 8        | Stats, charts, trust score                         |
| **Celkem**    | **~116** | **~280**                                           |

### Code Style

```bash
# Backend
vendor/bin/pint

# Frontend
cd frontend && npx prettier --write "src/**/*.{ts,tsx}"
```

---

## 🔄 CI/CD

### GitHub Actions

Soubor: `.github/workflows/tests.yml`

**Triggery:** Push na `main`/`develop`, Pull Request do `main`

**Pipeline:**

```
1. Checkout code
2. Setup PHP 8.4 + extensions
3. Cache Composer dependencies
4. Install dependencies
5. Setup environment (.env + key)
6. Run migrations + tests (MariaDB service container)
7. Run Pint (code style check)
```

| Výsledek | Ikona | Význam                        |
|----------|-------|-------------------------------|
| Success  | ✅     | Všechny testy + code style OK |
| Failure  | ❌     | Test selhání nebo formátování |

---

## 📁 Struktura projektu

```
rentflow/
├── app/
│   ├── Console/Commands/          # Scheduled commands
│   │   ├── CheckExpiringLeases.php
│   │   ├── CheckOverduePayments.php
│   │   └── GenerateMonthlyPayments.php
│   ├── Events/                    # Laravel events
│   ├── Http/
│   │   ├── Controllers/Api/       # 15+ REST controllers
│   │   ├── Middleware/             # RoleMiddleware
│   │   ├── Requests/              # Form Request validace
│   │   └── Resources/             # API Resources (transformace)
│   ├── Models/                    # 14 Eloquent modelů
│   ├── Notifications/             # Email + DB notifikace
│   ├── Policies/                  # 8 authorization policies
│   ├── Services/                  # TrustScoreService, BankImportService
│   └── Traits/                    # Filterable trait
├── database/
│   ├── factories/                 # 13 model factories
│   ├── migrations/                # 15+ migrations
│   └── seeders/                   # DatabaseSeeder (české seed data)
├── frontend/
│   └── src/
│       ├── api/                   # 11 API modules (axios)
│       ├── components/
│       │   ├── layout/            # AppLayout, Sidebar, ProtectedRoute
│       │   └── ui/                # Button, Modal, Badge, Spinner, ...
│       ├── contexts/              # AuthContext (global auth state)
│       ├── hooks/                 # useConfirm (custom dialog)
│       ├── pages/
│       │   ├── auth/              # Login, Register
│       │   ├── dashboard/         # Landlord + Tenant + Manager dashboard
│       │   ├── leases/            # Leases CRUD + PDF + Rating modal
│       │   ├── payments/          # Payments + CSV import + Generate
│       │   ├── people/            # People list + Person detail
│       │   ├── properties/        # Properties + Detail (6 sections)
│       │   ├── settings/          # Profile + Password
│       │   └── tickets/           # Tickets + Comments + Images
│       ├── types/                 # TypeScript interfaces (20+)
│       └── utils/                 # format helpers (currency, date)
├── routes/
│   ├── api.php                    # 60+ API routes
│   └── console.php                # Scheduler config
├── tests/Feature/                 # 116+ PHPUnit tests
└── .github/workflows/tests.yml    # CI pipeline
```

---

## 📝 Licence

Tento projekt byl vytvořen jako bakalářská práce. Všechna práva vyhrazena.
