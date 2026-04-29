<?php
session_start();
require_once __DIR__ . '/config.php';

$zprava     = '';
$zprava_typ = ''; // 'ok' | 'chyba'
$sekce      = $_GET['sekce'] ?? 'nastenka'; // nastenka | ohlasky | aktuality

// Flash zprávy (po přesměrování)
if (!empty($_SESSION['flash_zprava'])) {
    $zprava     = $_SESSION['flash_zprava'];
    $zprava_typ = $_SESSION['flash_typ'] ?? 'ok';
    unset($_SESSION['flash_zprava'], $_SESSION['flash_typ']);
}

/* ════════════════════════════════════════════════════════════
   POMOCNÉ FUNKCE – OBECNÉ
   ════════════════════════════════════════════════════════════ */

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function sanitizujSoubor(string $nazev): string {
    $from = ['á','č','ď','é','ě','í','ň','ó','ř','š','ť','ú','ů','ý','ž',
             'Á','Č','Ď','É','Ě','Í','Ň','Ó','Ř','Š','Ť','Ú','Ů','Ý','Ž'];
    $to   = ['a','c','d','e','e','i','n','o','r','s','t','u','u','y','z',
             'a','c','d','e','e','i','n','o','r','s','t','u','u','y','z'];
    $nazev = str_replace($from, $to, $nazev);
    $nazev = strtolower($nazev);
    $nazev = preg_replace('/[^a-z0-9._-]/', '-', $nazev);
    $nazev = preg_replace('/-{2,}/', '-', $nazev);
    return trim($nazev, '-');
}

function nactiJson(string $cesta): array {
    if (!file_exists($cesta)) return [];
    $data = json_decode(file_get_contents($cesta), true);
    return is_array($data) ? $data : [];
}

function ulozJson(string $cesta, array $data): void {
    file_put_contents($cesta, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/* ════════════════════════════════════════════════════════════
   ODHLÁŠENÍ
   ════════════════════════════════════════════════════════════ */

if (isset($_GET['odhlasit'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

/* ════════════════════════════════════════════════════════════
   PŘIHLÁŠENÍ
   ════════════════════════════════════════════════════════════ */

if (!isset($_SESSION['farnost_admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['heslo'])) {
        if ($_POST['heslo'] === ADMIN_HESLO) {
            session_regenerate_id(true);
            $_SESSION['farnost_admin'] = true;
            header('Location: index.php?sekce=' . h($sekce));
            exit;
        }
        $zprava     = 'Nesprávné heslo.';
        $zprava_typ = 'chyba';
    }
}

/* ════════════════════════════════════════════════════════════
   CHRÁNĚNÉ AKCE
   ════════════════════════════════════════════════════════════ */

if (isset($_SESSION['farnost_admin']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $akce = $_POST['akce'] ?? '';

    /* ── NÁSTĚNKA ─────────────────────────────────────────── */

    if ($sekce === 'nastenka') {

        if ($akce === 'smazat' && !empty($_POST['soubor'])) {
            $soubor  = basename($_POST['soubor']);
            $plakaty = nactiJson(JSON_SOUBOR);
            $pred    = count($plakaty);
            $plakaty = array_filter($plakaty, fn($p) => $p['soubor'] !== $soubor);
            if (count($plakaty) < $pred) {
                ulozJson(JSON_SOUBOR, $plakaty);
                $cesta = AKCE_ADRESAR . $soubor;
                if (file_exists($cesta)) unlink($cesta);
                $zprava     = 'Plakát byl odebrán z nástěnky.';
                $zprava_typ = 'ok';
            }
        }

        if ($akce === 'pridat') {
            $nazev  = trim($_POST['nazev'] ?? '');
            $upload = $_FILES['soubor'] ?? null;
            if (empty($nazev)) {
                $zprava = 'Zadejte název plakátu.'; $zprava_typ = 'chyba';
            } elseif (!$upload || $upload['error'] !== UPLOAD_ERR_OK) {
                $kody = [UPLOAD_ERR_INI_SIZE => 'Soubor je příliš velký (limit serveru).',
                         UPLOAD_ERR_FORM_SIZE => 'Soubor je příliš velký.',
                         UPLOAD_ERR_NO_FILE   => 'Nebyl vybrán žádný soubor.'];
                $zprava = $kody[$upload['error'] ?? 0] ?? 'Chyba při nahrávání souboru.';
                $zprava_typ = 'chyba';
            } else {
                $mime    = mime_content_type($upload['tmp_name']);
                $pripona = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
                if (!in_array($mime, POVOLENE_MIME) || !in_array($pripona, POVOLENE_PRIPONY)) {
                    $zprava = 'Nepodporovaný formát. Povoleno: JPG, PNG, WEBP, GIF, PDF.';
                    $zprava_typ = 'chyba';
                } else {
                    $zaklad = sanitizujSoubor(pathinfo($upload['name'], PATHINFO_FILENAME));
                    if (empty($zaklad)) $zaklad = 'plakat';
                    $nazevSouboru = $zaklad . '.' . $pripona;
                    $cilCesta     = AKCE_ADRESAR . $nazevSouboru;
                    $i = 1;
                    while (file_exists($cilCesta)) {
                        $nazevSouboru = $zaklad . '-' . $i . '.' . $pripona;
                        $cilCesta     = AKCE_ADRESAR . $nazevSouboru;
                        $i++;
                    }
                    if (move_uploaded_file($upload['tmp_name'], $cilCesta)) {
                        $plakaty   = nactiJson(JSON_SOUBOR);
                        $plakaty[] = ['soubor' => $nazevSouboru, 'nazev' => $nazev,
                                      'typ' => ($pripona === 'pdf') ? 'pdf' : 'img'];
                        ulozJson(JSON_SOUBOR, $plakaty);
                        $zprava = 'Plakát „' . h($nazev) . '" byl přidán na nástěnku.';
                        $zprava_typ = 'ok';
                    } else {
                        $zprava = 'Soubor se nepodařilo uložit. Zkontrolujte práva ke složce akce/.';
                        $zprava_typ = 'chyba';
                    }
                }
            }
        }
    }

    /* ── OHLÁŠKY ──────────────────────────────────────────── */

    if ($sekce === 'ohlasky') {

        /* Auto-archivace ohlášek s prošlým datem expirace */
        {
            $dnes_datum   = date('Y-m-d');
            $ohlasky_auto = nactiJson(OHLASKY_JSON);
            $archiv_auto  = nactiJson(OHLASKY_ARCHIV_JSON);
            $auto_zmeneno = false;
            $ohlasky_auto = array_filter($ohlasky_auto, function ($o) use ($dnes_datum, &$archiv_auto, &$auto_zmeneno) {
                if (!empty($o['datum_expirace']) && $o['datum_expirace'] < $dnes_datum) {
                    unset($o['pinnováno']);
                    array_unshift($archiv_auto, $o);
                    $auto_zmeneno = true;
                    return false;
                }
                return true;
            });
            if ($auto_zmeneno) {
                ulozJson(OHLASKY_JSON, array_values($ohlasky_auto));
                ulozJson(OHLASKY_ARCHIV_JSON, $archiv_auto);
            }
        }

        /* Přidat ohlášky */
        if ($akce === 'pridat') {
            $nazev          = trim($_POST['nazev']          ?? '');
            $poznamka       = trim($_POST['poznamka']       ?? '');
            $datum_expirace = trim($_POST['datum_expirace'] ?? '');
            $pinnováno      = !empty($_POST['pinnováno']);
            $upload         = $_FILES['soubor'] ?? null;

            $maUpload = $upload && $upload['error'] !== UPLOAD_ERR_NO_FILE;

            if (empty($nazev) || empty($datum_expirace)) {
                $zprava = 'Zadejte název a datum expirace ohlášek.'; $zprava_typ = 'chyba';
            } elseif ($maUpload && $upload['error'] !== UPLOAD_ERR_OK) {
                $kody = [UPLOAD_ERR_INI_SIZE => 'Soubor je příliš velký (limit serveru).',
                         UPLOAD_ERR_FORM_SIZE => 'Soubor je příliš velký.'];
                $zprava = $kody[$upload['error']] ?? 'Chyba při nahrávání souboru.';
                $zprava_typ = 'chyba';
            } else {
                $nazevSouboru = '';
                if ($maUpload) {
                    $mime    = mime_content_type($upload['tmp_name']);
                    $pripona = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
                    if ($mime !== 'application/pdf' || $pripona !== 'pdf') {
                        $zprava = 'Ohlášky musí být ve formátu PDF.'; $zprava_typ = 'chyba';
                        goto konec_pridat;
                    }
                    $zaklad = sanitizujSoubor(pathinfo($upload['name'], PATHINFO_FILENAME));
                    if (empty($zaklad)) $zaklad = 'ohlasky';
                    $nazevSouboru = $zaklad . '.pdf';
                    $cilCesta     = OHLASKY_ADRESAR . $nazevSouboru;
                    $i = 1;
                    while (file_exists($cilCesta)) {
                        $nazevSouboru = $zaklad . '-' . $i . '.pdf';
                        $cilCesta     = OHLASKY_ADRESAR . $nazevSouboru;
                        $i++;
                    }
                    if (!move_uploaded_file($upload['tmp_name'], $cilCesta)) {
                        $zprava = 'Soubor se nepodařilo uložit. Zkontrolujte práva ke složce ohlasky/.';
                        $zprava_typ = 'chyba';
                        goto konec_pridat;
                    }
                }
                $nova    = ['soubor' => $nazevSouboru, 'nazev' => $nazev,
                            'poznamka' => $poznamka, 'datum_expirace' => $datum_expirace,
                            'pinnováno' => $pinnováno];
                $ohlasky = nactiJson(OHLASKY_JSON);
                if ($pinnováno) {
                    array_unshift($ohlasky, $nova);
                } else {
                    $prvni_nepinnovaný = count(array_filter($ohlasky, fn($o) => !empty($o['pinnováno'])));
                    array_splice($ohlasky, $prvni_nepinnovaný, 0, [$nova]);
                }
                ulozJson(OHLASKY_JSON, $ohlasky);
                $zprava = 'Ohlášky „' . h($nazev) . '" byly přidány.';
                $zprava_typ = 'ok';
            }
            konec_pridat:
        }

        /* Archivovat ohlášky (přesun bez smazání souboru) */
        if ($akce === 'archivovat' && !empty($_POST['soubor'])) {
            $soubor  = basename($_POST['soubor']);
            $ohlasky = nactiJson(OHLASKY_JSON);
            $archiv  = nactiJson(OHLASKY_ARCHIV_JSON);
            $item    = null;
            $ohlasky = array_filter($ohlasky, function($o) use ($soubor, &$item) {
                if ($o['soubor'] === $soubor) { $item = $o; return false; }
                return true;
            });
            if ($item) {
                array_unshift($archiv, $item);
                ulozJson(OHLASKY_JSON, $ohlasky);
                ulozJson(OHLASKY_ARCHIV_JSON, $archiv);
                $zprava = 'Ohlášky byly přesunuty do archivu.';
                $zprava_typ = 'ok';
            }
        }

        /* Přepnout připnutí */
        if ($akce === 'pinovat' && !empty($_POST['soubor'])) {
            $soubor  = basename($_POST['soubor']);
            $ohlasky = nactiJson(OHLASKY_JSON);
            foreach ($ohlasky as &$o) {
                if ($o['soubor'] === $soubor) {
                    $o['pinnováno'] = !($o['pinnováno'] ?? false);
                    break;
                }
            }
            unset($o);
            usort($ohlasky, fn($a, $b) => ($b['pinnováno'] ?? false) <=> ($a['pinnováno'] ?? false));
            ulozJson(OHLASKY_JSON, $ohlasky);
            $zprava = 'Připnutí bylo změněno.'; $zprava_typ = 'ok';
        }

        /* Smazat ohlášky (z aktuálních) */
        if ($akce === 'smazat' && !empty($_POST['soubor'])) {
            $soubor  = basename($_POST['soubor']);
            $ohlasky = nactiJson(OHLASKY_JSON);
            $pred    = count($ohlasky);
            $ohlasky = array_filter($ohlasky, fn($o) => $o['soubor'] !== $soubor);
            if (count($ohlasky) < $pred) {
                ulozJson(OHLASKY_JSON, $ohlasky);
                $cesta = OHLASKY_ADRESAR . $soubor;
                if (file_exists($cesta)) unlink($cesta);
                $zprava = 'Ohlášky byly smazány.'; $zprava_typ = 'ok';
            }
        }

        /* Smazat z archivu */
        if ($akce === 'smazat_archiv' && !empty($_POST['soubor'])) {
            $soubor = basename($_POST['soubor']);
            $archiv = nactiJson(OHLASKY_ARCHIV_JSON);
            $pred   = count($archiv);
            $archiv = array_filter($archiv, fn($o) => $o['soubor'] !== $soubor);
            if (count($archiv) < $pred) {
                ulozJson(OHLASKY_ARCHIV_JSON, $archiv);
                $cesta = OHLASKY_ADRESAR . $soubor;
                if (file_exists($cesta)) unlink($cesta);
                $zprava = 'Archivovaná ohlášky byly smazány.'; $zprava_typ = 'ok';
            }
        }
    }

    /* ── AKTUALITY ────────────────────────────────────────── */

    if ($sekce === 'aktuality') {

        /* Přidat aktualitu */
        if ($akce === 'pridat') {
            $slug      = trim($_POST['slug']      ?? '');
            $nazev     = trim($_POST['nazev']      ?? '');
            $kategorie = trim($_POST['kategorie']  ?? '');
            $datum     = trim($_POST['datum']      ?? '');
            $perex     = trim($_POST['perex']      ?? '');
            $trititulek = trim($_POST['trititulek'] ?? '');
            $hero_alt  = trim($_POST['hero_alt']   ?? '');
            $obsah     = trim($_POST['obsah']      ?? '');
            $upload    = $_FILES['hero_img']       ?? null;

            $slug = sanitizujSoubor($slug);

            if (empty($slug) || empty($nazev) || empty($kategorie) || empty($datum) || empty($perex)) {
                $zprava = 'Vyplňte všechna povinná pole (slug, název, kategorie, datum, perex).';
                $zprava_typ = 'chyba';
            } elseif (!$upload || $upload['error'] !== UPLOAD_ERR_OK) {
                $kody = [UPLOAD_ERR_INI_SIZE => 'Obrázek je příliš velký (limit serveru).',
                         UPLOAD_ERR_FORM_SIZE => 'Obrázek je příliš velký.',
                         UPLOAD_ERR_NO_FILE   => 'Nebyl vybrán hero obrázek.'];
                $zprava = $kody[$upload['error'] ?? 0] ?? 'Chyba při nahrávání obrázku.';
                $zprava_typ = 'chyba';
            } else {
                $mime    = mime_content_type($upload['tmp_name']);
                $pripona = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
                $povolMime = ['image/jpeg', 'image/png', 'image/webp'];
                $povolPrip = ['jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($mime, $povolMime) || !in_array($pripona, $povolPrip)) {
                    $zprava = 'Hero obrázek musí být JPG, PNG nebo WEBP.'; $zprava_typ = 'chyba';
                } else {
                    $imgZaklad    = sanitizujSoubor(pathinfo($upload['name'], PATHINFO_FILENAME));
                    if (empty($imgZaklad)) $imgZaklad = $slug;
                    $imgNazev     = $imgZaklad . '.' . $pripona;
                    $imgCesta     = AKTUALITY_ADRESAR . $imgNazev;
                    $i = 1;
                    while (file_exists($imgCesta)) {
                        $imgNazev = $imgZaklad . '-' . $i . '.' . $pripona;
                        $imgCesta = AKTUALITY_ADRESAR . $imgNazev;
                        $i++;
                    }

                    $htmlSoubor = 'aktualita-' . $slug . '.html';
                    $htmlCesta  = __DIR__ . '/../' . $htmlSoubor;

                    if (file_exists($htmlCesta)) {
                        $zprava = 'Stránka „' . h($htmlSoubor) . '" již existuje. Zvolte jiný slug.';
                        $zprava_typ = 'chyba';
                    } elseif (!move_uploaded_file($upload['tmp_name'], $imgCesta)) {
                        $zprava = 'Obrázek se nepodařilo uložit. Zkontrolujte práva ke složce aktuality/.';
                        $zprava_typ = 'chyba';
                    } else {
                        $heroImgPath = 'aktuality/' . $imgNazev;

                        // Volitelná příloha PDF
                        $prilohaPath  = null;
                        $prilohaNazev = trim($_POST['priloha_nazev'] ?? '');
                        $uploadPdf    = $_FILES['priloha_pdf'] ?? null;
                        if ($uploadPdf && $uploadPdf['error'] === UPLOAD_ERR_OK) {
                            $mimeP = mime_content_type($uploadPdf['tmp_name']);
                            $extP  = strtolower(pathinfo($uploadPdf['name'], PATHINFO_EXTENSION));
                            if ($mimeP === 'application/pdf' && $extP === 'pdf') {
                                $pdfZak  = sanitizujSoubor(pathinfo($uploadPdf['name'], PATHINFO_FILENAME));
                                if (empty($pdfZak)) $pdfZak = $slug . '-priloha';
                                $pdfNaz  = $pdfZak . '.pdf';
                                $pdfCes  = AKTUALITY_ADRESAR . $pdfNaz;
                                $j = 1;
                                while (file_exists($pdfCes)) {
                                    $pdfNaz = $pdfZak . '-' . $j . '.pdf';
                                    $pdfCes = AKTUALITY_ADRESAR . $pdfNaz;
                                    $j++;
                                }
                                if (move_uploaded_file($uploadPdf['tmp_name'], $pdfCes)) {
                                    $prilohaPath = 'aktuality/' . $pdfNaz;
                                }
                            }
                        }

                        $htmlObsah = generujAktualituHtml([
                            'soubor'         => $htmlSoubor,
                            'nazev'          => $nazev,
                            'kategorie'      => $kategorie,
                            'datum'          => $datum,
                            'perex'          => $perex,
                            'trititulek'     => $trititulek,
                            'hero_img'       => $heroImgPath,
                            'hero_alt'       => $hero_alt ?: $nazev,
                            'obsah'          => $obsah,
                            'priloha_soubor' => $prilohaPath,
                            'priloha_nazev'  => $prilohaNazev ?: 'Příloha',
                        ]);
                        file_put_contents($htmlCesta, $htmlObsah);

                        $nova = [
                            'soubor'         => $htmlSoubor,
                            'nazev'          => $nazev,
                            'perex'          => $perex,
                            'kategorie'      => $kategorie,
                            'datum'          => $datum,
                            'hero_img'       => $heroImgPath,
                            'hero_alt'       => $hero_alt ?: $nazev,
                            'trititulek'     => $trititulek,
                            'obsah'          => $obsah,
                            'priloha'        => $prilohaPath,
                            'priloha_nazev'  => $prilohaPath ? ($prilohaNazev ?: 'Příloha') : '',
                            'generovano'     => true,
                        ];
                        $aktuality = nactiJson(AKTUALITY_JSON);
                        array_unshift($aktuality, $nova);
                        ulozJson(AKTUALITY_JSON, $aktuality);

                        $zprava = 'Aktualita „' . h($nazev) . '" byla přidána.';
                        $zprava_typ = 'ok';
                    }
                }
            }
        }

        /* Smazat aktualitu */
        if ($akce === 'smazat' && !empty($_POST['soubor'])) {
            $soubor    = basename($_POST['soubor']);
            $aktuality = nactiJson(AKTUALITY_JSON);
            $item      = null;
            $aktuality = array_filter($aktuality, function($a) use ($soubor, &$item) {
                if ($a['soubor'] === $soubor) { $item = $a; return false; }
                return true;
            });
            if ($item) {
                ulozJson(AKTUALITY_JSON, $aktuality);
                if (!empty($item['generovano'])) {
                    // Smazat vygenerovanou HTML stránku
                    $htmlCesta = __DIR__ . '/../' . $soubor;
                    if (file_exists($htmlCesta)) unlink($htmlCesta);
                    // Smazat hero obrázek (jen pokud je v aktuality/)
                    $img = $item['hero_img'] ?? '';
                    if (strpos($img, 'aktuality/') === 0) {
                        $imgCesta = __DIR__ . '/../' . $img;
                        if (file_exists($imgCesta)) unlink($imgCesta);
                    }
                    // Smazat přílohu PDF (jen pokud je v aktuality/)
                    $pril = $item['priloha'] ?? '';
                    if ($pril && strpos($pril, 'aktuality/') === 0) {
                        $prilCesta = __DIR__ . '/../' . $pril;
                        if (file_exists($prilCesta)) unlink($prilCesta);
                    }
                    $zprava = 'Aktualita „' . h($item['nazev']) . '" byla smazána včetně stránky.';
                } else {
                    $zprava = 'Aktualita „' . h($item['nazev']) . '" byla odebrána ze seznamu (stránka zůstala zachována).';
                }
                $zprava_typ = 'ok';
            }
        }

        /* Upravit aktualitu */
        if ($akce === 'upravit' && !empty($_POST['soubor_orig'])) {
            $soubor_orig = basename($_POST['soubor_orig']);
            $nazev       = trim($_POST['nazev']       ?? '');
            $kategorie   = trim($_POST['kategorie']   ?? '');
            $datum       = trim($_POST['datum']       ?? '');
            $perex       = trim($_POST['perex']       ?? '');
            $trititulek  = trim($_POST['trititulek']  ?? '');
            $hero_alt    = trim($_POST['hero_alt']    ?? '');
            $obsah       = trim($_POST['obsah']       ?? '');
            $priloha_nazev_new = trim($_POST['priloha_nazev'] ?? '');

            if (empty($nazev) || empty($kategorie) || empty($datum) || empty($perex)) {
                $zprava = 'Vyplňte všechna povinná pole.'; $zprava_typ = 'chyba';
            } else {
                $aktuality = nactiJson(AKTUALITY_JSON);
                $idx = null;
                foreach ($aktuality as $k => $a) {
                    if ($a['soubor'] === $soubor_orig) { $idx = $k; break; }
                }

                if ($idx === null) {
                    $zprava = 'Aktualita nebyla nalezena.'; $zprava_typ = 'chyba';
                } else {
                    $item = $aktuality[$idx];

                    // Hero obrázek – nahrát nový nebo ponechat stávající
                    $heroImgPath = $item['hero_img'];
                    $uploadImg   = $_FILES['hero_img'] ?? null;
                    if ($uploadImg && $uploadImg['error'] === UPLOAD_ERR_OK) {
                        $mime    = mime_content_type($uploadImg['tmp_name']);
                        $pripona = strtolower(pathinfo($uploadImg['name'], PATHINFO_EXTENSION));
                        $povolMime = ['image/jpeg','image/png','image/webp'];
                        $povolPrip = ['jpg','jpeg','png','webp'];
                        if (!in_array($mime, $povolMime) || !in_array($pripona, $povolPrip)) {
                            $zprava = 'Hero obrázek musí být JPG, PNG nebo WEBP.'; $zprava_typ = 'chyba';
                        } else {
                            $slug     = preg_replace('/^aktualita-|\.html$/', '', $soubor_orig);
                            $imgZak   = sanitizujSoubor(pathinfo($uploadImg['name'], PATHINFO_FILENAME));
                            if (empty($imgZak)) $imgZak = $slug;
                            $imgNaz   = $imgZak . '.' . $pripona;
                            $imgCes   = AKTUALITY_ADRESAR . $imgNaz;
                            $i = 1;
                            while (file_exists($imgCes) && AKTUALITY_ADRESAR . $imgNaz !== __DIR__ . '/../' . $item['hero_img']) {
                                $imgNaz = $imgZak . '-' . $i . '.' . $pripona; $imgCes = AKTUALITY_ADRESAR . $imgNaz; $i++;
                            }
                            if (move_uploaded_file($uploadImg['tmp_name'], $imgCes)) {
                                // Smazat starý obrázek
                                $staryImg = __DIR__ . '/../' . $item['hero_img'];
                                if (file_exists($staryImg) && realpath($staryImg) !== realpath($imgCes)) unlink($staryImg);
                                $heroImgPath = 'aktuality/' . $imgNaz;
                            }
                        }
                    }

                    // Příloha PDF – nahrát novou nebo ponechat stávající
                    $prilohaPath  = $item['priloha'] ?? null;
                    $priloha_nazev_fin = $priloha_nazev_new ?: ($item['priloha_nazev'] ?? 'Příloha');
                    $uploadPdf    = $_FILES['priloha_pdf'] ?? null;
                    if ($uploadPdf && $uploadPdf['error'] === UPLOAD_ERR_OK) {
                        $mimeP = mime_content_type($uploadPdf['tmp_name']);
                        $extP  = strtolower(pathinfo($uploadPdf['name'], PATHINFO_EXTENSION));
                        if ($mimeP === 'application/pdf' && $extP === 'pdf') {
                            $slug2   = preg_replace('/^aktualita-|\.html$/', '', $soubor_orig);
                            $pdfZak  = sanitizujSoubor(pathinfo($uploadPdf['name'], PATHINFO_FILENAME));
                            if (empty($pdfZak)) $pdfZak = $slug2 . '-priloha';
                            $pdfNaz  = $pdfZak . '.pdf';
                            $pdfCes  = AKTUALITY_ADRESAR . $pdfNaz;
                            $j = 1;
                            while (file_exists($pdfCes)) { $pdfNaz = $pdfZak . '-' . $j . '.pdf'; $pdfCes = AKTUALITY_ADRESAR . $pdfNaz; $j++; }
                            if (move_uploaded_file($uploadPdf['tmp_name'], $pdfCes)) {
                                // Smazat starou přílohu
                                if ($prilohaPath && strpos($prilohaPath, 'aktuality/') === 0) {
                                    $stara = __DIR__ . '/../' . $prilohaPath;
                                    if (file_exists($stara)) unlink($stara);
                                }
                                $prilohaPath = 'aktuality/' . $pdfNaz;
                            }
                        }
                    }

                    if ($zprava_typ !== 'chyba') {
                        // Regenerovat HTML
                        $htmlCesta = __DIR__ . '/../' . $soubor_orig;
                        $htmlObsah = generujAktualituHtml([
                            'soubor'         => $soubor_orig,
                            'nazev'          => $nazev,
                            'kategorie'      => $kategorie,
                            'datum'          => $datum,
                            'perex'          => $perex,
                            'trititulek'     => $trititulek,
                            'hero_img'       => $heroImgPath,
                            'hero_alt'       => $hero_alt ?: $nazev,
                            'obsah'          => $obsah,
                            'priloha_soubor' => $prilohaPath,
                            'priloha_nazev'  => $priloha_nazev_fin,
                        ]);
                        file_put_contents($htmlCesta, $htmlObsah);

                        // Aktualizovat JSON záznam
                        $aktuality[$idx] = array_merge($item, [
                            'nazev'         => $nazev,
                            'perex'         => $perex,
                            'kategorie'     => $kategorie,
                            'datum'         => $datum,
                            'hero_img'      => $heroImgPath,
                            'hero_alt'      => $hero_alt ?: $nazev,
                            'trititulek'    => $trititulek,
                            'obsah'         => $obsah,
                            'priloha'       => $prilohaPath,
                            'priloha_nazev' => $priloha_nazev_fin,
                        ]);
                        ulozJson(AKTUALITY_JSON, $aktuality);

                        $_SESSION['flash_zprava'] = 'Aktualita „' . $nazev . '" byla upravena.';
                        $_SESSION['flash_typ']    = 'ok';
                        header('Location: index.php?sekce=aktuality');
                        exit;
                    }
                }
            }
        }
    }
}

/* ════════════════════════════════════════════════════════════
   FUNKCE PRO GENEROVÁNÍ AKTUALITY
   ════════════════════════════════════════════════════════════ */

function generujAktualituHtml(array $d): string {
    $nazev      = htmlspecialchars($d['nazev'],      ENT_QUOTES, 'UTF-8');
    $perex      = htmlspecialchars($d['perex'],      ENT_QUOTES, 'UTF-8');
    $kat        = htmlspecialchars($d['kategorie'],  ENT_QUOTES, 'UTF-8');
    $datum      = htmlspecialchars($d['datum'],      ENT_QUOTES, 'UTF-8');
    $hero_img   = htmlspecialchars($d['hero_img'],   ENT_QUOTES, 'UTF-8');
    $hero_alt   = htmlspecialchars($d['hero_alt'],   ENT_QUOTES, 'UTF-8');
    $soubor     = htmlspecialchars($d['soubor'],     ENT_QUOTES, 'UTF-8');
    $obsah      = $d['obsah'] ?? '';

    $trit = '';
    if (!empty($d['trititulek'])) {
        $tt   = htmlspecialchars($d['trititulek'], ENT_QUOTES, 'UTF-8');
        $trit = "\n  <h2 class=\"prispevek-tritulek\">\n    {$tt}\n  </h2>\n";
    }

    $priloha_blok = '';
    if (!empty($d['priloha_soubor'])) {
        $href_p  = htmlspecialchars($d['priloha_soubor'], ENT_QUOTES, 'UTF-8');
        $label_p = htmlspecialchars($d['priloha_nazev'] ?? 'Příloha', ENT_QUOTES, 'UTF-8');
        $priloha_blok = "\n  <a href=\"{$href_p}\" target=\"_blank\" class=\"prispevek-priloha\">"
            . "\n    <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\""
            . " fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\">"
            . "<path d=\"M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z\"/>"
            . "<polyline points=\"14 2 14 8 20 8\"/>"
            . "<line x1=\"9\" y1=\"13\" x2=\"15\" y2=\"13\"/>"
            . "<line x1=\"9\" y1=\"17\" x2=\"15\" y2=\"17\"/>"
            . "</svg>\n    {$label_p}\n  </a>";
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="{$perex}">
<title>{$nazev} – Farnost Ostrov</title>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-YC8PFWZWHN"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-YC8PFWZWHN');
</script>
<link rel="icon" type="image/png" href="img/darnost-favicon.png">
<link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700&family=Cinzel:wght@400;600&family=EB+Garamond:ital,wght@0,400;0,500;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<meta property="og:type" content="website">
<meta property="og:url" content="https://www.farnostostrov.cz/{$soubor}">
<meta property="og:site_name" content="Farnost Ostrov">
<meta property="og:title" content="{$nazev} – Farnost Ostrov">
<meta property="og:description" content="{$perex}">
<meta property="og:image" content="https://www.farnostostrov.cz/{$hero_img}">
</head>
<body>

<!-- NAV -->
<nav>
  <a href="index.html" class="nav-logo" style="display:flex;align-items:center;gap:0.7rem;">
    <img src="img/erb-farnosti.png" alt="Erb farnosti" style="height:42px;width:auto;opacity:0.95;">
    <span style="display:flex;align-items:center;gap:0.4rem;">Farnost Ostrov</span>
  </a>
  <button class="nav-toggle" aria-label="Menu" onclick="this.nextElementSibling.classList.toggle('open')" id="nav-toggle">
    <span></span><span></span><span></span>
  </button>
  <ul class="nav-links" id="nav-links">
    <li><a href="o-farnosti.html">O farnosti</a></li>
    <li><a href="zivotni-situace.html">Životní situace</a></li>
    <li><a href="aktuality.html">Aktuality</a></li>
    <li><a href="index.html#ohlasky">Ohlášky</a></li>
    <li><a href="index.html#bohosluzby">Pořad bohoslužeb</a></li>
    <li><a href="index.html#kontakty">Kontakty</a></li>
  </ul>
  <script>
    document.querySelectorAll('#nav-links a').forEach(function(a) {
      a.addEventListener('click', function() {
        document.getElementById('nav-links').classList.remove('open');
      });
    });
  </script>
</nav>

<!-- HERO -->
<div class="prispevek-hero">
  <img
    src="{$hero_img}"
    alt="{$hero_alt}"
    class="prispevek-hero-img"
  >
  <div class="prispevek-hero-overlay"></div>
  <div class="prispevek-hero-meta">
    <p class="prispevek-kategorie">{$kat}</p>
    <p class="prispevek-datum">{$datum}</p>
  </div>
</div>

<!-- OBSAH PŘÍSPĚVKU -->
<article class="prispevek-obsah">

  <a href="aktuality.html" class="prispevek-zpet">Zpět na aktuality</a>

  <h1 class="prispevek-nadpis">{$nazev}</h1>

  <div class="ornament">
    <span class="ornament-line"></span>
    <span class="ornament-cross">✝</span>
    <span class="ornament-line rev"></span>
  </div>
{$trit}
  <div class="prispevek-delici-linka"></div>

  <div class="prispevek-telo">
{$obsah}
  </div>
{$priloha_blok}
</article>

<div id="site-footer"></div>
<script src="footer.js"></script>

</body>
</html>
HTML;
}

/* ════════════════════════════════════════════════════════════
   DATA PRO VÝPIS
   ════════════════════════════════════════════════════════════ */

$prihlaseni = isset($_SESSION['farnost_admin']);
$plakaty    = ($prihlaseni && $sekce === 'nastenka')  ? nactiJson(JSON_SOUBOR)         : [];
$ohlasky    = ($prihlaseni && $sekce === 'ohlasky')   ? nactiJson(OHLASKY_JSON)        : [];
$archiv     = ($prihlaseni && $sekce === 'ohlasky')   ? nactiJson(OHLASKY_ARCHIV_JSON) : [];
$aktuality  = ($prihlaseni && $sekce === 'aktuality') ? nactiJson(AKTUALITY_JSON)      : [];

// Data pro formulář úpravy
$upravit_data = null;
if ($prihlaseni && $sekce === 'aktuality' && !empty($_GET['upravit'])) {
    $hledany = basename($_GET['upravit']);
    foreach ($aktuality as $a) {
        if ($a['soubor'] === $hledany && !empty($a['generovano'])) {
            $upravit_data = $a;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Administrace – Farnost Ostrov</title>
<meta name="robots" content="noindex, nofollow">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --gold:    #c9922a;
    --gold-lt: #e8c97a;
    --dark:    #2a1a05;
    --bg:      #f7f4ef;
    --card:    #ffffff;
    --border:  #e5ddd0;
    --red:     #c0392b;
    --green:   #27714a;
    --radius:  10px;
  }

  body { font-family: system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--dark); min-height: 100vh; font-size: 15px; }

  /* ── Přihlašovací stránka ── */
  .login-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
  .login-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 2.5rem 2rem; width: 100%; max-width: 360px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
  .login-logo { text-align: center; margin-bottom: 1.8rem; }
  .login-logo-kriz { font-size: 2rem; color: var(--gold); display: block; margin-bottom: 0.4rem; }
  .login-logo-nazev { font-size: 0.8rem; letter-spacing: 0.12em; text-transform: uppercase; color: #888; font-weight: 600; }
  .login-card h1 { font-size: 1.2rem; font-weight: 700; margin-bottom: 1.5rem; text-align: center; }

  /* ── Zprávy ── */
  .zprava { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.2rem; font-size: 0.9rem; font-weight: 500; }
  .zprava.ok    { background: #eaf6f0; color: var(--green); border: 1px solid #b6e0cc; }
  .zprava.chyba { background: #fdf0ee; color: var(--red);   border: 1px solid #f0c4be; }

  /* ── Formulářové prvky ── */
  label { display: block; font-size: 0.82rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; color: #666; margin-bottom: 0.4rem; }
  input[type="password"], input[type="text"] { width: 100%; padding: 0.65rem 0.9rem; border: 1px solid var(--border); border-radius: 6px; font-size: 1rem; background: #fff; color: var(--dark); transition: border-color 0.2s; }
  input[type="password"]:focus, input[type="text"]:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,146,42,0.12); }
  .form-group { margin-bottom: 1.1rem; }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; }

  /* ── Tlačítka ── */
  .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.6rem 1.2rem; border: none; border-radius: 6px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: opacity 0.15s, transform 0.1s; text-decoration: none; }
  .btn:active { transform: scale(0.97); }
  .btn-gold   { background: var(--gold); color: #fff; }
  .btn-gold:hover { opacity: 0.88; }
  .btn-full   { width: 100%; justify-content: center; padding: 0.75rem; font-size: 1rem; }
  .btn-smazat { background: none; border: 1px solid #e0b0a8; color: var(--red); padding: 0.35rem 0.75rem; font-size: 0.82rem; border-radius: 5px; cursor: pointer; transition: background 0.15s; }
  .btn-smazat:hover { background: #fdf0ee; }
  .btn-archivovat { background: none; border: 1px solid #b8d4be; color: var(--green); padding: 0.35rem 0.75rem; font-size: 0.82rem; border-radius: 5px; cursor: pointer; transition: background 0.15s; }
  .btn-archivovat:hover { background: #eaf6f0; }
  .btn-pin { background: none; border: 1px solid #d4c8a8; color: #a07838; padding: 0.35rem 0.6rem; font-size: 0.82rem; border-radius: 5px; cursor: pointer; transition: background 0.15s; }
  .btn-pin:hover { background: #f5f0e0; }
  .btn-pin.active { background: #f5f0e0; border-color: #a07838; font-weight: 700; }
  .ohlaska-row--pinned { border-color: rgba(160,120,56,0.4); background: #fdf9f0; }
  .btn-smazat-prilohu { background: none; border: none; color: #c0392b; font-size: 0.8rem; cursor: pointer; padding: 0.2rem 0.4rem; margin-top: 0.3rem; text-decoration: underline; }
  .btn-odhlasit { background: none; border: 1px solid rgba(255,255,255,0.25); color: #ccc; font-size: 0.82rem; padding: 0.35rem 0.8rem; border-radius: 5px; text-decoration: none; transition: background 0.15s; }
  .btn-odhlasit:hover { background: rgba(255,255,255,0.1); color: #fff; }

  /* ── Upload oblast ── */
  .upload-zone { border: 2px dashed var(--border); border-radius: 8px; padding: 1.5rem; text-align: center; cursor: pointer; transition: border-color 0.2s, background 0.2s; position: relative; }
  .upload-zone:hover, .upload-zone.dragover { border-color: var(--gold); background: #fdf8ee; }
  .upload-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; }
  .upload-icon { font-size: 1.8rem; display: block; margin-bottom: 0.4rem; }
  .upload-text { font-size: 0.85rem; color: #888; }
  .upload-text strong { color: var(--gold); }
  .upload-nazev { font-size: 0.8rem; color: var(--dark); margin-top: 0.5rem; font-style: italic; }

  /* ── Hlavička adminu ── */
  .admin-header { background: var(--dark); color: #f5e4b0; padding: 0 1.5rem; height: 56px; display: flex; align-items: center; justify-content: space-between; gap: 1rem; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
  .admin-header-logo { display: flex; align-items: center; gap: 0.6rem; font-weight: 700; font-size: 0.95rem; }
  .admin-header-logo span { color: var(--gold-lt); font-size: 1.1rem; }

  /* ── Sekce navigace ── */
  .admin-sekce-nav { display: flex; gap: 0.25rem; }
  .sekce-btn { padding: 0.4rem 1rem; border-radius: 5px; font-size: 0.85rem; font-weight: 600; text-decoration: none; color: #b8a070; transition: background 0.15s, color 0.15s; }
  .sekce-btn:hover { background: rgba(255,255,255,0.08); color: var(--gold-lt); }
  .sekce-btn.active { background: rgba(201,146,42,0.2); color: var(--gold-lt); }
  @media (max-width: 600px) {
    .admin-header { height: auto; flex-wrap: wrap; padding: 0.6rem 1rem; gap: 0.5rem; }
    .admin-sekce-nav { flex-wrap: wrap; }
    .sekce-btn { font-size: 0.78rem; padding: 0.35rem 0.7rem; }
  }

  /* ── Layout těla ── */
  .admin-body { max-width: 1040px; margin: 0 auto; padding: 2rem 1.5rem; display: grid; grid-template-columns: 300px 1fr; gap: 2rem; align-items: start; }
  @media (max-width: 700px) { .admin-body { grid-template-columns: 1fr; } }

  .card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; box-shadow: 0 1px 6px rgba(0,0,0,0.05); }
  .card + .card { margin-top: 1.2rem; }
  .card-title { font-size: 0.75rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--gold); margin-bottom: 1.2rem; padding-bottom: 0.7rem; border-bottom: 1px solid var(--border); }

  /* ── Grid plakátů (nástěnka) ── */
  .plakaty-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1.2rem; }
  .plakat-item { border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: #faf8f5; display: flex; flex-direction: column; }
  .plakat-nahled { width: 100%; aspect-ratio: 3/4; object-fit: cover; display: block; background: #eee; }
  .plakat-nahled-pdf { width: 100%; aspect-ratio: 3/4; display: flex; align-items: center; justify-content: center; background: #f0ece5; color: var(--gold); font-size: 2.5rem; }
  .plakat-info { padding: 0.6rem 0.7rem 0.7rem; flex: 1; display: flex; flex-direction: column; gap: 0.5rem; }
  .plakat-nazev { font-size: 0.82rem; font-weight: 600; line-height: 1.3; color: var(--dark); flex: 1; }
  .plakat-soubor { font-size: 0.7rem; color: #aaa; word-break: break-all; }
  .prazdny { text-align: center; color: #aaa; font-size: 0.9rem; padding: 2rem 0; }

  /* ── Seznam ohlášek ── */
  .ohlasky-list { display: flex; flex-direction: column; gap: 0.7rem; }
  .ohlaska-row { display: grid; grid-template-columns: 1fr auto; gap: 1rem; align-items: center; padding: 0.9rem 1rem; border: 1px solid var(--border); border-radius: 7px; background: #faf8f5; }
  .ohlaska-row:hover { border-color: rgba(201,146,42,0.3); }
  .ohlaska-row-info { min-width: 0; }
  .ohlaska-row-nazev { font-size: 0.9rem; font-weight: 600; color: var(--dark); margin-bottom: 0.15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .ohlaska-row-datum { font-size: 0.78rem; color: #888; }
  .ohlaska-row-soubor { font-size: 0.7rem; color: #bbb; margin-top: 0.15rem; }
  .ohlaska-row-akce { display: flex; gap: 0.4rem; flex-shrink: 0; }
  .archiv-badge { display: inline-block; background: #f0ece5; color: #a07838; font-size: 0.68rem; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; padding: 0.15rem 0.5rem; border-radius: 3px; margin-left: 0.4rem; vertical-align: middle; }
  .sekce-sekce-title { font-size: 0.75rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #aaa; margin: 1.4rem 0 0.7rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border); }

  /* ── Placeholder sekce ── */
  .placeholder-sekce { text-align: center; padding: 4rem 2rem; color: #aaa; }
  .placeholder-sekce .icon { font-size: 3rem; display: block; margin-bottom: 1rem; opacity: 0.4; }
  .placeholder-sekce p { font-size: 0.95rem; }

  /* ── Quill editor ── */
  .ql-toolbar.ql-snow { border: 1px solid var(--border) !important; border-radius: 6px 6px 0 0 !important; background: #faf8f5; padding: 6px 8px !important; }
  .ql-container.ql-snow { border: 1px solid var(--border) !important; border-top: none !important; border-radius: 0 0 6px 6px !important; font-family: system-ui, -apple-system, sans-serif !important; font-size: 0.95rem !important; }
  .ql-editor { min-height: 260px; color: var(--dark); line-height: 1.65; }
  .ql-editor p { margin-bottom: 0.6em; }
  .ql-editor h2 { font-size: 1.1rem; font-weight: 700; margin: 1em 0 0.4em; }
  .ql-editor h3 { font-size: 0.95rem; font-weight: 700; margin: 0.9em 0 0.35em; text-transform: uppercase; letter-spacing: 0.04em; }
  .ql-editor blockquote { border-left: 3px solid var(--gold); padding-left: 1rem; color: #777; font-style: italic; margin: 0.8em 0; }
  .ql-snow .ql-picker-label, .ql-snow .ql-stroke { color: #555 !important; stroke: #555 !important; }
  .ql-snow .ql-active .ql-stroke, .ql-snow .ql-picker-label:hover, .ql-snow button:hover .ql-stroke { stroke: var(--gold) !important; }
  .ql-snow .ql-active, .ql-snow .ql-picker-label.ql-active { color: var(--gold) !important; }
  .ql-editor.ql-blank::before { color: #bbb; font-style: normal; font-size: 0.9rem; }
</style>
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
</head>
<body>

<?php if (!$prihlaseni): ?>
<!-- ══ PŘIHLAŠOVACÍ FORMULÁŘ ══════════════════════════════════ -->
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <span class="login-logo-kriz">✝</span>
      <span class="login-logo-nazev">Farnost Ostrov</span>
    </div>
    <h1>Administrace webu</h1>

    <?php if ($zprava): ?>
      <div class="zprava <?= h($zprava_typ) ?>"><?= $zprava ?></div>
    <?php endif; ?>

    <form method="post" action="index.php?sekce=<?= h($sekce) ?>">
      <div class="form-group">
        <label for="heslo">Heslo</label>
        <input type="password" id="heslo" name="heslo" autofocus autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-gold btn-full">Přihlásit se</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ══ ADMIN PANEL ════════════════════════════════════════════ -->
<header class="admin-header">
  <div class="admin-header-logo">
    <span>✝</span>
    Farnost Ostrov
  </div>
  <nav class="admin-sekce-nav">
    <a href="index.php?sekce=nastenka"  class="sekce-btn <?= $sekce === 'nastenka'  ? 'active' : '' ?>">Nástěnka</a>
    <a href="index.php?sekce=ohlasky"   class="sekce-btn <?= $sekce === 'ohlasky'   ? 'active' : '' ?>">Ohlášky</a>
    <a href="index.php?sekce=aktuality" class="sekce-btn <?= $sekce === 'aktuality' ? 'active' : '' ?>">Aktuality</a>
  </nav>
  <a href="index.php?odhlasit=1" class="btn-odhlasit">Odhlásit</a>
</header>

<?php /* ══ SEKCE: NÁSTĚNKA ══════════════════════════════════ */ ?>
<?php if ($sekce === 'nastenka'): ?>

<div class="admin-body">

  <div>
    <div class="card">
      <p class="card-title">Přidat plakát</p>
      <?php if ($zprava): ?><div class="zprava <?= h($zprava_typ) ?>"><?= $zprava ?></div><?php endif; ?>
      <form method="post" action="index.php?sekce=nastenka" enctype="multipart/form-data">
        <input type="hidden" name="akce" value="pridat">
        <div class="form-group">
          <label for="nazev">Název plakátu</label>
          <input type="text" id="nazev" name="nazev" placeholder="např. Pouť ke sv. Michaelovi" required>
        </div>
        <div class="form-group">
          <label>Soubor (JPG, PNG, WEBP, PDF)</label>
          <div class="upload-zone" id="uploadZoneNastenka">
            <input type="file" name="soubor" id="souborNastenka" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf" required>
            <span class="upload-icon">📎</span>
            <p class="upload-text">Přetáhněte nebo <strong>klikněte pro výběr</strong></p>
            <p class="upload-nazev" id="nazevNastenka"></p>
          </div>
        </div>
        <button type="submit" class="btn btn-gold btn-full">Přidat na nástěnku</button>
      </form>
    </div>
    <p style="margin-top:1rem;font-size:0.78rem;color:#aaa;text-align:center;">
      Web: <a href="../index.html#akce" target="_blank" style="color:var(--gold)">farnostostrov.cz/#akce</a>
    </p>
  </div>

  <div class="card">
    <p class="card-title">Aktuální plakáty (<?= count($plakaty) ?>)</p>
    <?php if (empty($plakaty)): ?>
      <p class="prazdny">Nástěnka je prázdná.</p>
    <?php else: ?>
      <div class="plakaty-grid">
        <?php foreach ($plakaty as $p): ?>
          <div class="plakat-item">
            <?php if ($p['typ'] === 'pdf'): ?>
              <div class="plakat-nahled-pdf">📄</div>
            <?php else: ?>
              <img class="plakat-nahled" src="../akce/<?= h($p['soubor']) ?>" alt="<?= h($p['nazev']) ?>" loading="lazy">
            <?php endif; ?>
            <div class="plakat-info">
              <p class="plakat-nazev"><?= h($p['nazev']) ?></p>
              <p class="plakat-soubor"><?= h($p['soubor']) ?></p>
              <form method="post" action="index.php?sekce=nastenka"
                    onsubmit="return confirm('Odebrat plakát „<?= h(addslashes($p['nazev'])) ?>"?')">
                <input type="hidden" name="akce" value="smazat">
                <input type="hidden" name="soubor" value="<?= h($p['soubor']) ?>">
                <button type="submit" class="btn-smazat">Odebrat</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php /* ══ SEKCE: OHLÁŠKY ════════════════════════════════════ */ ?>
<?php elseif ($sekce === 'ohlasky'): ?>

<div class="admin-body">

  <div>
    <div class="card">
      <p class="card-title">Přidat ohlášky</p>
      <?php if ($zprava): ?><div class="zprava <?= h($zprava_typ) ?>"><?= $zprava ?></div><?php endif; ?>
      <form method="post" action="index.php?sekce=ohlasky" enctype="multipart/form-data">
        <input type="hidden" name="akce" value="pridat">
        <div class="form-group">
          <label for="nazev">Název</label>
          <input type="text" id="nazev" name="nazev" placeholder="Ohlášky – 3. neděle velikonoční" required>
        </div>
        <div class="form-group">
          <label for="poznamka">Poznámka k ohlášce</label>
          <input type="text" id="poznamka" name="poznamka" placeholder="např. platí i v pondělí">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="datum_expirace">Platí do (datum expirace)</label>
            <input type="date" id="datum_expirace" name="datum_expirace" required>
          </div>
          <div class="form-group" style="display:flex;align-items:center;gap:0.6rem;padding-top:1.6rem;">
            <input type="checkbox" id="pinnováno" name="pinnováno" value="1" style="width:1.1rem;height:1.1rem;cursor:pointer;">
            <label for="pinnováno" style="margin:0;cursor:pointer;font-size:0.88rem;">Připnout na první pozici</label>
          </div>
        </div>
        <div class="form-group">
          <label>Soubor PDF</label>
          <div class="upload-zone" id="uploadZoneOhlasky">
            <input type="file" name="soubor" id="souborOhlasky" accept=".pdf">
            <span class="upload-icon">📄</span>
            <p class="upload-text">Přetáhněte nebo <strong>klikněte pro výběr</strong></p>
            <p class="upload-nazev" id="nazevOhlasky"></p>
          </div>
          <button type="button" class="btn-smazat-prilohu" id="clearOhlasky" style="display:none">× Odebrat soubor</button>
        </div>
        <button type="submit" class="btn btn-gold btn-full">Přidat ohlášky</button>
      </form>
    </div>
    <p style="margin-top:1rem;font-size:0.78rem;color:#aaa;text-align:center;">
      Max. <?= MAX_OHLASKY ?> ohlášky na webu · starší jdou do archivu<br>
      Web: <a href="../index.html#ohlasky" target="_blank" style="color:var(--gold)">farnostostrov.cz/#ohlasky</a>
    </p>
  </div>

  <div>
    <div class="card">
      <p class="card-title">Aktuální ohlášky (<?= count($ohlasky) ?>)</p>
      <?php if (empty($ohlasky)): ?>
        <p class="prazdny">Žádné ohlášky.</p>
      <?php else: ?>
        <div class="ohlasky-list">
          <?php foreach ($ohlasky as $o):
            $je_pinned = !empty($o['pinnováno']);
            $exp_text  = !empty($o['datum_expirace'])
                ? 'platí do ' . date('j. n. Y', strtotime($o['datum_expirace']))
                : ((!empty($o['datum1']) ? $o['datum1'] : '') . (!empty($o['datum2']) ? ' · ' . $o['datum2'] : ''));
          ?>
            <div class="ohlaska-row <?= $je_pinned ? 'ohlaska-row--pinned' : '' ?>">
              <div class="ohlaska-row-info">
                <div class="ohlaska-row-nazev">
                  <?= $je_pinned ? '📌 ' : '' ?><?= h($o['nazev']) ?>
                </div>
                <?php if (!empty($o['poznamka'])): ?>
                  <div class="ohlaska-row-datum" style="font-style:italic;"><?= h($o['poznamka']) ?></div>
                <?php endif; ?>
                <div class="ohlaska-row-datum"><?= h($exp_text) ?></div>
                <div class="ohlaska-row-soubor">
                  <a href="../ohlasky/<?= h(rawurlencode($o['soubor'])) ?>" target="_blank" style="color:var(--gold)">📄 <?= h($o['soubor']) ?></a>
                </div>
              </div>
              <div class="ohlaska-row-akce">
                <form method="post" action="index.php?sekce=ohlasky">
                  <input type="hidden" name="akce" value="pinovat">
                  <input type="hidden" name="soubor" value="<?= h($o['soubor']) ?>">
                  <button type="submit" class="btn-pin <?= $je_pinned ? 'active' : '' ?>" title="<?= $je_pinned ? 'Odepnout' : 'Připnout' ?>">📌</button>
                </form>
                <form method="post" action="index.php?sekce=ohlasky"
                      onsubmit="return confirm('Archivovat tyto ohlášky?')">
                  <input type="hidden" name="akce" value="archivovat">
                  <input type="hidden" name="soubor" value="<?= h($o['soubor']) ?>">
                  <button type="submit" class="btn-archivovat">Archivovat</button>
                </form>
                <form method="post" action="index.php?sekce=ohlasky"
                      onsubmit="return confirm('Smazat ohlášky „<?= h(addslashes($o['nazev'])) ?>" i soubor?')">
                  <input type="hidden" name="akce" value="smazat">
                  <input type="hidden" name="soubor" value="<?= h($o['soubor']) ?>">
                  <button type="submit" class="btn-smazat">Smazat</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($archiv)): ?>
        <p class="sekce-sekce-title">Archiv <span class="archiv-badge"><?= count($archiv) ?></span></p>
        <div class="ohlasky-list">
          <?php foreach ($archiv as $o):
            $arch_text = !empty($o['datum_expirace'])
                ? 'platí do ' . date('j. n. Y', strtotime($o['datum_expirace']))
                : ((!empty($o['datum1']) ? $o['datum1'] : '') . (!empty($o['datum2']) ? ' · ' . $o['datum2'] : ''));
          ?>
            <div class="ohlaska-row" style="background:#f5f2ed;opacity:0.85;">
              <div class="ohlaska-row-info">
                <div class="ohlaska-row-nazev"><?= h($o['nazev']) ?></div>
                <div class="ohlaska-row-datum"><?= h($arch_text) ?></div>
                <div class="ohlaska-row-soubor">
                  <a href="../ohlasky/<?= h(rawurlencode($o['soubor'])) ?>" target="_blank" style="color:var(--gold)">📄 <?= h($o['soubor']) ?></a>
                </div>
              </div>
              <div class="ohlaska-row-akce">
                <form method="post" action="index.php?sekce=ohlasky"
                      onsubmit="return confirm('Trvale smazat archivované ohlášky „<?= h(addslashes($o['nazev'])) ?>" i soubor?')">
                  <input type="hidden" name="akce" value="smazat_archiv">
                  <input type="hidden" name="soubor" value="<?= h($o['soubor']) ?>">
                  <button type="submit" class="btn-smazat">Smazat</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php /* ══ SEKCE: AKTUALITY ══════════════════════════════════ */ ?>
<?php elseif ($sekce === 'aktuality'): ?>
<?php
  $ed = $upravit_data; // zkrácený alias; null = nová aktualita
?>

<div class="admin-body" style="grid-template-columns: 1fr 1fr; max-width: 1600px; padding: 1.5rem 1rem;">

  <!-- Levý sloupec: formulář (nová / editace) -->
  <div>
    <div class="card">
      <p class="card-title"><?= $ed ? 'Upravit aktualitu' : 'Přidat aktualitu' ?></p>
      <?php if ($zprava): ?><div class="zprava <?= h($zprava_typ) ?>"><?= $zprava ?></div><?php endif; ?>

      <?php if ($ed): ?>
        <p style="font-size:0.8rem;color:#888;margin-bottom:1rem;">
          Soubor: <code><?= h($ed['soubor']) ?></code>
          &nbsp;<a href="index.php?sekce=aktuality" style="color:var(--gold);font-size:0.78rem;">× zrušit</a>
        </p>
      <?php endif; ?>

      <form method="post" action="index.php?sekce=aktuality" enctype="multipart/form-data">
        <?php if ($ed): ?>
          <input type="hidden" name="akce" value="upravit">
          <input type="hidden" name="soubor_orig" value="<?= h($ed['soubor']) ?>">
        <?php else: ?>
          <input type="hidden" name="akce" value="pridat">
        <?php endif; ?>

        <?php if (!$ed): ?>
        <div class="form-group">
          <label for="slug">Slug (URL, bez diakritiky, pomlčky)</label>
          <input type="text" id="slug" name="slug" placeholder="novy-prispevek" pattern="[a-z0-9\-]+" required>
          <p style="font-size:0.72rem;color:#aaa;margin-top:0.3rem;">Vytvoří soubor <code>aktualita-{slug}.html</code></p>
        </div>
        <?php endif; ?>

        <div class="form-group">
          <label for="a_nazev">Název článku</label>
          <input type="text" id="a_nazev" name="nazev" value="<?= $ed ? h($ed['nazev']) : '' ?>" placeholder="Pouť ke sv. Michaelovi" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="a_kategorie">Kategorie</label>
            <input type="text" id="a_kategorie" name="kategorie" value="<?= $ed ? h($ed['kategorie']) : '' ?>" placeholder="Aktualita" required>
          </div>
          <div class="form-group">
            <label for="a_datum">Datum</label>
            <input type="text" id="a_datum" name="datum" value="<?= $ed ? h($ed['datum']) : '' ?>" placeholder="15. dubna 2026" required>
          </div>
        </div>

        <div class="form-group">
          <label for="a_perex">Perex (zobrazí se na home page)</label>
          <textarea id="a_perex" name="perex" rows="3" required
            style="width:100%;padding:0.65rem 0.9rem;border:1px solid var(--border);border-radius:6px;font-size:0.95rem;background:#fff;color:var(--dark);resize:vertical;"
            placeholder="Stručný popis aktuality."><?= $ed ? h($ed['perex']) : '' ?></textarea>
        </div>

        <div class="form-group">
          <label>Hero obrázek (JPG, PNG, WEBP)<?= $ed ? ' – ponechte prázdné pro zachování' : '' ?></label>
          <?php if ($ed && !empty($ed['hero_img'])): ?>
            <div style="margin-bottom:0.5rem;display:flex;align-items:center;gap:0.6rem;">
              <img src="../<?= h($ed['hero_img']) ?>" style="height:40px;border-radius:3px;border:1px solid var(--border);">
              <span style="font-size:0.75rem;color:#aaa;"><?= h($ed['hero_img']) ?></span>
            </div>
          <?php endif; ?>
          <div class="upload-zone" id="uploadZoneAkt">
            <input type="file" name="hero_img" id="souborAkt" accept=".jpg,.jpeg,.png,.webp" <?= $ed ? '' : 'required' ?>>
            <span class="upload-icon">🖼️</span>
            <p class="upload-text">Přetáhněte nebo <strong>klikněte pro výběr</strong></p>
            <p class="upload-nazev" id="nazevAkt"></p>
          </div>
        </div>

        <div class="form-group">
          <label for="a_hero_alt">Popis obrázku (alt text)</label>
          <input type="text" id="a_hero_alt" name="hero_alt" value="<?= $ed ? h($ed['hero_alt'] ?? '') : '' ?>" placeholder="Pouť ke sv. Michaelovi">
        </div>

        <div class="form-group">
          <label for="a_trititulek">Podnadpis (volitelný)</label>
          <input type="text" id="a_trititulek" name="trititulek" value="<?= $ed ? h($ed['trititulek'] ?? '') : '' ?>" placeholder="Krátký výstižný podnadpis článku">
        </div>

        <div class="form-group">
          <label>Obsah článku</label>
          <div id="quill-editor"></div>
          <textarea id="a_obsah" name="obsah" style="display:none;"></textarea>
          <script>window._obsahInit = <?= json_encode($ed ? ($ed['obsah'] ?? '') : '') ?>;</script>
        </div>

        <div style="border-top:1px solid var(--border);margin:1rem 0 1.1rem;"></div>

        <div class="form-group">
          <label>Příloha PDF<?= $ed ? ' – ponechte prázdné pro zachování' : ' (volitelná)' ?></label>
          <?php if ($ed && !empty($ed['priloha'])): ?>
            <p style="font-size:0.75rem;color:#aaa;margin-bottom:0.4rem;">
              Aktuální: <a href="../<?= h($ed['priloha']) ?>" target="_blank" style="color:var(--gold)"><?= h($ed['priloha']) ?></a>
            </p>
          <?php endif; ?>
          <div class="upload-zone" id="uploadZonePriloha">
            <input type="file" name="priloha_pdf" id="souborPriloha" accept=".pdf">
            <span class="upload-icon">📎</span>
            <p class="upload-text">Přetáhněte nebo <strong>klikněte pro výběr</strong></p>
            <p class="upload-nazev" id="nazevPriloha"></p>
          </div>
        </div>
        <div class="form-group">
          <label for="a_priloha_nazev">Název přílohy (text odkazu)</label>
          <input type="text" id="a_priloha_nazev" name="priloha_nazev" value="<?= $ed ? h($ed['priloha_nazev'] ?? '') : '' ?>" placeholder="např. Dopis biskupa Holuba">
        </div>

        <button type="submit" class="btn btn-gold btn-full">
          <?= $ed ? 'Uložit změny' : 'Vytvořit aktualitu' ?>
        </button>
      </form>
    </div>
    <p style="margin-top:1rem;font-size:0.78rem;color:#aaa;text-align:center;">
      Web: <a href="../aktuality.html" target="_blank" style="color:var(--gold)">farnostostrov.cz/aktuality.html</a>
    </p>
  </div>

  <!-- Pravý sloupec: seznam aktualit -->
  <div class="card">
    <p class="card-title">Aktuality (<?= count($aktuality) ?>)</p>

    <?php if (empty($aktuality)): ?>
      <p class="prazdny">Žádné aktuality.</p>
    <?php else: ?>
      <div class="ohlasky-list">
        <?php foreach ($aktuality as $a): ?>
          <?php
            $isRucni   = empty($a['generovano']);
            $isEdited  = $ed && $ed['soubor'] === $a['soubor'];
            $btnKlasa  = $isRucni ? 'btn-archivovat' : 'btn-smazat';
            $btnText   = $isRucni ? 'Odebrat' : 'Smazat';
            $confirmTx = $isRucni
              ? 'Odebrat ze seznamu? Stránka zůstane zachována.'
              : 'Smazat aktualitu včetně stránky a obrázku?';
          ?>
          <div class="ohlaska-row" style="grid-template-columns: auto 1fr auto; gap: 0.8rem;<?= $isEdited ? 'border-color:var(--gold);background:#fdf8ee;' : '' ?>">
            <!-- Miniatura -->
            <div style="flex-shrink:0;">
              <img src="../<?= h($a['hero_img']) ?>" alt="<?= h($a['hero_alt'] ?? '') ?>"
                style="width:72px;height:48px;object-fit:cover;border-radius:4px;display:block;border:1px solid var(--border);" loading="lazy">
            </div>
            <!-- Info -->
            <div class="ohlaska-row-info" style="min-width:0;">
              <div class="ohlaska-row-nazev"><?= h($a['nazev']) ?></div>
              <div class="ohlaska-row-datum"><?= h($a['kategorie']) ?> · <?= h($a['datum']) ?></div>
              <div class="ohlaska-row-soubor">
                <a href="../<?= h($a['soubor']) ?>" target="_blank" style="color:var(--gold)">↗ <?= h($a['soubor']) ?></a>
                <?php if ($isRucni): ?>
                  <span class="archiv-badge" style="background:#eaf6f0;color:#27714a;">ruční</span>
                <?php else: ?>
                  <span class="archiv-badge">vygenerováno</span>
                <?php endif; ?>
              </div>
            </div>
            <!-- Akce -->
            <div class="ohlaska-row-akce">
              <?php if (!$isRucni): ?>
                <a href="index.php?sekce=aktuality&upravit=<?= h($a['soubor']) ?>"
                   class="btn-archivovat" style="text-decoration:none;display:inline-block;">Upravit</a>
              <?php endif; ?>
              <form method="post" action="index.php?sekce=aktuality"
                    onsubmit="return confirm(<?= json_encode($confirmTx) ?>)">
                <input type="hidden" name="akce" value="smazat">
                <input type="hidden" name="soubor" value="<?= h($a['soubor']) ?>">
                <button type="submit" class="<?= $btnKlasa ?>"><?= $btnText ?></button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p style="margin-top:1.2rem;font-size:0.75rem;color:#aaa;">
        První <?= MAX_AKTUALITY_HP ?> aktuality se zobrazí na home page. Ostatní jsou dostupné na <a href="../aktuality.html" target="_blank" style="color:var(--gold)">aktuality.html</a>.
      </p>
    <?php endif; ?>
  </div>

</div>

<?php endif; ?>
<?php endif; ?>

<script src="https://cdn.quilljs.com/1.3.7/quill.js"></script>
<script>
(function () {
  // ── Quill textový editor ──────────────────────────────────
  var editorEl = document.getElementById('quill-editor');
  if (editorEl) {
    var quill = new Quill('#quill-editor', {
      theme: 'snow',
      placeholder: 'Napište text článku…',
      modules: {
        toolbar: [
          [{ header: [2, 3, false] }],
          ['bold', 'italic'],
          ['blockquote'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['link'],
          ['clean']
        ]
      }
    });

    // Naplnit existujícím obsahem při editaci
    if (window._obsahInit) {
      quill.clipboard.dangerouslyPasteHTML(window._obsahInit);
    }

    // Před odesláním formuláře přepsat skrytou textarea
    editorEl.closest('form').addEventListener('submit', function () {
      document.getElementById('a_obsah').value = quill.root.innerHTML;
    });
  }

  // ── Pomocná funkce pro upload zóny ───────────────────────
  function nastavUpload(zoneId, inputId, nazevId, clearId) {
    var zone   = document.getElementById(zoneId);
    var input  = document.getElementById(inputId);
    var nazev  = document.getElementById(nazevId);
    var clear  = clearId ? document.getElementById(clearId) : null;
    if (!zone || !input) return;
    input.addEventListener('change', function () {
      var soubor = this.files[0] ? this.files[0].name : '';
      nazev.textContent = soubor;
      if (clear) clear.style.display = soubor ? 'inline-block' : 'none';
    });
    if (clear) {
      clear.addEventListener('click', function () {
        input.value = '';
        nazev.textContent = '';
        clear.style.display = 'none';
      });
    }
    zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', function ()  { zone.classList.remove('dragover'); });
    zone.addEventListener('drop',      function ()  { zone.classList.remove('dragover'); });
  }
  nastavUpload('uploadZoneNastenka', 'souborNastenka', 'nazevNastenka');
  nastavUpload('uploadZoneOhlasky',  'souborOhlasky',  'nazevOhlasky', 'clearOhlasky');
  nastavUpload('uploadZoneAkt',      'souborAkt',      'nazevAkt');
  nastavUpload('uploadZonePriloha',  'souborPriloha',  'nazevPriloha');
}());
</script>
</body>
</html>
