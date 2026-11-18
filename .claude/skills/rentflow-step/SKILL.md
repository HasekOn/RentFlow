---
name: rentflow-step
description: Zpracování jednoho kroku nebo stránky z docs/REWRITE_PLAN.md – kontrola stavu repa a větve, přečtení kroku a rozhodnutí v plánu, plán ke schválení, provedení jen toho kroku (přes rentflow-backend nebo rentflow-page), checklist čistoty, kontroly, ověření, odškrtnutí v plánu a závěrečné shrnutí se seznamem souborů a návrhem commit zprávy.
argument-hint: "[fáze a krok, např. 2.2 Enumy a role]"
disable-model-invocation: true
---

# Krok z plánu přepisu

Krok: $ARGUMENTS

## Stav repa při spuštění

Větev: !`git branch --show-current`

Pracovní strom:
!`git status --short || true`

Commity oproti v2:
!`git log --oneline v2..HEAD || true`

## Postup

1. **Stav repa.** Nečistý strom nebo jiná větev, než krok předpokládá → zastav se a zeptej se. Nic nestashuj ani
   nepřepínej sám. Krok se dělá ve větvi `v2-<krok>` založené z aktuální `v2` (např. `v2-2.2-enumy`);
   `v2/<krok>` git nedovolí. Novou větev založ, jen když jsi na čisté `v2`.
2. **Kontext.** Přečti v `docs/REWRITE_PLAN.md` celý krok, poznámky pod ním, „Rozhodnutí z auditu“ a úvod fáze
   (platí pro všechny kroky fáze). Čísla nálezů `#` dohledej v `docs/REVIEW.md`, ve fázi 5 sekci stránky
   v `docs/FEATURES.md`. Pravidla kódu jsou v `CLAUDE.md`.
3. **Plán ke schválení** (plan mode): soubory, testy, rizika, co krok vědomě nedělá. Kód až po schválení.
   Dotaz na rozpor kódu s maticí v README nebo s rozhodnutím v plánu patří sem, ne do implementace.
4. **Jen tento krok.** Postup podle typu:
   - fáze 2 (backend, policy, scope, Action, migrace): skill `rentflow-backend`,
   - fáze 5 (stránka): skill `rentflow-page`,
   - ostatní fáze: krok v plánu + vzory z repa a z JaTime (`C:\dev\jatime`).

   Vědomá rozhodnutí z plánu nehlas jako chyby. Odchylku od plánu napiš a zdůvodni. Nic navíc mimo krok – nápad
   na později zapiš do shrnutí jako návrh.
5. **Čistota.** Před předáním projdi diff podle checklistu „Čistota kódu“ v `.claude/skills/rentflow-review/SKILL.md`
   (DRY, enumy, vzory repa, velikost, Blade a CSS, testy, texty) a zjevné věci oprav.
6. **Kontroly:** skill `rentflow-checks` – v argumentu předej, co se změnilo (soubory, oblast). Selhání oprav
   v hlavní konverzaci a kontroly zopakuj.
7. **Ověření chování**, které testy nepokryjí:
   - API (fáze 2): `MODE=api bash .claude/skills/rentflow-browser/smoke.sh <účet> password /cesta …` pro všechny
     role ze seedu,
   - UI (od fáze 3): skill `rentflow-browser` (stránky a role, záloha a obnova DB).
8. **Plán.** Krok v `docs/REWRITE_PLAN.md` odškrtni (`- [x]`) a pod něj napiš krátkou poznámku: datum, co se
   oproti plánu změnilo, nové návrhy. Ve fázi 5 odškrtni i funkce v `docs/FEATURES.md`.
9. **Necommituj, nepushuj, nemerguj, nezakládej PR.** Commit dělá uživatel.

## Výstup na konci

1. Stručné shrnutí po krocích (co přibylo, odchylky od plánu a proč, otevřené otázky).
2. Seznam změněných souborů **po logických krocích** (aby šly commitnout odděleně).
3. Návrh commit zprávy: česky, jeden řádek + pár odrážek (u víc logických kroků pro každý zvlášť).
4. Výsledky kontrol (řádek na kontrolu) a ověření (tabulka scénářů, potvrzení obnovy DB, pokud se zálohovalo).
5. Změna oprávnění (fáze 2): tabulka role × akce před a po.
