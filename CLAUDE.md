# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Architektura

Web **farnost Ostrov** je převážně statický – holé HTML soubory + jeden sdílený CSS (`style.css`) + dva sdílené JS skripty. Jediná dynamická část je PHP admin console (`admin/`).

**Neexistuje žádný příkaz pro build, testy ani linting.** Vývoj probíhá přímou editací souborů a ověřením v prohlížeči. Nasazení = nahrání souborů na FTP na `farnostostrov.cz`.

## Admin console (`admin/`)

PHP redakční systém na `farnostostrov.cz/admin/`. Přihlášení heslem (uloženo v `admin/config.php`). Tři sekce:

| Sekce | Co dělá |
|---|---|
| **Nástěnka** | Upload obrázků/PDF, správa plakátů → zapisuje do `akce/nastenka.json` |
| **Ohlášky** | Upload PDF, automatický přesun starších do archivu (max 3 aktuální) → `ohlasky/ohlasky.json` + `ohlasky/ohlasky-archiv.json` |
| **Aktuality** | WYSIWYG editor (Quill.js), upload hero obrázku, volitelná příloha PDF → **generuje celý HTML soubor** `aktualita-{slug}.html` a zapisuje do `aktuality/aktuality.json` |

Klíčové konstanty v `admin/config.php`:
- `MAX_OHLASKY = 3` – max aktuálních ohlášek (starší se automaticky přesouvají do archivu)
- `MAX_AKTUALITY_HP = 3` – max karet aktualit zobrazených na homepage

### Generátor HTML (`generujAktualituHtml()`)

Funkce v `admin/index.php` vytváří a přepisuje soubory `aktualita-*.html`. Položky s `"generovano": true` v JSON jsou spravovány adminem (lze editovat/smazat). Položky s `"generovano": false` jsou ruční HTML soubory – admin je ze seznamu jen odebere, soubor nesmaže.

**Důsledek pro úpravy:** Při jakékoli změně HTML šablony aktualit (struktura, CSS třídy, ikony) je nutné upravit i `generujAktualituHtml()` v `admin/index.php`, jinak nově generované stránky nebudou konzistentní s ručně upravenými.

## Datové soubory (JSON)

Část obsahu je řízena JSON soubory, které stránky načítají přes `fetch()`. Soubory `.htaccess` zakazuje cachování JSON souborů (`Cache-Control: no-cache`), aby se změny projevily okamžitě.

| Soubor | Obsah | Konzumuje |
|---|---|---|
| `aktuality/aktuality.json` | Seznam aktualit (karty na homepage) | `index.html`, `aktuality.html` |
| `ohlasky/ohlasky.json` | Aktuální ohlášky (PDF) | `index.html` |
| `ohlasky/ohlasky-archiv.json` | Archiv ohlášek | `ohlasky-archiv-2026.html` |
| `akce/nastenka.json` | Plakáty nástěnky | `index.html` |

### Schéma aktuality (aktuality.json)
```json
{
  "soubor": "aktualita-nazev.html",
  "nazev": "Název příspěvku",
  "perex": "Krátký popis.",
  "kategorie": "Kategorie",
  "datum": "DD. měsíce YYYY",
  "hero_img": "aktuality/obrazek.webp",
  "hero_alt": "Popis obrázku",
  "generovano": false
}
```

### Schéma nástěnky (nastenka.json)
```json
{ "soubor": "nazev.webp", "nazev": "Popis", "typ": "img" }
{ "soubor": "nazev.pdf",  "nazev": "Popis", "typ": "pdf" }
```

### Schéma ohlášky (ohlasky.json / ohlasky-archiv.json)
```json
{ "soubor": "nazev-souboru.pdf", "nazev": "Ohlášky – ...", "datum1": "...", "datum2": "..." }
```

## Přidání nové aktuality

1. Vytvořit `aktualita-nazev.html` (použij existující aktualitu jako šablonu).
2. Přidat záznam **na začátek** pole v `aktuality/aktuality.json`.
3. Na homepage se zobrazí automaticky (načítá se prvních `MAX_AKTUALITY_HP` = 3 položek ze JSON).
4. Přidat URL do `sitemap.xml`.

## Přidání nové ohlášky

1. Nahrát PDF do složky `ohlasky/`.
2. Přidat záznam **na začátek** pole v `ohlasky/ohlasky.json`.
3. Přidat záznam do `ohlasky/ohlasky-archiv.json`.

## Přidání plakátu na nástěnku

1. Nahrát soubor (webp/jpg/png/pdf) do složky `akce/`.
2. Přidat záznam do `akce/nastenka.json`.

## Sdílené komponenty

### Navigace
Navigace je **zkopírována v každém HTML souboru** zvlášť – neexistuje žádný include. Změna navigace vyžaduje editaci každého HTML souboru. Hamburger menu pro mobil se ovládá přepínáním třídy `open` přes inline JS přímo v HTML.

### Patička (`footer.js`)
Patička se generuje JS skriptem a vkládá se do `<div id="site-footer">`. Každá stránka musí obsahovat:
```html
<div id="site-footer"></div>
<script src="footer.js"></script>
```
Kontaktní formulář v patičce je napojen na **Web3Forms** (klíč přístupový přímo v JS).

### Galerie (`gallery.js`)
Lightbox pro fotografie. Aktivuje se automaticky na `.galerie-item` uvnitř `.galerie`. Podporuje klik, swipe i klávesy ← → Escape.

## Design systém

**Barvy:**
- `#0a0602` – hlavní pozadí
- `#180f05` – nástěnka, tmavší plochy
- `#c9922a` – zlatý akcent (CSS proměnná `--gold`)
- `#f5e4b0` – základní text (CSS proměnná `--cream`)

**Písma (Google Fonts):**
- `Cinzel Decorative` – logo, H1
- `Cinzel` – navigace, nadpisy, popisky
- `EB Garamond` – tělo textu

**Ornament** (dekorativní oddělovač sekcí):
```html
<div class="ornament">
  <span class="ornament-line"></span>
  <span class="ornament-cross">✦</span>
  <span class="ornament-line rev"></span>
</div>
```

## Šablona stránky aktuality

Každá `aktualita-*.html` sdílí tuto strukturu:
```html
<nav>…</nav>
<div class="prispevek-hero">
  <img class="prispevek-hero-img" src="…" alt="…">
  <div class="prispevek-hero-overlay"></div>
  <div class="prispevek-hero-meta">
    <p class="prispevek-kategorie">…</p>
    <p class="prispevek-datum">…</p>
  </div>
</div>
<article class="prispevek-obsah">
  <a class="prispevek-zpet" href="aktuality.html">← Zpět</a>
  <h1 class="prispevek-nadpis">…</h1>
  <!-- obsah -->
</article>
<div id="site-footer"></div>
<script src="footer.js"></script>
```

## Co se mění ručně a kde

| Co | Kde |
|---|---|
| Pořad bohoslužeb | Přímo v HTML, sekce `#bohosluzby` v `index.html` |
| Kontakty, kněží v patičce | `footer.js` |
| Stránka Naši kněží | `nasi-knezi.html` |
| Mapa kostelů | `nase-kostely.html` |

## Externí závislosti

| Služba | Účel | Kde je klíč/ID |
|---|---|---|
| Google Fonts | Písma (Cinzel, EB Garamond) | `<link>` v `<head>` každé stránky |
| Google Analytics | Měření návštěvnosti (G-YC8PFWZWHN) | Inline `<script>` v `<head>` každé stránky |
| Web3Forms | Odesílání kontaktního formuláře bez backendu | Přímo v `footer.js` |

## Nasazení

FTP upload na `farnostostrov.cz`. Žádný build krok – co je v repozitáři, jde přímo na server. Po přidání nové stránky je nutné aktualizovat `sitemap.xml`.
