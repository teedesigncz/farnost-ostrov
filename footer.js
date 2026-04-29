(function () {
  var placeholder = document.getElementById('site-footer');
  if (!placeholder) return;

  placeholder.outerHTML = `<footer id="kontakty">
  <div class="footer-grid">
    <div>
      <p class="footer-col-title">Farnost</p>
      <div class="footer-address">
        Římskokatolická farnost Ostrov<br>
        Malé náměstí 25<br>
        363 01 Ostrov<br><br>
        <a href="mailto:farnost.ostrov@bip.cz">farnost.ostrov@bip.cz</a><br>
        <a href="https://www.facebook.com/farnostostrov" target="_blank"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:5px;margin-bottom:2px"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.235 2.686.235v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.268h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg> Facebook farnosti</a><br><br>
        Číslo účtu: 801809379/0800
      </div>
    </div>
    <div>
      <p class="footer-col-title">Kontaktní osoby</p>
      <div class="footer-person">
        <div class="footer-person-name">Milan Geiger</div>
        <div class="footer-person-detail">
          <a href="mailto:geiger@bip.cz">geiger@bip.cz</a><br>
          <a href="tel:+420731697408">+420 731 697 408</a>
        </div>
      </div>
      <div class="footer-person" style="margin-top:1.2rem; padding-top:1rem; border-top:1px solid rgba(201,146,42,0.2);">
        <div class="footer-person-name" style="letter-spacing:0.18em; font-size:0.72rem; color:var(--gold);">Kancelář</div>
        <div class="footer-person-detail">
          <a href="mailto:farnost.ostrov@bip.cz">farnost.ostrov@bip.cz</a><br>
          <a href="tel:+420604835064">+420 604 835 064</a>
        </div>
      </div>
    </div>
    <div>
      <p class="footer-col-title">Zůstaňte v kontaktu</p>
      <p style="font-size:0.85rem;color:rgba(245,228,176,0.6);margin:0 0 1rem;">Zanechte nám svůj kontakt a budeme vás informovat o dění ve farnosti.</p>
      <form class="footer-form" id="footer-contact-form">
        <input type="hidden" name="access_key" value="16374971-e3b5-49fe-bbec-5cff6190f678">
        <input type="hidden" name="subject" value="Nový kontakt z webu farnosti Ostrov">
        <input type="text" name="name" placeholder="Vaše jméno" required>
        <input type="email" name="email" placeholder="Váš e-mail" required>
        <button type="submit">Přihlásit se</button>
        <p class="footer-form-success" id="fc-success">✓ &nbsp;Děkujeme, kontakt byl uložen.</p>
      </form>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="footer-logo">Farnost Ostrov ✝</div>
    <div class="footer-copy">© 2026 TEE Design CZ – vibe4you</div>
  </div>
</footer>`;

  document.getElementById('footer-contact-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.textContent = 'Odesílám…';
    btn.disabled = true;
    const payload = {
      access_key: '16374971-e3b5-49fe-bbec-5cff6190f678',
      subject: 'Nový kontakt z webu farnosti Ostrov',
      name: this.querySelector('[name="name"]').value,
      email: this.querySelector('[name="email"]').value
    };
    try {
      const res = await fetch('https://api.web3forms.com/submit', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
      });
      if ((await res.json()).success) {
        document.getElementById('fc-success').style.display = 'block';
        this.reset();
      }
    } catch (err) {}
    btn.textContent = 'Přihlásit se';
    btn.disabled = false;
  });
})();
