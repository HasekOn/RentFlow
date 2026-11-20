# RentFlow – inventář funkcí React frontendu (fáze 1)

Předloha pro fázi 5. Při převodu stránky odškrtni každou funkci (`- [x]`), nebo napiš, proč se vědomě vynechává.
Zdroj: `frontend/src` (pages, components, api, contexts) ke commitu `9c43509`. Od kroku 2.1 je React jen na větvi
`main` (`git show main:frontend/src/…`), spuštění přes `git worktree` popisuje skill `rentflow-page`.

**Role:**

| Zkratka | Role |
| --- | --- |
| **P** | pronajímatel (`landlord`) |
| **S** | správce (`manager`) |
| **N** | nájemník (`tenant`) |
| **S(N)** | správce, který je zároveň nájemníkem jinde (stránky se mu přizpůsobují) |

**API:** cesty jsou relativní k `/api/v1`.

**Značky:**

| Značka | Význam |
| --- | --- |
| ⚠ | Chování v Reactu je chybné nebo v rozporu s maticí v README. **Nekopírovat**, správně podle `docs/REVIEW.md`. |
| ✋ | Funkci React schovává jen v UI, API ji ale pouští. V Livewire to musí hlídat policy. |

UI Reactu je většinou anglicky (notifikace česky). Nové UI je celé česky.

---

## 0. Společné (layout, auth, vzory)

### Autentizace a layout

- [ ] Chráněné stránky jen pro přihlášené. Nepřihlášený jde na `/login`, při načítání se ukáže spinner. — **P S N** — `GET /user`
- [ ] Při 401 z API se smaže token a uživatel jde na `/login` (kromě volání login a registrace). — **P S N**
- [ ] Neznámá URL přesměruje na `/` (v Livewire místo toho stránka 404). — **P S N**
- [ ] Sidebar s položkami podle role: Overview, Portfolio, Leases, Payments, Helpdesk, Documents (všichni), People (**P S**), Settings (všichni). — **P S N**
- [ ] Mobilní menu: hamburger otevře sidebar s overlayem, klik na položku nebo overlay ho zavře. — **P S N**
- [ ] Uživatelská část v sidebaru: avatar s iniciálou, jméno, e-mail, odhlášení. — **P S N** — `POST /logout`

### Notifikace (zvoneček v sidebaru)

- [ ] Zvoneček s počtem nepřečtených (max. „9+“). — **P S N** — `GET /notifications` (posledních 20)
- [ ] Polling každých 60 s (v Livewire `wire:poll.60s`). — **P S N**
- [ ] Dropdown: ikona podle typu, nadpis, text, relativní čas („5m ago“, „2h ago“, „3d ago“, pak datum) a modrá tečka u nepřečtené. — **P S N**
- [ ] Klik na notifikaci ji označí jako přečtenou a naviguje na odkaz (smlouva, tiket, platby). — **P S N** — `PUT /notifications/{id}/read`
- [ ] „Označit vše jako přečtené“ (React volá `read` pro každou zvlášť, v Livewire jedna akce). — **P S N** — `PUT /notifications/{id}/read` × n
- [ ] Prázdný stav „Zatím žádná oznámení“. — **P S N**
- [ ] Zavření dropdownu klikem mimo. — **P S N**
- [ ] Typy a texty: `tenant_invitation`, `lease_created`, `lease_expiring` (za X dní), `ticket_created`, `ticket_resolved`, `payment_marked_paid`, `payment_overdue` (X dní po splatnosti, částka). ⚠ Klíče `ticket_title` a `tenant_name` backend neposílá (REVIEW #47). — **P S N**

### Vzory UI, které se opakují

- [ ] Potvrzovací dialog (`useConfirm`) před každou destruktivní akcí (smazat, ukončit, označit zaplaceno, generovat). V `<x-modal>` se potvrzuje druhým kliknutím (CLAUDE.md).
- [ ] Prázdné stavy (`EmptyState`) s textem podle filtru („No … found“ a nabídka vytvoření jen bez filtru).
- [ ] Stránkování (`Pagination`, meta z Laravel paginatoru: 15 nebo 20 na stránku).
- [ ] Seznamy: na mobilu karty, na desktopu tabulka.
- [ ] Barevné odznaky stavů (`Badge`): nemovitost, smlouva, platba, tiket (stav i priorita), inventář (stav).
- [ ] Trust Score: odznak a progress bar (≥ 70 zelená, ≥ 40 žlutá, jinak červená).
- [ ] Formát částek „12 500 Kč“ (bez haléřů) a dat (React `en-US` „Oct 7, 2026“, nově česky „7. 10. 2026“).
- [ ] Chyby validace pod poli z odpovědi 422 (`errors`), obecná chyba nahoře ve formuláři.

---

## 1. Přihlášení — `/login` (`pages/auth/LoginPage.tsx`)

- [ ] Formulář e-mail a heslo, tlačítko se stavem „Signing in…“. — veřejné — `POST /login`
- [ ] Chybová hláška z API („Invalid credentials“, 429). — veřejné
- [ ] Po přihlášení přesměrování na `/`. — veřejné
- [ ] Odkaz na registraci. — veřejné
- [ ] (Nové, fáze 4: zapomenuté heslo, rate limiter `login`.)

## 2. Registrace — `/register` (`pages/auth/RegisterPage.tsx`)

- [ ] Volba role dlaždicemi „Manage properties / I'm a landlord“ (výchozí) a „Rent a place / I'm a tenant“. — veřejné — `POST /register`
- [ ] Pole jméno, e-mail, heslo a potvrzení hesla, chyby u polí. — veřejné
- [ ] Po registraci automatické přihlášení a přesměrování na `/`. — veřejné
- [ ] Odkaz na přihlášení. — veřejné
- [ ] ⚠ API přijímá i `role=manager`, nové UI ji nesmí nabízet a backend ji nesmí přijmout (REVIEW #21).

## 3. Přehled — `/` (`pages/dashboard/DashboardPage.tsx`)

### Pronajímatel

- [ ] 8 karet:
  - celkem nemovitostí (podtitul obsazené a volné),
  - příjem za měsíc,
  - výdaje za měsíc,
  - cashflow (zelená nebo červená),
  - aktivní smlouvy (podtitul „X expiring soon“, žlutá při > 0),
  - otevřené tikety,
  - platby po splatnosti,
  - nemovitosti v rekonstrukci.

  — **P** — `GET /dashboard/stats`
- [ ] Sloupcový graf příjmy vs. výdaje za 12 měsíců. — **P** — `GET /dashboard/finance-chart`
- [ ] Prstencový graf obsazenosti (obsazené, volné, rekonstrukce). — **P** — `GET /dashboard/occupancy-chart`
- [ ] ⚠ Poslední den měsíce chybí v součtech (REVIEW #28).

### Nájemník

- [ ] 4 karty: Můj byt (adresa), měsíční nájem, nezaplacené platby (červená nebo zelená), otevřené tikety. — **N** — `GET /leases?status=active`, `GET /payments?sort=-due_date`, `GET /tickets?sort=-created_at`
- [ ] Posledních 5 plateb (částka, typ, splatnost, stav) a odkaz „View all“ na platby. — **N**
- [ ] Posledních 5 tiketů (název, priorita, stav), klik otevře detail, „View all“. — **N**
- [ ] Karta „My Lease“: nemovitost, začátek, konec (nebo „Indefinite“), variabilní symbol. — **N**
- [ ] Karta „My Trust Score“ s progress barem a vysvětlivkou. — **N** — z `GET /user`

### Správce (dvě záložky)

- [ ] Přepínač „Manager View“ / „My Tenancy“. — **S**
- [ ] Manager View: karty spravované nemovitosti (podtitul obsazené), otevřené tikety, nové tikety, vyřešené. — **S** — `GET /properties`, `GET /tickets`
- [ ] Manager View: seznam spravovaných nemovitostí bez té, kde bydlí (adresa, stav, nájemník), klik otevře detail. — **S**
- [ ] Manager View: poslední tikety s prioritou a stavem, klik otevře detail. — **S**
- [ ] My Tenancy: stejné jako přehled nájemníka (byt, nájem, nezaplacené, trust score a posledních 5 plateb), bez smlouvy prázdný stav. — **S(N)** — `GET /leases?status=active`, `GET /payments`
- [ ] Správce finance nevidí (dashboard bez `stats` a `finance-chart`). ✋ API mu je dnes vrací (REVIEW #17).

## 4. Nemovitosti — `/properties` (`pages/properties/PropertiesPage.tsx`, `PropertyFormModal.tsx`)

- [ ] Seznam nemovitostí podle role: pronajímatel své, správce spravované a tu, kde bydlí, nájemník tu s aktivní smlouvou. — **P S N** — `GET /properties`
- [ ] Hledání (adresa, město, popis). — **P S N** — `?search=`
- [ ] Filtr stavu (volná, obsazená, rekonstrukce). — **P S N** — `?status=`
- [ ] Řazení od nejnovějších (`sort=-created_at`). API umí i `address`, `city`, `size`, `status`, `purchase_price`, ale UI je nenabízí. — **P S N**
- [ ] Stránkování (15), změna filtru vrátí na stranu 1. — **P S N**
- [ ] Sloupce: nemovitost (fotka nebo ikona, adresa, město), dispozice, plocha, stav, nájem (z aktivní smlouvy), akce. — **P S N**
- [ ] Tlačítko „+ Add Property“ v hlavičce i v prázdném stavu. — **P** — `POST /properties`
- [ ] Formulář: adresa\*, město, PSČ, plocha m², dispozice, patro, stav, pořizovací cena, popis. ⚠ Plocha a dispozice prázdné = 500 (REVIEW #29). — **P**
- [ ] Smazání s potvrzením (soft delete). ⚠ Nemovitost s aktivní smlouvou (REVIEW #35). — **P** — `DELETE /properties/{id}`
- [ ] Odkaz „View“ na detail. — **P S N**
- [ ] (Obnovení smazané nemovitosti: API `PUT /properties/{id}/restore` existuje, UI ho nemá.)

## 5. Detail nemovitosti — `/properties/:id` (`pages/properties/PropertyDetailPage.tsx` a podkomponenty)

V Reactu dvě záložky: **Overview** a **Management** (pro P) nebo **Details** (pro S a N). Plán fáze 5 dělí detail na
samostatné komponenty, viz mapování URL na konci.

### 5.1 Hlavička a přehled

- [ ] Načtení detailu, při chybě nebo 403 návrat na seznam. — **P S N** — `GET /properties/{id}`
- [ ] Zpět na seznam, tlačítko „Edit property“ (stejný formulář jako vytvoření). — **P** — `PUT /properties/{id}`
- [ ] Galerie: hlavní fotka a náhledy, „+X“ pro další, lightbox s předchozí a další a mazáním fotky (**P**). — **P S N** — `DELETE /property-images/{id}`
- [ ] Nahrání fotky: náhled, typ (marketing, závada, sken dokumentu), popis a `accept` jpg, png nebo webp. Prázdná galerie ukáže „Add First Photo“. — **P** — `POST /properties/{id}/images`
- [ ] Karta ceny a stavu: nájem aktivní smlouvy (nebo „No active lease“), dispozice, plocha, adresa a odznak stavu. — **P S N**
- [ ] Popis nemovitosti (pokud je). — **P S N**
- [ ] Detaily: plocha, dispozice, patro, město a PSČ. Pořizovací cena jen pro **P**. ✋ API ji vrací všem (REVIEW #20). — **P S N**
- [ ] Obyvatel: avatar a jméno nájemníka aktivní smlouvy. — **P S N**

### 5.2 Boční panel podle role

- [ ] Pronajímatel: stav, aktivní nájem, zálohy, počet smluv, konec smlouvy za X dní (nebo „Expired“), počet měřičů, počet fotek a pořizovací cena. — **P**
- [ ] Nájemník: stav smlouvy („Active“ nebo „No lease“), nájem, zálohy, celkem měsíčně, kauce, začátek, konec s počtem dní a VS. — **N**
- [ ] Správce bydlící v nemovitosti: stejné jako nájemník a k tomu počet měřičů. — **S(N)**
- [ ] Správce spravující nemovitost: stav, nájemník, otevřené tikety a počet měřičů. — **S**
- [ ] Správci nemovitosti: seznam přiřazených (jméno a e-mail) s odebráním, výběr ze správců a „Assign“. Chyby přes `alert()`, v Livewire toast. — **P** — `GET /properties/{id}/managers`, `GET /users?role=manager`, `POST /properties/{id}/assign-manager`, `DELETE /properties/{id}/remove-manager/{user}`

### 5.3 Správa a detaily: oznámení

- [ ] Seznam oznámení (název, text, autor, datum, odznak „Inactive“). — **P S N** — `GET /properties/{id}/notices`
- [ ] „+ Add“ s formulářem název a obsah. — **P** — `POST /properties/{id}/notices`
- [ ] Aktivovat nebo deaktivovat oznámení. — **P** — `PUT /notices/{id}` (`is_active`)
- [ ] Smazání s potvrzením. — **P** — `DELETE /notices/{id}`
- [ ] Neaktivní oznámení jsou zašedlá a vidí je všichni, i nájemník. Rozhodnout, jestli nájemníkovi ukazovat jen aktivní.

### 5.4 Správa a detaily: tikety nemovitosti

- [ ] Seznam tiketů nemovitosti (#id, název, priorita, nahlásil, stav), klik otevře detail, „View All“ vede na `/tickets`. — **P S N** — `GET /tickets?property_id={id}&sort=-created_at`
- [ ] Prázdný stav „No tickets for this property“. — **P S N**

### 5.5 Správa: náklady (jen pronajímatel)

- [ ] Tabulka nákladů (datum, typ, popis, částka, akce), na mobilu karty. — **P** — `GET /expenses?property_id={id}&sort=-expense_date`. ✋ API je dnes vrací i nájemníkovi (REVIEW #16).
- [ ] Přidat a upravit náklad: typ, částka, datum, popis. ⚠ Typ „Utility“ API odmítne (REVIEW #44). — **P** — `POST /expenses`, `PUT /expenses/{id}`
- [ ] Smazání s potvrzením. — **P** — `DELETE /expenses/{id}`

### 5.6 Správa a detaily: inventář

- [ ] Seznam položek (název, kategorie, stav jako odznak, poznámka). Datum a cenu nákupu seznam neukazuje, jsou jen ve formuláři. — **P S N** (S a N jen čtení) — `GET /properties/{id}/inventory`
- [ ] Přidat a upravit položku: název\*, kategorie, stav (nová, dobrá, opotřebená, špatná, rozbitá), datum a cena nákupu, poznámka. — **P** — `POST /properties/{id}/inventory`, `PUT /inventory/{id}`
- [ ] Smazání s potvrzením. — **P** — `DELETE /inventory/{id}`

### 5.7 Správa a detaily: dokumenty

- [ ] Seznam dokumentů podle viditelnosti (ikona podle přípony, název, typ, kdo nahrál, datum). — **P S N** — `GET /properties/{id}/documents`
- [ ] Stažení se správnou příponou (blob). — **P S N** — `GET /documents/{id}/download`
- [ ] Nahrání: soubor (`accept` pdf, doc(x), xls(x), jpg, png, webp), název\* a typ (smlouva, pojistka, revize, energetický štítek, daňový doklad, předávací protokol, faktura, ostatní). Viditelnost: Auto podle typu, jen pronajímatel, + nájemník, + správce, všichni, s nápovědou. — **P** — `POST /properties/{id}/documents`
- [ ] Výchozí viditelnost pro „Auto“:
  - smlouva a protokol: + nájemník,
  - pojistka, daňový doklad, faktura a ostatní: jen pronajímatel,
  - revize a energetický štítek: + správce.

  — **P**
- [ ] Smazání s potvrzením. — **P** — `DELETE /documents/{id}`

### 5.8 Správa a detaily: měřiče a odečty

- [ ] Karty měřičů: ikona a jednotka podle typu (voda m³, elektřina kWh, plyn m³, teplo GJ), poslední odečet a datum, sériové číslo a umístění. — **P S N** — `GET /properties/{id}/meters`
- [ ] Přidat měřič: typ, sériové číslo, umístění. — **P** — `POST /properties/{id}/meters`
- [ ] Upravit měřič (sériové číslo, umístění) inline. — **P** — `PUT /meters/{id}`
- [ ] Smazání měřiče s potvrzením. — **P** — `DELETE /meters/{id}`
- [ ] Zapsat odečet (hodnota a datum, výchozí dnes), chyba „nesmí být menší než předchozí“. — **P S** (S jen přiřazený) — `POST /meters/{id}/readings`
- [ ] Prázdný stav „No meters“. — **P S N**
- [ ] (Historie odečtů: API `GET /meters/{id}/readings` existuje, UI ukazuje jen poslední. Rozhodnout, jestli přidat.)

## 6. Smlouvy — `/leases` (`pages/leases/LeasesPage.tsx`, `CreateLeaseModal.tsx`)

- [ ] Seznam smluv: pronajímatel smlouvy svých nemovitostí, správce a nájemník své jako nájemník (včetně smazaných). — **P S N** — `GET /leases`
- [ ] Filtr stavu (aktivní, ukončená, vypovězená) a stránkování (15). — **P S N** — `?status=&page=`
- [ ] Sloupce: nemovitost (fotka, adresa), nájemník (jen **P**), období, nájem, stav, vyprší (za X dní, žlutě do 30 dnů, „Expired“). — **P S N**
- [ ] Nová smlouva: nemovitost, nájemník (nájemníci i správci), začátek\*, konec, nájem\*, kauce, zálohy a VS (unikátní). — **P** — `GET /properties`, `GET /users?role=tenant`, `GET /users?role=manager`, `POST /leases`. ⚠ Výběr nájemníka ze všech uživatelů systému (REVIEW #4) a nemovitostí jen z první stránky (REVIEW #45).
- [ ] Chyby: nemovitost v rekonstrukci a aktivní smlouva už existuje (422). — **P**
- [ ] Odkaz „View“ na detail. — **P S N**

## 7. Detail smlouvy — `/leases/:id` (`pages/leases/LeaseDetailPage.tsx`, `RateTenantModal.tsx`)

- [ ] Načtení detailu (i smazané smlouvy), při chybě návrat na seznam. — **P S N** — `GET /leases/{id}`
- [ ] Přehled: adresa, stav a měsíční nájem. — **P S N**
- [ ] Detaily: nemovitost, nájemník, začátek, konec, kauce, zálohy, VS, vytvořeno. — **P S N**
- [ ] Rychlé info: celkem měsíčně (nájem + zálohy) a zbývá dní (nebo „Expired“). — **P S N**
- [ ] Karta nájemníka: jméno, e-mail, telefon, trust score s barem. — **P S N**
- [ ] Platby smlouvy (splatnost, částka, stav, datum zaplacení), na desktopu tabulka a na mobilu karty. — **P S N** (✋ S ne podle matice, REVIEW #17)
- [ ] Označit platbu jako zaplacenou s potvrzením. — **P** — `PUT /payments/{id}/mark-paid`
- [ ] Stáhnout PDF smlouvy. — **P S N** — `GET /leases/{id}/generate-pdf`. ⚠ Ukládá se veřejně (REVIEW #15).
- [ ] Upravit smlouvu (jen aktivní): nájem\*, kauce, zálohy, VS, konec. ⚠ Konec před začátkem projde (REVIEW #30). — **P** — `PUT /leases/{id}`
- [ ] Ukončit smlouvu („End Lease“) a vypovědět („Terminate“) s potvrzením. Nemovitost se uvolní, když nemá jinou aktivní smlouvu. — **P** — `PUT /leases/{id}` (`status`)
- [ ] Ohodnotit nájemníka (jen ukončená nebo vypovězená smlouva): 4 kategorie (stav bytu, komunikace, dodržování pravidel, celkově), hvězdičky 1–5 a komentář. Už hodnocené kategorie se skryjí, lze jich odeslat víc najednou. — **P** — `POST /leases/{id}/ratings` × n
- [ ] Smazat smlouvu s potvrzením a návratem na seznam. — **P** — `DELETE /leases/{id}`

## 8. Platby — `/payments` (`pages/payments/PaymentsPage.tsx`, `CreatePaymentModal.tsx`)

- [ ] Seznam plateb: pronajímatel platby svých smluv, správce a nájemník vlastní. — **P S N** — `GET /payments?sort=-due_date`
- [ ] Filtry stavu (zaplaceno, nezaplaceno, po splatnosti) a typu (nájem, zálohy, kauce, ostatní), stránkování (20). — **P S N**
- [ ] Sloupce: nájemník a nemovitost, typ, částka, splatnost, zaplaceno, stav (+ dní po splatnosti). — **P S N**
- [ ] Rychlé statistiky „X unpaid / Y overdue“. ⚠ Počítá jen aktuální stránku, v Livewire počítat v DB. — **P S N**
- [ ] Označit zaplaceno s potvrzením (jen nezaplacené). — **P** — `PUT /payments/{id}/mark-paid`
- [ ] Smazat platbu s potvrzením (jen nezaplacené). — **P** — `DELETE /payments/{id}`
- [ ] Přidat platbu: smlouva (aktivní smlouvy, výběr předvyplní částku a VS), typ, částka\*, splatnost\*, datum zaplacení (vyplněné = rovnou zaplacená), VS, poznámka. — **P** — `GET /leases?status=active`, `POST /payments`
- [ ] Generovat platby za aktuální měsíc s potvrzením a výsledkem „Generated X payments“ (opakované spuštění nic nezdvojí). — **P** — `POST /payments/generate-monthly`
- [ ] Import CSV z banky: výběr souboru a výsledek (řádků, spárováno, už zaplaceno, nespárováno). Chybový panel se dá zavřít. — **P** — `POST /payments/import-csv`
- [ ] Stáhnout vzorový CSV (`variable_symbol;amount;date;note`, BOM, středníky, datum `dd.mm.yyyy`). — **P**
- [ ] (Úprava platby: API `PUT /payments/{id}` existuje, UI ji nemá.)

## 9. Tikety — `/tickets` (`pages/tickets/TicketsPage.tsx`, `CreateTicketModal.tsx`)

- [ ] Seznam tiketů podle role: pronajímatel tikety svých nemovitostí, správce spravovaných a přiřazených, nájemník svoje a tikety svého bytu. — **P S N** — `GET /tickets`
- [ ] Hledání (název, popis), filtr stavu (nový, řeší se, vyřešený, zamítnutý) a priority (nízká, střední, vysoká, urgentní), stránkování (15). — **P S N**
- [ ] Sloupce: tiket (název a nemovitost), počet dní od založení, priorita, stav, nahlásil, akce. — **P S N**
- [ ] „+ Add Ticket“: pronajímatel a správce vždy, nájemník jen s aktivní smlouvou. — **P S N** — `GET /leases?status=active` (kontrola u N)
- [ ] Formulář: nemovitost, název\*, popis\* (min. 10 znaků), kategorie (instalatérství, elektro, topení, stavební, spotřebič, ostatní) a priorita. — **P S N** — `GET /properties`, `POST /tickets`. ⚠ API pustí tiket na cizí nemovitost (REVIEW #11) a správce nezaloží tiket ve svém bytě (REVIEW #24).

## 10. Detail tiketu — `/tickets/:id` (`pages/tickets/TicketDetailPage.tsx`)

- [ ] Načtení tiketu a komentářů, při chybě návrat na seznam. — **P S N** — `GET /tickets/{id}`, `GET /tickets/{id}/comments`
- [ ] Info: název, popis, stav, priorita, kategorie, nemovitost, nahlásil, datum. — **P S N**
- [ ] Fotky tiketu: mřížka, lightbox s předchozí a další. Mazání má uploader nebo **P**. — **P S N** — `DELETE /tickets/{id}/images/{image}`
- [ ] Přidat fotku (jen dokud není vyřešený nebo zamítnutý; jpg, png, webp). — **P S N** — `POST /tickets/{id}/images`
- [ ] Komentáře: avatar a jméno autora, datum, text a příloha (náhled a lightbox). Vlastní komentář jde zarovnat odlišně a smazat s potvrzením. — **P S N** — `DELETE /tickets/{id}/comments/{comment}`
- [ ] Nový komentář s volitelnou fotkou (náhled a odebrání). — **P S N** — `POST /tickets/{id}/comments` (multipart)
- [ ] Boční panel: přiřazeno (nebo „Unassigned“), vyřešeno kdy a doba řešení (v hodinách). — **P S N**
- [ ] Akce podle stavu (ne u vyřešeného nebo zamítnutého): „Mark In Progress“, „Resolve“ a „Reject“ s potvrzením. Při vyřešení odchází notifikace autorovi. — **P S** — `PUT /tickets/{id}` (`status`)
- [ ] Upravit tiket: priorita, kategorie a přiřazení správci. — **P S** — `GET /users?role=manager`, `PUT /tickets/{id}`. ⚠ Přiřadit jde kohokoli (REVIEW #23).
- [ ] Upravit název a popis autorem ve stavu „new“. ⚠ API vrací 403 (REVIEW #46), rozhodnout. — **N**
- [ ] Smazat tiket s potvrzením. — **P** — `DELETE /tickets/{id}`

## 11. Lidé — `/people` (`pages/people/PeoplePage.tsx`)

- [ ] Pronajímatel: seznam nájemníků a správců (avatar, jméno, e-mail, telefon, trust score), klik otevře detail. — **P** — `GET /users?role=tenant`, `GET /users?role=manager`. ⚠ Dnes všichni uživatelé systému (REVIEW #4), nově jen „moji“.
- [ ] Správce: „Managed Tenants“ (nájemníci aktivních smluv spravovaných nemovitostí bez jeho vlastní) a „Other Managers“ (jen pokud nějací jsou), bez prokliku. — **S** — `GET /properties`, `GET /users?role=manager`, `GET /users?role=tenant`
- [ ] Hledání podle jména a e-mailu (na klientovi). — **P S**
- [ ] Prázdné stavy „No tenants found“, „No managed tenants“ a „No managers yet“. — **P S**
- [ ] Nájemník stránku nevidí (není v menu). ✋ API `/users` mu odpoví. — **N**

## 12. Detail osoby — `/people/:id` (`pages/people/PersonDetailPage.tsx`)

- [ ] Přístup:
  - pronajímatel: kohokoli,
  - správce: kohokoli,
  - kdokoli: sám sebe („My Profile“),
  - nájemník: jinak přesměrování na `/`.

  — **P S N** — `GET /users` (hledá se v seznamu)
- [ ] Profil: avatar, jméno, role a trust score (u nájemníka nebo správce). — **P S N**
- [ ] Osobní údaje: jméno, e-mail, telefon, role, členem od. — **P S N**
- [ ] Historie smluv (nemovitost, období, nájem, stav), klik otevře smlouvu. — **P S N** — `GET /leases?tenant_id={id}`
- [ ] Rozpad trust score: celkem plateb, včas, poměr a průměrné hodnocení. — **P S N** — `GET /tenants/{id}/trust-score`
- [ ] Souhrn hodnocení po kategoriích (průměr) a dialog s detailem (celkový průměr, kategorie, komentáře a kdo hodnotil). Bez hodnocení se ukáže prázdný stav. — **P S N** — `GET /users/{id}/ratings`
- [ ] Rychlé akce: „Edit Profile“ (vlastní profil, vede na `/settings`), „View Leases“, „View Payments“. — **P S N**
- [ ] „Promote to Manager“ (u nájemníka) a „Demote to Tenant“ (u správce) s potvrzením. — **P** — `POST /users/{id}/promote-manager`, `POST /users/{id}/demote-manager`. ⚠ Dnes u kohokoli v systému (REVIEW #14).

## 13. Dokumenty — `/documents` (`pages/documents/DocumentsPage.tsx`)

- [ ] Výběr nemovitosti (dlaždice z viditelných nemovitostí, první vybraná automaticky). — **P S N** — `GET /properties`
- [ ] Seznam dokumentů vybrané nemovitosti (ikona, název, typ, nahrál, datum). — **P S N** — `GET /properties/{id}/documents`
- [ ] Stažení. — **P S N** — `GET /documents/{id}/download`
- [ ] Nahrání (stejný modal jako v detailu nemovitosti, 5.7). — **P** — `POST /properties/{id}/documents`
- [ ] Smazání s potvrzením. — **P** — `DELETE /documents/{id}`
- [ ] Prázdný stav „No documents“ (pro **P** s tlačítkem „Upload File“). — **P S N**

## 14. Nastavení — `/settings` (`pages/settings/SettingsPage.tsx`)

- [ ] Záložky „Profile“ a „Password“. — **P S N**
- [ ] Profil: avatar s iniciálou, jméno\*, e-mail\* a telefon, hláška „Saved successfully!“. Aktualizuje se i jméno v sidebaru. — **P S N** — `PUT /profile`
- [ ] Heslo: současné\*, nové\* (min. 8) a potvrzení, chyba „current password is incorrect“, hláška „Password changed!“. Po změně se formulář vyčistí. — **P S N** — `PUT /profile/password`
- [ ] (Nové, fáze 4: změna hesla odhlásí ostatní zařízení.)

---

## API, které React nevolá

Jen pro rozhodnutí ve fázi 6 (ponechat API, nebo odstranit):

| Endpoint | Poznámka |
| --- | --- |
| `GET /payments/{id}`, `PUT /payments/{id}` | Detail a úprava platby nejsou v UI. |
| `PUT /properties/{id}/restore` | Obnovení smazané nemovitosti není v UI. |
| `GET /properties/{id}/images` | Fotky chodí v detailu nemovitosti. |
| `GET /meters/{id}`, `GET /meters/{id}/readings`, `DELETE /meters/{id}/readings/{reading}` | Historie a mazání odečtů nejsou v UI. |
| `GET /expenses/{id}`, `GET /inventory/{item}` | Detail se nepoužívá. |
| `GET /leases/{id}/ratings`, `DELETE /ratings/{id}` | Hodnocení se čtou přes `/users/{id}/ratings`, mazání není v UI. |
| `GET /tickets/{id}/images` | Fotky chodí v detailu tiketu. |
| `GET /profile` | Používá se `/user`. |
| `GET /health` | Monitoring. |

---

## Mapování React route → Livewire URL

| React route | Komponenta (React) | Navržená URL (Livewire) | Komponenta (návrh) | Role |
| --- | --- | --- | --- | --- |
| `/login` | `LoginPage` | `/login` (Laravel standard, rozhodnutí ve fázi 2) | `Auth\Login` (nebo controller jako JaTime) | host |
| `/register` | `RegisterPage` | `/registrace` | `Auth\Register` | host |
| — (nové) | — | `/zapomenute-heslo`, `/obnova-hesla/{token}` | fáze 4 | host |
| — (`POST /logout`) | Sidebar | `POST /logout` (Laravel standard) | controller | P S N |
| `/` | `DashboardPage` (3 varianty) | `/` | `Dashboard` (+ části podle role) | P S N |
| `/properties` | `PropertiesPage` | `/nemovitosti` | `Properties\Index` (+ modal formuláře) | P S N |
| `/properties/:id` (Overview) | `PropertyDetailPage` | `/nemovitosti/{property}` | `Properties\Show` (přehled, fotky, boční panel, správci) | P S N |
| `/properties/:id` (Management, sekce měřiče) | `MeterCard`, `CreateMeterModal` | `/nemovitosti/{property}/merice` | `Properties\Meters` | P S N |
| `/properties/:id` (sekce inventář) | `InventoryModal` | `/nemovitosti/{property}/inventar` | `Properties\Inventory` | P S N |
| `/properties/:id` (sekce náklady) | `ExpenseModal` | `/nemovitosti/{property}/naklady` | `Properties\Expenses` | P |
| `/properties/:id` (sekce dokumenty) | `UploadDocumentModal` | `/nemovitosti/{property}/dokumenty` | `Properties\Documents` | P S N |
| `/properties/:id` (sekce oznámení) | `CreateNoticeModal` | `/nemovitosti/{property}/oznameni` | `Properties\Notices` | P S N |
| `/properties/:id` (karta správců) | `ManagerAssignment` | `/nemovitosti/{property}/spravci` | `Properties\Managers` | P |
| `/properties/:id` (sekce tikety) | — | odkaz na `/tikety?nemovitost={property}` | `Tickets\Index` s filtrem | P S N |
| `/leases` | `LeasesPage`, `CreateLeaseModal` | `/smlouvy` | `Leases\Index` | P S N |
| `/leases/:id` | `LeaseDetailPage`, `RateTenantModal` | `/smlouvy/{lease}` (PDF: `/smlouvy/{lease}/pdf`) | `Leases\Show` | P S N |
| `/payments` | `PaymentsPage`, `CreatePaymentModal` | `/platby` (vzor CSV: `/platby/vzor.csv`) | `Payments\Index` | P S N |
| `/tickets` | `TicketsPage`, `CreateTicketModal` | `/tikety` | `Tickets\Index` | P S N |
| `/tickets/:id` | `TicketDetailPage` | `/tikety/{ticket}` | `Tickets\Show` | P S N |
| `/people` | `PeoplePage` | `/lide` | `People\Index` | P S |
| `/people/:id` | `PersonDetailPage` | `/lide/{user}` | `People\Show` | P S (N jen sám sebe) |
| `/documents` | `DocumentsPage` | `/dokumenty` (stažení: `/dokumenty/{document}/stahnout`) | `Documents\Index` | P S N |
| `/settings` | `SettingsPage` | `/nastaveni` | `Settings` | P S N |
| zvoneček (Sidebar) | `Sidebar` | — (součást layoutu) | `Notifications\Bell` | P S N |
| `*` → `/` | — | stránka 404 | `errors/404` | — |

**Pozn. k záložkám detailu nemovitosti:** samostatné URL (`/merice`, `/inventar` …) jdou sdílet a každá
komponenta má vlastní autorizaci a testy. Jde to i přes `?zalozka=` na jedné stránce, ale pak se hůř testuje
autorizace a počet dotazů na záložku. Rozhodnout v první stránce fáze 5.
