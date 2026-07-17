# HTTP Provider Adapter UI

**Progetto:** Fanta Oracle V3  
**Area:** Provider Management / Setup Dati  
**Stato:** Documento di implementazione  
**Obiettivo:** permettere la configurazione guidata da UI di provider HTTP semplici, senza creare subito un adapter PHP dedicato per ogni fonte.

---

## 1. Problema da risolvere

Il provider registry attuale permette di registrare:

- provider;
- runtime config;
- credenziali;
- stato operativo;
- adapter PHP nativi gia' installati.

Questo pero' non basta per integrare un nuovo provider a partire dalla sola documentazione API.

Un `base_url` non definisce:

- quali endpoint chiamare;
- quali parametri inviare;
- come autenticarsi;
- dove si trovano gli item nel JSON;
- come mappare i campi esterni sul contratto interno Fanta Oracle;
- quale competizione/stagione usare come input per le chiamate successive.

Quindi serve un adapter HTTP generico configurabile da UI.

---

## 2. Distinzione fondamentale

```text
Provider registrato
!=
Adapter operativo
```

### Provider registrato

Esiste in:

```text
data_providers
data_provider_runtime_configs
data_provider_credentials
```

Descrive la fonte dati, il piano, le credenziali e lo stato runtime.

### Native adapter

Classe PHP dedicata, per esempio:

```text
FootballDataTeamProvider
ApiFootballTeamProvider
```

Implementa codice specifico, normalizzazione e gestione payload complessi.

### HTTP adapter configurabile

Adapter generico che legge da DB:

```text
endpoint
method
auth
query params
response paths
field mapping
```

e produce il contratto interno Fanta Oracle.

---

## 3. Ordine corretto delle capability

Non si parte da `teams`.

L'ordine corretto e':

```text
competitions
-> seasons
-> teams
```

Motivo:

- i team dipendono da una competizione;
- molte API richiedono un `league_id`, un nome lega o un `season_id`;
- senza mapping competizione/provider non sappiamo interrogare correttamente i team.

Il primo pilota deve quindi essere:

```text
Competition HTTP Adapter Mapping
```

---

## 4. Flusso operativo desiderato

L'admin lavora come farebbe in Postman:

1. legge la documentazione del provider;
2. inserisce endpoint e parametri;
3. esegue una test request;
4. visualizza il JSON;
5. indica dove sono gli item;
6. mappa i campi;
7. salva la configurazione;
8. collega il risultato alla competizione interna;
9. solo dopo abilita le capability successive.

```text
Documentazione provider
        |
        v
UI Adapter HTTP
        |
        v
Test request + preview JSON
        |
        v
Mapping campi
        |
        v
Contratto interno Fanta Oracle
```

---

## 5. UI richiesta

Percorso previsto:

```text
Administration
-> Provider Management
-> Provider
-> Configura HTTP Adapter
```

Prima schermata implementata:

```text
/admin/providers/{provider}/http-adapter
```

La pagina permette di:

- scegliere capability;
- scegliere operation;
- configurare method, endpoint, query params e body;
- impostare `items_path`;
- dichiarare un primo field mapping;
- lanciare una test request;
- vedere status, URL risolto, primo item raw e preview normalizzata.

La pagina salva il mapping runtime nel database applicativo. Bruno resta il laboratorio versionato, ma Laravel e' la fonte runtime.

### Step A - Capability

Scelta capability:

```text
competitions
seasons
teams
fixtures
standings
players
statistics
```

Per la prima implementazione:

```text
competitions
```

### Step B - Operation

La capability e' la famiglia dati. L'operation e' l'azione concreta su quella famiglia.

Esempio Football-Data:

```text
capability: competitions
operation: list
endpoint: competitions
items_path: competitions
```

```text
capability: competitions
operation: detail
endpoint: competitions/SA
items_path: vuoto
```

`SA` non e' un `items_path`: e' il parametro/identificatore esterno usato nell'endpoint di dettaglio.

Operations disponibili:

```text
list
```

Da usare quando l'endpoint restituisce una lista/collezione di record della capability.

Esempio:

```text
GET /competitions
items_path = competitions
```

```text
detail
```

Da usare quando l'endpoint restituisce un singolo oggetto identificato da un codice o ID esterno.

Esempio:

```text
GET /competitions/SA
items_path = vuoto
```

```text
search
```

Da usare quando l'endpoint cerca record usando parametri liberi o filtri testuali.

Esempio:

```text
GET /search_all_leagues.php?c=Italy
items_path = countries
```

```text
by_competition
```

Da usare quando la chiamata dipende da una competizione gia mappata.

Esempio:

```text
GET /teams?competition=SA
GET /seasons?league=135
```

```text
by_season
```

Da usare quando la chiamata dipende da una stagione gia scelta o mappata.

Esempio:

```text
GET /fixtures?season=2025
GET /teams?season=2025
```

```text
by_team
```

Da usare quando la chiamata dipende da una squadra gia mappata.

Esempio:

```text
GET /players?team=123
GET /fixtures?team=123
```

### Step C - Request

Campi:

```text
method
endpoint
auth_mode
headers
query_params
body_template
timeout
```

Esempio:

```text
method: GET
endpoint: search_all_leagues.php
query:
  c = Italy
```

### Step D - Variabili disponibili

La UI deve mostrare quali variabili possono essere usate.

Per `competitions`:

```text
{country_name}
{country_code}
{league_name}
```

Per `seasons`:

```text
{provider_league_id}
{provider_league_name}
```

Per `teams`:

```text
{provider_league_id}
{provider_league_name}
{season_key}
{season_year}
```

### Step E - Test Request

Pulsante:

```text
Test request
```

Mostra:

```text
resolved_url
status_code
headers principali
raw_json
errore eventuale
```

La test request non salva dati di dominio.

### Step F - Response selector

Campi:

```text
items_path
pagination_path
next_page_path
```

Esempio TheSportsDB:

```text
items_path = countries
```

oppure:

```text
items_path = leagues
```

dipende dall'endpoint scelto.

### Step G - Field mapping

Per capability `competitions`:

```text
external_id_path
name_path
country_path
type_path
logo_path
metadata_paths
```

Per capability `seasons`:

```text
external_season_id_path
season_label_path
start_date_path
end_date_path
current_flag_path
```

Per capability `teams`:

```text
external_id_path
name_path
short_name_path
country_path
logo_path
founded_path
venue_path
```

### Step G - Preview normalizzata

Dopo il mapping, la UI mostra:

```text
raw item
->
normalized item
```

Esempio:

```json
{
  "external_id": "4331",
  "name": "Italian Serie A",
  "country": "Italy"
}
```

### Step H - Salvataggio

Salva:

```text
provider
capability
operation
request config
response mapping
test sample
validation status
```

---

## 6. Modello dati proposto

### `data_provider_http_endpoints`

```text
id
data_provider_id
capability
operation
method
endpoint
query_params json nullable
body_template json nullable
items_path
is_enabled boolean
last_tested_at nullable
last_status_code nullable
sample_payload json nullable
sample_normalized json nullable
validation_status
created_at
updated_at
```

Vincoli:

```text
unique(data_provider_id, capability, operation)
```

### `data_provider_payload_mappings`

```text
id
data_provider_http_endpoint_id
field_mappings json
required_fields json
validation_status
created_at
updated_at
```

Esempio `field_mappings`:

```json
{
  "external_id": "idLeague",
  "name": "strLeague",
  "country": "strCountry",
  "logo": "strBadge"
}
```

### Possibile alternativa

Per una prima fase si puo' salvare tutto in `data_provider_runtime_configs.metadata`.

Non e' consigliato oltre il prototipo, perche':

- diventa difficile validare;
- diventa difficile versionare i mapping;
- diventa difficile testare capability diverse;
- aumenta il rischio di metadata ingestibili.

Decisione consigliata:

```text
tabelle dedicate
```

---

## 7. Contratto applicativo

Introdurre:

```text
GenericHttpProvider
GenericHttpCompetitionProvider
GenericHttpSeasonProvider
GenericHttpTeamProvider
```

Il primo da implementare:

```text
GenericHttpCompetitionProvider
```

Responsabilita':

- leggere config HTTP da DB;
- risolvere variabili;
- eseguire request;
- estrarre items via path;
- applicare mapping;
- restituire DTO normalizzati;
- non conoscere provider specifici.

---

## 8. Casi pilota

### TheSportsDB

Ruolo:

```text
provider pubblico senza credenziale
```

Obiettivo pilota:

```text
competitions mapping
```

Esempio da verificare in Postman/documentazione:

```text
base_url:
https://www.thesportsdb.com/api/v1/json/3

endpoint candidato:
search_all_leagues.php

query:
c = Italy
```

Mapping da definire dopo test reale payload:

```text
items_path
external_id_path
name_path
country_path
```

Nota: TheSportsDB usa spesso nomi lega come input per endpoint successivi. Questo va modellato esplicitamente nel mapping.

### SportMonks

Ruolo:

```text
provider con credenziale
```

Obiettivo pilota:

```text
competitions mapping autenticato
```

Da verificare da documentazione/Postman:

```text
endpoint competitions/leagues
auth via token
items_path
league_id path
season path
pagination
```

SportMonks non va usato come primo caso se non abbiamo credenziale valida.

---

## 9. Stati operativi

Provider status deve evolvere cosi':

```text
REGISTERED
HTTP_CONFIG_DRAFT
HTTP_TEST_FAILED
HTTP_TEST_PASSED
MAPPING_VALIDATED
READY
DISABLED
```

Per la UI:

```text
Adapter richiesto
```

deve diventare piu' specifico:

```text
Configura HTTP adapter
```

oppure:

```text
Installa native adapter
```

quando non esiste mapping HTTP.

---

## 10. Regole di sicurezza

- Non mostrare mai credenziali cifrate in chiaro nella preview.
- La test request deve mascherare token nei log.
- Consentire solo HTTP method previsti: `GET`, `POST`.
- Timeout massimo configurabile.
- Nessuna esecuzione di codice utente.
- Mapping solo tramite path dichiarativi, non espressioni PHP arbitrarie.
- Salvataggio consentito solo ad admin autorizzati.

---

## 11. Test richiesti

### Unit

- path reader JSON;
- template resolver;
- query/header renderer;
- field mapper;
- payload normalizer;
- masking credenziali.

### Feature

- admin crea HTTP adapter config;
- admin esegue test request mockata;
- errore HTTP viene salvato;
- mapping valido produce preview normalizzata;
- provider passa da `ADAPTER REQUIRED` a `HTTP_TEST_PASSED`;
- competitions mapping salva collegamento a lega interna.

### UI

- capability wizard mostra step corretti;
- test request mostra JSON;
- mapping mostra preview;
- salvataggio disabilitato se manca `items_path`;
- credenziale richiesta se auth mode lo prevede.

---

## 12. Roadmap implementativa

### Fase 1 - Documento e UI guida

- creare questa specifica;
- aggiungere schermata guida in Provider Management;
- chiarire differenza tra native adapter e HTTP adapter.

### Fase 2 - DB e servizi base

- migration `data_provider_http_adapters`;
- migration `data_provider_response_mappings`;
- servizio `HttpAdapterRequestBuilder`;
- servizio `JsonPathReader`;
- servizio `ResponseFieldMapper`.

### Fase 3 - Competition mapping

- UI capability `competitions`;
- test request;
- preview JSON;
- mapping normalized competition;
- salvataggio collegamento a `league_provider_mappings`.

### Fase 4 - Seasons

- endpoint stagione;
- mapping season;
- collegamento a `league_season_provider_mappings`.

### Fase 5 - Teams

- endpoint teams;
- input da competition/season mapping;
- normalizzazione team.

---

## 13. Non-obiettivi

Per la prima versione non implementare:

- editor visuale completo tipo Postman;
- import automatico da collection Postman;
- mapping con codice custom;
- scraping HTML;
- paginazione complessa multi-step;
- supporto a tutte le capability in un'unica fase.

---

## 14. Decisione

La prossima implementazione deve partire da:

```text
Generic HTTP Adapter configurabile da UI
capability: competitions
provider pilota: TheSportsDB
```

Solo dopo validazione reale del mapping competizione si procede con:

```text
seasons
teams
```

Questo evita di costruire team mapping senza sapere prima quale competizione esterna rappresenta la lega interna.
