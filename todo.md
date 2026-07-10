# TODO — FantaOracle

## 📧 Email

- [ ] **Rivedere tutti i testi delle email** (verifica, reset password, invito team, ecc.)
  - Sostituire i testi di default Laravel con testi personalizzati in italiano per il brand FantaOracle
  - Verificare/aggiornare il tagline nell'header: attualmente `"Fantasy Football Platform"` — decidere testo definitivo
  - Controllare tutti i template Fortify/Jetstream (verifica email, reset password, invito squadra)
  - File da aggiornare: `resources/views/vendor/mail/html/header.blade.php`

- [ ] **Configurare DKIM su IONOS** per `fantaoracle.it`
  - Le email arrivano nello Spam di Gmail perché manca la firma DKIM
  - Andare su pannello IONOS → Email → Autenticazione → Abilitare DKIM
  - Aggiungere i record TXT generati al DNS del dominio
  - SPF è già OK (`v=spf1 include:_spf-eu.ionos.com ~all`)
