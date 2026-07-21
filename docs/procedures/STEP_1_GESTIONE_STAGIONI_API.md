# STEP 1 — Gestione Stagioni API

**Progetto:** Fanta Oracle V3  
**Modulo:** Setup Dati · Gestione Stagioni  
**Stato:** Completato  
**Interfaccia:** `/admin/manage-season`

---

## 1. Obiettivo

Lo Step 1 inizializza e mantiene la timeline stagionale di una competizione senza dipendere direttamente da un provider specifico.

Il sistema deve:

- individuare la stagione corrente;
- risolvere automaticamente la lega interna tramite i mapping provider;
- costruire la timeline corrente più lo storico configurato;
- verificare quali provider possono soddisfare ogni stagione;
- salvare date, stato corrente e mapping esterni;
- eseguire sempre un dry-run prima della persistenza;
- rimanere idempotente nei run successivi.

---

## 2. Principio architetturale

Il dominio non sceglie un provider per nome.

```text
Richiesta canonica
      │
      ▼
Provider Registry
      │
      ▼
Capability runtime
      │
      ├─ più provider disponibili → congruity audit
      ├─ un provider disponibile  → single provider validation
      └─ nessun provider          → coverage gap
```

Il passaggio futuro da Football-Data.org o API-Football a Sportmonks, Opta o altro provider avviene aggiungendo un adapter compatibile, senza modificare il contratto del dominio.

Per i provider HTTP semplici o sperimentali, l'implementazione non deve partire da una classe PHP dedicata. La strategia prevista e' descritta in:

```text
docs/architecture/HTTP_PROVIDER_ADAPTER_UI.md
```

L'ordine corretto di configurazione e':

```text
competitions
-> seasons
-> teams
```

---

## 3. Provider attuali

### Football-Data.org

Utilizzato quando la competizione e la stagione sono disponibili con le credenziali correnti.

### API-Football

Utilizzato come provider concorrente o fallback per le stagioni non disponibili su Football-Data.org.

### FBref

Non è utilizzato nello Step 1 V3.

Il precedente fallback basato su scraping è stato sostituito da API ufficiali registrate nel provider layer.

---

## 4. Configurazione

```dotenv
SEASON_HISTORY_FALLBACK=4
```

Il valore rappresenta il numero di stagioni precedenti da mantenere oltre alla stagione corrente.

Con stagione corrente `2026` e fallback `4`, la timeline è:

```text
2026/27  current
2025/26
2024/25
2023/24
2022/23
```

Il comando supporta un override diagnostico tramite `--history`, ma il valore ordinario proviene dalla configurazione applicativa.

---

## 5. Modello dati

### `seasons`

Dimensione globale della stagione.

```text
id
season_key
label
```

### `league_seasons`

Edizione stagionale della singola lega.

```text
id
league_id
season_id
is_current
status
start_date
end_date
```

Regole:

- una sola stagione corrente per lega;
- vincolo univoco `league_id + season_id`;
- le date appartengono alla lega-stagione, non alla stagione globale.

### `league_season_provider_mappings`

Collega la lega-stagione canonica alle rappresentazioni esterne.

```text
league_season_id
data_provider_id
external_id
external_year
metadata
verified_at
```

---

## 6. Risoluzione automatica della lega

L'ID interno non viene ricercato per nome e non viene hardcodato.

```text
football_data + SA
        │
        ▼
league_provider_mappings
        │
        ▼
league_id = 120
```

```text
api_football + 135
        │
        ▼
league_provider_mappings
        │
        ▼
league_id = 120
```

Se i mapping disponibili risolvono leghe interne differenti, la sincronizzazione fallisce per incongruenza.

L'opzione `--league-id` resta esclusivamente come override diagnostico.

---

## 7. Discovery della stagione corrente

Ogni provider prova a restituire la propria stagione corrente e le relative date.

Il sistema:

1. raccoglie le discovery disponibili;
2. seleziona la stagione valida più recente;
3. costruisce la timeline secondo `SEASON_HISTORY_FALLBACK`;
4. verifica la copertura provider per ogni stagione;
5. normalizza le date disponibili;
6. pianifica le operazioni DB.

Le date `start_date` e `end_date` vengono conservate anche per validare la coerenza temporale di `is_current`.

### Regola sui dati espliciti

Le date ufficiali di una lega-stagione vengono valorizzate solo quando il provider espone campi espliciti nel payload.

```text
start_date=season.startDate
end_date=season.endDate
```

Non sono ammessi valori dedotti da eventi parziali, classifiche o aggregazioni locali.

```text
NO start_date = min(eventi)
NO end_date   = max(eventi)
```

Se un provider restituisce soltanto eventi o classifiche, puo' essere utile per audit, squadre o calendario, ma non e' valido per alimentare `start_date` e `end_date` della capability `seasons`.

---

## 8. Comandi CLI

### Stato provider

```bash
php artisan providers:status
```

Il comando mostra solo provider registrati nel database e configurazioni HTTP salvate nel DB.

```text
Code          Registered   Configured   Runtime    State
provider_a    YES          YES          ACTIVE     READY
provider_b    YES          YES          DISABLED   CONFIGURED
provider_c    YES          NO           DISABLED   TO CONFIGURE
```

Stati principali:

- `READY`: provider registrato, configurazione HTTP presente, runtime attivo;
- `CONFIGURED`: provider registrato e configurato, ma runtime spento;
- `TO CONFIGURE`: provider registrato ma senza endpoint HTTP runtime.

Il comando storico resta disponibile come alias compatibile:

```bash
php artisan providers:adapters
```

### Audit dei provider

```bash
php artisan season:audit-providers --provider-ref=provider_a=SA --provider-ref=provider_b=135 --season=2024 --json
```

### Dry-run della timeline

```bash
php artisan season:sync --provider-ref=provider_a=SA --provider-ref=provider_b=135 --season=2024 --json
```

### Apply

```bash
php artisan season:sync --provider-ref=provider_a=SA --provider-ref=provider_b=135 --season=2024 --apply
```

### Verifica di idempotenza

Dopo l'apply:

```bash
php artisan season:sync --provider-ref=provider_a=SA --provider-ref=provider_b=135 --season=2024
```

Il risultato atteso è `UNCHANGED` per tutte le stagioni.

---

## 9. Interfaccia amministrativa

Percorso:

```text
Administration
└── Gestione Stagioni
```

URL:

```text
/admin/manage-season
```

La UI espone due azioni.

La pagina `Administration -> Provider Management` distingue esplicitamente:

- `Registrato`: il provider esiste in `data_providers`;
- `Configurato`: il provider ha endpoint HTTP/mapping salvati nel DB;
- `Attivo`: runtime abilitato.

Nel form `Aggiungi provider` si registra solo il catalogo DB. La parte operativa nasce dopo, in `Configura e testa`.

### Provider HTTP Adapter Lab

Da `Provider Management -> Configura e testa` e' possibile configurare endpoint HTTP generici salvati nel DB.

La configurazione runtime e' composta da:

- capability;
- operation;
- method;
- auth mode;
- endpoint;
- query params;
- body JSON;
- items path;
- field mapping;
- contratto interno.

Endpoint, query params e body possono contenere placeholder:

```text
competitions/{provider_competition_code}/standings
season={season_year}
s={season_label}
```

`season_label` rappresenta il formato cross-year `YYYY-YYYY+1`, ad esempio `2022-2023`.

Il campo `Valori test variabili` serve solo per la prova manuale:

```text
provider_competition_code=SA
season_year=2024
```

Il test chiama l'URL risolto, ma il salvataggio mantiene i placeholder in `data_provider_http_endpoints`.

L'autenticazione e' configurabile sulla singola chiamata HTTP:

```text
auth_mode = default
```

usa il token/header/query configurato sul provider.

```text
auth_mode = none
```

non invia credenziali per quello specifico endpoint.

Caso Football-Data:

```text
competitions?areas=2114
```

puo' restituire una lista filtrata se chiamata con `X-Auth-Token`, mentre in modalita' pubblica restituisce la lista completa dell'area. Per il censimento competizioni si puo' quindi usare `auth_mode=none`; per endpoint operativi come standings o teams si mantiene `auth_mode=default`.

Esempio:

```text
endpoint = competitions/{provider_competition_code}/standings
query_params = {"season":"{season_year}"}
items_path = standings.0.table
```

Se invece in query params si salva `season=2024`, la stagione resta cablata a 2024.

Il `Field mapping` supporta anche array annidati. La guida completa e':

```text
docs/provider-lab/MAPPING_LANGUAGE.md
```

Esempio stagione Football-Data normalizzata come oggetto singolo con lista squadre:

```text
items_path = vuoto

season_id=season.id
start_date=season.startDate
end_date=season.endDate
list_teams=map(standings.0.table, provider_team_id=team.id, team_name=team.name, position=position)
```

Usare `items_path = standings.0.table` solo quando la collection principale da importare e' la lista squadre/classifica.
Se invece si sta normalizzando la stagione, `standings.0.table` va usato dentro `pluck(...)` o `map(...)`.

La pagina consente anche la pulizia:

- `Elimina configurazione`: rimuove endpoint e payload mapping runtime;
- `Elimina campo interno`: rimuove un campo da `data_provider_contract_fields` solo se non e' usato da mapping salvati.

### Analizza senza scrivere

- esegue `season:sync` in dry-run;
- mostra il report completo;
- non modifica il database;
- visualizza discovery, timeline, date, copertura e azioni previste.

### Applica sincronizzazione

- disponibile soltanto dopo un dry-run riuscito;
- richiede la conferma testuale `APPLICA`;
- richiama lo stesso motore del comando CLI;
- mostra il report dell'operazione.

La business logic rimane unica: la UI non replica il planner stagionale.

---

## 10. Stati e azioni pianificate

```text
CREATE
UPDATE_CURRENT
UPDATE_DATES
UNCHANGED
```

Il comando opera in transazione e prima disattiva l'eventuale precedente `is_current`, quindi applica la timeline pianificata.

---

## 11. Scenari validati

### Serie A

- multi-provider quando entrambe le API sono disponibili;
- single-provider quando un piano non copre una stagione;
- matching squadre 20/20 nei confronti validati.

### TheSportsDB

TheSportsDB e' stato verificato come fallback sperimentale, ma gli endpoint disponibili nel piano free non espongono date stagione ufficiali esplicite.

`eventsseason.php` restituisce eventi e puo' essere utile per audit o calendario, ma non deve essere usato per dedurre `start_date` e `end_date`.

### Serie B

- API-Football disponibile;
- Football-Data.org indisponibile con il piano corrente;
- fallback automatico senza errore terminale.

### Timeline Serie A validata

```text
2026/27  Football-Data.org
2025/26  Football-Data.org
2024/25  Football-Data.org + API-Football
2023/24  Football-Data.org + API-Football
2022/23  API-Football
```

---

## 12. Test e controlli operativi

```bash
php artisan test tests/Unit/Seasons tests/Unit/Providers
php artisan providers:status
php artisan route:list --name=admin.seasons
php artisan season:sync --competition=SA --api-league-id=135 --json
```

Controlli finali:

- league ID risolto automaticamente;
- una sola `is_current` per lega;
- `start_date` e `end_date` valorizzate quando disponibili;
- fallback storico conforme alla configurazione;
- secondo run idempotente;
- nessuno scraping nel flusso ufficiale.

### Audit chiamate provider

Le chiamate HTTP eseguite dal Provider Lab e dai provider generici vengono tracciate in:

```text
data_provider_api_call_audits
```

La tabella conserva solo dati sintetici:

- quale provider e quale endpoint configurato sono stati chiamati;
- capability e operation usate;
- endpoint risolto e query depurata dalle credenziali;
- status code, durata e numero item estratti;
- hash della risposta, senza salvare il payload raw;
- header provider utili, come rate limit, versione API, client autenticato e data risposta.

Non vengono salvati payload raw, token o campi generici vuoti. Se il provider non restituisce un request id ufficiale, il sistema non lo inventa.

---

## 13. Esito

Lo Step 1 fornisce una gestione stagionale:

- provider-agnostic;
- capability-driven;
- auditabile;
- configurabile;
- transazionale;
- idempotente;
- utilizzabile da CLI e UI.

Questa infrastruttura è la base per gli step successivi relativi a squadre, rose, classifiche, giocatori e statistiche.
