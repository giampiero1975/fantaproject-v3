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

---

## 8. Comandi CLI

### Stato provider e adapter

```bash
php artisan providers:status
```

Il comando mostra l'unione tra provider registrati nel database e adapter PHP installati in `config/data_provider_adapters.php`.

```text
Code          Registered   Adapter installed   Runtime    State
football_data YES          YES                 ACTIVE     READY
api_football  YES          YES                 ACTIVE     READY
thesportsdb   YES          NO                  DISABLED   ADAPTER REQUIRED
sportmonks    YES          NO                  DISABLED   ADAPTER REQUIRED
```

Stati principali:

- `READY`: provider registrato, adapter installato, runtime attivo;
- `ADAPTER REQUIRED`: provider registrato dalla UI ma adapter PHP non ancora installato;
- `AVAILABLE TO REGISTER`: adapter PHP disponibile ma provider non ancora registrato nel DB;
- `DISABLED`: provider registrato con adapter installato, ma runtime spento.

Il comando storico resta disponibile come alias compatibile:

```bash
php artisan providers:adapters
```

### Audit dei provider

```bash
php artisan season:audit-providers --competition=SA --league-id=135 --season=2024 --json
```

### Dry-run della timeline

```bash
php artisan season:sync --competition=SA --api-league-id=135 --json
```

### Apply

```bash
php artisan season:sync --competition=SA --api-league-id=135 --apply
```

### Verifica di idempotenza

Dopo l'apply:

```bash
php artisan season:sync --competition=SA --api-league-id=135
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
- `Adapter richiesto`: il provider e' configurato nel DB, ma manca l'adapter applicativo;
- `Attivo`: adapter installato e runtime abilitato.

I provider DB-only non sono attivabili dalla UI: il bottone resta disabilitato finche' non viene installato l'adapter PHP.

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
