/* ─────────────────────────────────────────────────────────────
   gallery.js  –  Farnost Ostrov  –  sdílený skript fotogalerií
   Použití na stránce:
     <script src="gallery.js" defer></script>
   HTML šablona jedné fotografie:
     <figure class="galerie-item"
             data-src="img/galerie/foto.jpg"
             data-caption="Popis fotografie">
       <img src="img/galerie/foto.jpg" alt="Popis fotografie">
       <div class="galerie-item-overlay">
         <span class="galerie-item-caption">Popis fotografie</span>
       </div>
     </figure>
   ───────────────────────────────────────────────────────────── */
(function () {
  'use strict';

  /* ── Vytvoření lightboxu ── */
  function buildLightbox() {
    var el = document.createElement('div');
    el.id = 'galerie-lb';
    el.className = 'galerie-lb';
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-label', 'Prohlížeč fotografií');
    el.innerHTML =
      '<button class="galerie-lb-close" id="galerie-lb-close" aria-label="Zavřít">&#10005;</button>' +
      '<button class="galerie-lb-prev" id="galerie-lb-prev" aria-label="Předchozí fotografie">&#8592;</button>' +
      '<div class="galerie-lb-stage" id="galerie-lb-stage">' +
        '<img src="" alt="" class="galerie-lb-img" id="galerie-lb-img">' +
      '</div>' +
      '<button class="galerie-lb-next" id="galerie-lb-next" aria-label="Další fotografie">&#8594;</button>' +
      '<div class="galerie-lb-meta">' +
        '<p class="galerie-lb-caption" id="galerie-lb-caption"></p>' +
        '<p class="galerie-lb-counter" id="galerie-lb-counter"></p>' +
      '</div>';
    document.body.appendChild(el);
    return el;
  }

  /* ── Stav ── */
  var lb, lbImg, lbCaption, lbCounter, lbPrev, lbNext;
  var currentItems = [];
  var currentIdx   = 0;

  /* ── Zobrazení a zavření ── */
  function open(galleryItems, startIdx) {
    currentItems = galleryItems;
    currentIdx   = startIdx;
    render();
    lb.classList.add('open');
    document.body.style.overflow = 'hidden';
    lbImg.focus();
  }

  function close() {
    lb.classList.remove('open');
    document.body.style.overflow = '';
    setTimeout(function () { lbImg.src = ''; }, 300);
  }

  /* ── Listování ── */
  function navigate(dir) {
    currentIdx = (currentIdx + dir + currentItems.length) % currentItems.length;
    lbImg.style.opacity = '0';
    setTimeout(function () {
      render();
      lbImg.style.opacity = '1';
    }, 180);
  }

  /* ── Aktualizace obsahu lightboxu ── */
  function render() {
    var item    = currentItems[currentIdx];
    var imgEl   = item.querySelector('img');
    var src     = item.dataset.src     || (imgEl && imgEl.getAttribute('src')) || '';
    var caption = item.dataset.caption || (imgEl && imgEl.getAttribute('alt')) || '';
    lbImg.setAttribute('src', src);
    lbImg.setAttribute('alt', caption);
    lbCaption.textContent = caption;
    lbCounter.textContent = (currentIdx + 1) + '\u00a0/\u00a0' + currentItems.length;
    /* Šipky – skrýt při jediné fotografii */
    var multi = currentItems.length > 1;
    lbPrev.style.visibility = multi ? 'visible' : 'hidden';
    lbNext.style.visibility = multi ? 'visible' : 'hidden';
  }

  /* ── Inicializace ── */
  function init() {
    lb        = buildLightbox();
    lbImg     = document.getElementById('galerie-lb-img');
    lbCaption = document.getElementById('galerie-lb-caption');
    lbCounter = document.getElementById('galerie-lb-counter');
    lbPrev    = document.getElementById('galerie-lb-prev');
    lbNext    = document.getElementById('galerie-lb-next');

    /* Tlačítka lightboxu */
    document.getElementById('galerie-lb-close').addEventListener('click', close);
    lbPrev.addEventListener('click', function (e) { e.stopPropagation(); navigate(-1); });
    lbNext.addEventListener('click', function (e) { e.stopPropagation(); navigate(1); });

    /* Klik na tmavé pozadí = zavřít */
    lb.addEventListener('click', function (e) {
      if (e.target === lb || e.target.id === 'galerie-lb-stage') close();
    });

    /* Swipe gesta (dotykové displeje) */
    var touchStartX = 0;
    var touchStartY = 0;
    lb.addEventListener('touchstart', function (e) {
      touchStartX = e.changedTouches[0].clientX;
      touchStartY = e.changedTouches[0].clientY;
    }, { passive: true });
    lb.addEventListener('touchend', function (e) {
      var dx = e.changedTouches[0].clientX - touchStartX;
      var dy = e.changedTouches[0].clientY - touchStartY;
      /* Reagovat jen na převážně horizontální swipe > 50 px */
      if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) {
        if (dx < 0) navigate(1);   /* swipe doleva  = další */
        else        navigate(-1);  /* swipe doprava = předchozí */
      }
    }, { passive: true });

    /* Klávesnice */
    document.addEventListener('keydown', function (e) {
      if (!lb.classList.contains('open')) return;
      if      (e.key === 'Escape')      close();
      else if (e.key === 'ArrowLeft')  { e.preventDefault(); navigate(-1); }
      else if (e.key === 'ArrowRight') { e.preventDefault(); navigate(1); }
    });

    /* Napojení na každou galerii na stránce */
    document.querySelectorAll('.galerie').forEach(function (galerie) {
      var items = Array.from(galerie.querySelectorAll('.galerie-item'));
      items.forEach(function (item, i) {
        item.setAttribute('role', 'button');
        item.setAttribute('tabindex', '0');
        item.addEventListener('click', function () { open(items, i); });
        item.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(items, i); }
        });
      });
    });
  }

  /* ── Spustit po načtení DOMu ── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
}());
