# Dokumentace webu – Farnost Ostrov

> Stav k: duben 2026  
> Autor: Marek Poledníček / TEE Design CZ  
> URL: [www.farnostostrov.cz](https://www.farnostostrov.cz)

---

## 1. Přehled architektury

Web je **čistě statický** – žádný server-side jazyk, žádná databáze, žádný build proces. Vše jsou holé HTML soubory + jeden sdílený CSS + dva JS skripty. Nasazení = nahrání souborů na FTP.

```
farnost_ostrov_II/
│
├── index.html                  ← hlavní stránka (všechny sekce homepage)
├── style.css                   ← globální styly (celý web)
├── footer.js                   ← patička (generovaná JS, sdílená)
├── gallery.js                  ← lightbox galerie (sdílený skript)
├── sitemap.xml
│
├── aktualita-*.html            ← jednotlivé aktuality / příspěvky
├── *-kostel-*.html             ← stránky kostelů (12 kostelů)
├── zivotni-situace.html        ← rozcestník životních situací
├── krest-ditete.html           ← křest dítěte
├── krest-dospeleho.html        ← křest dospělého
├── svatba-v-kostele.html       ← svatba
├── cirkevni-pohreb.html        ← pohřeb
├── navsteva-kostela.html       ← návštěva kostela
├── zajinam-se-o-krestanstvi.html
├── o-farnosti.html
├── nase-kostely.html           ← přehled všech kostelů s mapou
├── nasi-knezi.html
├── foto-galerie.html
├── knihovna-sv-anny.html
├── e-shop.html
├── ohlasky-archiv-2026.html
│
├── img/                        ← sdílené obrázky (hero, kostely, ikony)
├── akce/                       ← plakáty nástěnky (webp / jpg / png / pdf)
├── galerie/                    ← fotografie pro galerie
├── aktuality/                  ← obrázky a přílohy aktualit
├── ohlasky/                    ← PDF soubory týdenních ohlášek
├── obsah/                      ← ostatní obsahové soubory
└── zdroje/                     ← zdrojové podklady (CorelDraw, PNG nákresy)
```

---

## 2. Design systém

### Písma (Google Fonts)
| Písmo | Použití |
|---|---|
| **Cinzel Decorative** | Logo, hlavní nadpisy (H1) |
| **Cinzel** | Nadpisy sekcí, navigace, popisky |
| **EB Garamond** | Tělo textu, odstavce |

### Barvy
| Proměnná / hodnota | Použití |
|---|---|
| `#0a0602` | Hlavní pozadí (téměř černá) |
| `#180f05` | Nástěnka, tmavší plochy |
| `#c9922a` (gold) | Akcenty, zlaté linky, ikonky |
| `#f5e4b0` | Základní text |
| `rgba(201,146,42,0.x)` | Průhledné zlaté bordery a stíny |

### Ornament
Opakující se dekorativní prvek mezi sekcemi:
```html
<div class="ornament">
  <span class="ornament-line"></span>
  <span class="ornament-cross">✦</span>  <!-- nebo ✝ -->
  <span class="ornament-line rev"></span>
</div>
```

---

## 3. Typy stránek a jejich šablony

### 3.1 Homepage (`index.html`)
Jedna velká stránka sestavená ze sekcí oddělených kotvami (`#id`). Navigace odkazuje na tyto kotvy.

| Sekce | ID kotvy | Obsah |
|---|---|---|
| Hero | — | Animovaný banner s fotografií, motto, CTA tlačítka |
| Aktuality | `#aktuality` | Karty příspěvků (obrázek + nadpis + perex + odkaz) |
| Akce / Nástěnka | `#akce` | Plakáty připnuté na tmavé desce s efektem špendlíku |
| Ohlášky | `#ohlasky` | Odkaz na aktuální PDF + archiv |
| Pořad bohoslužeb | `#bohosluzby` | Grid s časy mší pro každý kostel |
| Rychlé odkazy | — | Grid ikon s odkazy na hlavní sekce |
| Kontakty / Patička | `#kontakty` | Adresa, kontaktní osoby, formulář |

### 3.2 Aktuality (`aktualita-*.html`)
Samostatné stránky pro jednotlivé příspěvky. Sdílená struktura:
```html
<nav>…</nav>

<div class="prispevek-hero">           ← hero obrázek s overlayem a metadaty
  <img class="prispevek-hero-img">
  <div class="prispevek-hero-overlay"></div>
  <div class="prispevek-hero-meta">
    <p class="prispevek-kategorie">…</p>
    <p class="prispevek-datum">…</p>
  </div>
</div>

<article class="prispevek-obsah">     ← obsah článku
  <a class="prispevek-zpet">…</a>    ← odkaz zpět
  <h1 class="prispevek-nadpis">…</h1>
  <h2 class="prispevek-tritulek">…</h2>
  <!-- obsah -->
</article>

<div id="site-footer"></div>
<script src="footer.js"></script>
```
Na homepage se každá aktualita zobrazuje jako karta v sekci `#aktuality` — tu je nutné **ručně přidat** při vytvoření nového příspěvku.

### 3.3 Kostely (`*-kostel-*.html`)
12 stránek, každá pro jeden kostel. Stejná šablona jako aktualita, rozšířená o:
- Akvarel/fotku kostela
- Popis a historii
- Pořad bohoslužeb pro daný kostel
- Galerii fotografií (napojená na `gallery.js`)

### 3.4 Životní situace
Informační stránky (křest, svatba, pohřeb, …). Stejná základní šablona, obsah je statický text.

---

## 4. Sdílené komponenty

### Navigace
Navigace je **zkopírována do každého HTML souboru** zvlášť (není žádný include). Obsahuje hamburger menu pro mobil (toggle třídou `open` přes inline JS).

### Patička (`footer.js`)
Patička se generuje JS skriptem – vkládá se do `<div id="site-footer">`. Obsahuje:
- Adresu a kontakty farnosti
- Kontaktní formulář napojený na **Web3Forms** (klíč uložen přímo v JS)
- Facebook odkaz

### Galerie (`gallery.js`)
Sdílený lightbox pro fotogalerie. Aktivuje se automaticky na každém prvku `.galerie-item` uvnitř `.galerie`. Podporuje:
- Klik myší
- Swipe na dotykových zařízeních
- Klávesy ← → Escape

### Nástěnka Akce
Sekce v `index.html`. Každý plakát = jeden `.plakat-pin` div:
```html
<!-- Obrázek (webp / jpg / png) — otevře se v lightboxu -->
<div class="plakat-pin" onclick="otevriPlakat('akce/nazev.jpg', 'Popis')">
  <img src="akce/nazev.jpg" alt="Popis" class="plakat-img">
</div>

<!-- PDF — otevře se v nové záložce -->
<div class="plakat-pin" onclick="window.open('akce/nazev.pdf','_blank')">
  …karta…
</div>
```
Plakáty se automaticky rotují (CSS `nth-child`) pro efekt špendlíku.

### Ohlášky
Sekce v `index.html`. Aktuální ohlášky jsou PDF soubory ve složce `ohlasky/`. Na stránce je přímý odkaz na PDF a odkaz na archivní stránku `ohlasky-archiv-2026.html`.

---

## 5. Externí závislosti

| Služba | Účel |
|---|---|
| Google Fonts | Písma (Cinzel, EB Garamond) |
| Google Analytics (G-YC8PFWZWHN) | Měření návštěvnosti |
| Web3Forms | Odesílání kontaktního formuláře (bez backendu) |

---

## 6. Co se mění ručně a jak

| Co | Kde | Jak často |
|---|---|---|
| Aktuality (nový příspěvek) | Nový `aktualita-*.html` + karta v `index.html` | Nepravidelně |
| Ohlášky | Nové PDF do `ohlasky/` + odkaz v `index.html` | Každý týden |
| Archiv ohlášek | `ohlasky-archiv-2026.html` | Každý týden |
| Plakáty nástěnky | Soubor do `akce/` + pin v `index.html` | Nepravidelně |
| Pořad bohoslužeb | Přímo v HTML `index.html` (sekce `#bohosluzby`) | Při změně |
| Patička (kontakty, kněží) | `footer.js` | Při změně |
| Naši kněží | `nasi-knezi.html` | Při změně |

---

## 7. Bolestivá místa – podklady pro CMS

Tyto věci jsou pro správce webu obtížné bez znalosti HTML:

1. **Navigace** — zkopírovaná v každém souboru. Změna = editace desítek souborů.
2. **Aktuality** — přidání příspěvku vyžaduje vytvořit HTML soubor A ručně přidat kartu na homepage.
3. **Ohlášky** — každý týden ruční editace HTML + nahrání PDF.
4. **Nástěnka Akce** — přidání plakátu = editace `index.html`.
5. **Pořad bohoslužeb** — hardcoded HTML tabulka.
6. **Žádná ochrana** — web je veřejný, žádné admin rozhraní.

Tato místa jsou primárními kandidáty pro redakční systém.

---

## 8. Nasazení

- Hosting: FTP (klasický webhostink, žádný CI/CD)
- Doména: `farnostostrov.cz`
- Nasazení = nahrání souborů přes FTP klienta (FileZilla apod.)
- Žádný build krok — vše co je v repozitáři jde přímo na server

---

*Dokument slouží jako výchozí bod pro zavedení redakčního systému (CMS).*
