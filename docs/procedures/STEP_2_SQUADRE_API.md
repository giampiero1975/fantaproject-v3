# STEP 2 - Squadre API

**Progetto:** Fanta Oracle V3  
**Modulo:** Setup Dati - Squadre  
**Stato:** Implementazione runtime provider layer  
**Interfaccia:** `/admin/teams`

---

## 1. Obiettivo

Lo Step 2 sincronizza e consulta le squadre attraverso il layer provider, senza esporre al dominio il nome del provider sorgente.

Il sistema deve:

- partire da una competizione interna gia' collegata ai provider;
- usare provider attivi e configurati per la capability `teams`;
- normalizzare i payload esterni nel contratto interno delle squadre;
- salvare squadre e presenza stagionale;
- mostrare in UI lo stato della copertura senza rendere il provider una sorgente dati di dominio.

---

## 2. Principio architetturale

La fonte applicativa e':

```text
Fanta Oracle Provider Layer
```

Non:

```text
Football-Data
API-Football
Soccer-Data-Api
```

I provider sono strumenti di alimentazione. La UI amministrativa puo' mostrare diagnostica e copertura, ma il dato finale deve essere letto come dato canonico Fanta Oracle.

---

## 3. Capability richiesta

```text
teams
```

La capability viene scelta dal catalogo DB:

```text
data_provider_capabilities
```

e non da liste cablate nel codice.

---

## 4. Dati principali

### `teams`

Anagrafica canonica della squadra.

```text
id
name
short_name
tla
logo_url
created_at
updated_at
```

I campi legacy legati a scraping o fonti specifiche V2 non devono guidare la V3.

### `team_season`

Collega una squadra a una lega/stagione.

```text
team_id
season_id
league_id
is_active
```

### Mapping provider

I collegamenti esterni vivono in tabelle di mapping dedicate. Il dominio non deve dedurre ID provider da nomi o payload non normalizzati.

---

## 5. UI

La voce di menu resta:

```text
Squadre
```

La UI include:

- riepilogo copertura;
- tabella squadre canoniche;
- filtro ad imbuto con componenti condivisi;
- ricerca rapida testuale;
- azioni di analisi/sincronizzazione;
- diagnostica solo dove serve.

La colonna provider non e' una colonna di dominio: eventuali dettagli provider vanno trattati come diagnostica, non come sorgente finale del dato.

---

## 6. Logging

Il log funzionale segue la nomenclatura del menu:

```text
storage/logs/administration/squadre/squadre.log
```

Le chiamate HTTP verso provider sono inoltre tracciate in:

```text
data_provider_api_call_audits
```

senza payload raw e senza credenziali.

---

## 7. Regole

- niente scraping;
- niente provider hardcoded;
- niente logica provider nella UI;
- mapping e capability devono arrivare da DB;
- il runtime deve usare solo provider attivi e configurati per `teams`;
- se manca la capability `teams`, il provider non puo' coprire lo Step 2 anche se copre `competitions` o `seasons`.

