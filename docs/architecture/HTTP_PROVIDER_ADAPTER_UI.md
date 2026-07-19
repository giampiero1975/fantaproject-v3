# HTTP Provider Adapter UI

**Progetto:** Fanta Oracle V3  
**Area:** Provider Management / Setup Dati  
**Stato:** Documento di implementazione  
**Obiettivo:** permettere la configurazione guidata da UI di provider HTTP, senza creare adapter PHP dedicati per singola fonte.

---

## 1. Problema da risolvere

Il provider registry attuale permette di registrare:

- provider;
- runtime config;
- credenziali;
- stato operativo;
- endpoint HTTP e mapping salvati nel DB.

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
Provider pronto runtime
```

### Provider registrato

Esiste in:

```text
data_providers
data_provider_runtime_configs
data_provider_credentials
```

Descrive la fonte dati, il piano, le credenziali e lo stato runtime.

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

### Decisione runtime DB-driven

Il registry runtime non deve contenere una lista cablata di provider nel codice.

La sorgente primaria e':

```text
data_providers
data_provider_runtime_configs
data_provider_http_endpoints
data_provider_payload_mappings
data_provider_configurations
```

Regole:

- se un provider ha una configurazione HTTP abilitata per la capability richiesta, il runtime usa il provider HTTP generico;
- non esiste fallback su classi PHP specifiche per provider;
- autenticazione HTTP, credenziale, header e query auth sono configurazioni DB;
- `mapping_incomplete` e' diagnostica del mapping, non spegnimento automatico dell'endpoint HTTP.

Stato attuale implementato:

```text
TeamProviderRegistry
-> legge provider e endpoint teams dal DB
-> costruisce GenericHttpTeamProvider quando trova data_provider_http_endpoints.capability = teams
```

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
- assegnare una label leggibile alla configurazione;
- configurare method, endpoint, query params e body;
- usare variabili template in endpoint, query params e body;
- indicare valori di test temporanei per risolvere le variabili;
- impostare `items_path`;
- dichiarare un primo field mapping;
- lanciare una test request;
- vedere status, URL risolto, primo item raw e preview normalizzata.
- vedere subito le chiamate HTTP gia' configurate con endpoint, query, items path e campi mappati;
- caricare una configurazione salvata nel form per modificarla o ritestarla;
- eliminare mapping runtime salvati;
- aggiungere, modificare ed eliminare i campi del contratto interno.

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
label
endpoint
query_params
body_template
test_variables
```

Esempio:

```text
method: GET
endpoint: search_all_leagues.php
query:
  c = Italy
```

### Step D - Template e valori di test

Endpoint, query params e body JSON possono contenere variabili dichiarate con parentesi graffe.

Esempio:

```text
endpoint:
competitions/{provider_competition_code}/standings

query_params:
season={season_year}
```

La UI non indovina quali parti dell'URL siano variabili. La differenza e' esplicita:

```text
competitions/SA/standings
```

e' una URL fissa: `SA` resta sempre `SA`.

```text
competitions/{provider_competition_code}/standings
```

e' un template: il runtime dovra' sostituire `{provider_competition_code}` con il valore reale.

Per provare subito il template da UI si usa il campo:

```text
Valori test variabili
```

Esempio:

```text
provider_competition_code=SA
season_year=2024
```

Questi valori vengono usati solo dal pulsante `Test request`. Non vengono salvati nella configurazione runtime.

Regola operativa:

```text
Endpoint / Query params / Body JSON = configurazione salvata
Valori test variabili              = valori temporanei per la prova
```

Quindi:

```text
query_params:
season=2024
```

salva in DB:

```json
{"season":"2024"}
```

e cabla la stagione 2024.

Invece:

```text
query_params:
season={season_year}
```

salva in DB:

```json
{"season":"{season_year}"}
```

e lascia al runtime la sostituzione della stagione corretta.

Esempio Football-Data standings:

```text
capability: standings
operation: by_competition
method: GET
endpoint: competitions/{provider_competition_code}/standings
query_params: season={season_year}
test_variables:
  provider_competition_code=SA
  season_year=2024
items_path: standings.0.table
```

Durante il test viene chiamato:

```text
https://api.football-data.org/v4/competitions/SA/standings?season=2024
```

Nel DB resta salvato:

```text
endpoint = competitions/{provider_competition_code}/standings
query_params = {"season":"{season_year}"}
```

### Step E - Variabili disponibili

La UI deve mostrare quali variabili possono essere usate.

Per `competitions`:

```text
{country_name}
{country_code}
{league_name}
{provider_competition_code}
{provider_competition_id}
```

Per `seasons`:

```text
{provider_competition_code}
{provider_competition_id}
{season_year}
```

Per `teams`:

```text
{provider_competition_code}
{provider_competition_id}
{season_key}
{season_year}
```

Per `standings`:

```text
{provider_competition_code}
{provider_competition_id}
{season_year}
```

### Step F - Test Request

Pulsante:

```text
Test request
```

Mostra:

```text
resolved_url
resolved_query
status_code
headers principali
raw_json
errore eventuale
```

La test request non salva dati di dominio.

### Step G - Response selector

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

### Step H - Field mapping

Guida completa:

```text
docs/provider-lab/MAPPING_LANGUAGE.md
```

Il formato base e':

```text
campo_interno=path_payload
```

Esempio:

```text
season_id=season.id
start_date=season.startDate
```

Quando un campo interno deve contenere una lista estratta da un array annidato, si usa:

```text
campo_json=pluck(path_array, path_valore)
```

Esempio Football-Data, stagione con lista ID squadre:

```text
list_teams=pluck(standings.0.table, team.id)
```

Qui `standings.0.table` individua l'array da attraversare e `team.id` il valore da estrarre da ogni elemento.

Quando il campo interno deve contenere una lista di oggetti, si usa:

```text
campo_json=map(path_array, campo=path_valore, campo=path_valore)
```

Esempio Football-Data, stagione con ID e nome squadre:

```text
list_teams=map(standings.0.table, provider_team_id=team.id, team_name=team.name)
```

Risultato:

```json
[
  {
    "provider_team_id": 109,
    "team_name": "Juventus FC"
  }
]
```

Per capability `competitions`:

```text
provider_competition_code_path
provider_competition_id_path
provider_area_id_path
competition_name_path
country_name_path
country_code_path
competition_type_path
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

### Step I - Preview normalizzata

Dopo il mapping, la UI mostra:

```text
raw item
->
normalized item
```

Esempio:

```json
{
  "provider_competition_code": "SA",
  "provider_competition_id": 2019,
  "provider_area_id": 2114,
  "competition_name": "Serie A",
  "country_name": "Italy",
  "country_code": "ITA",
  "competition_type": "LEAGUE"
}
```

Nel payload Football-Data:

```text
id = 2019
```

e' l'ID numerico della competizione.

```text
area.id = 2114
```

e' l'ID numerico dell'area/paese. Non va confuso con l'ID della competizione.

### Step L - Salvataggio

Salva:

```text
provider
capability
operation
label
request config
response mapping
test sample
validation status
```

Il salvataggio mantiene i placeholder. I `test_variables` non vengono persistiti.

### Step M - Pulizia configurazioni

La pagina `Provider Management` non deve mostrare solo il conteggio `HTTP mapping`.
Per ogni provider deve mostrare anche il contenuto minimo delle chiamate configurate:

```text
capability
operation
label
method + endpoint
query params
items_path
numero campi mappati
stato mapping
```

La pagina `Configura e testa` mostra lo stesso riepilogo in alto, prima del form, con l'azione:

```text
Carica nel form
```

Questa azione ricarica la pagina con `capability` e `operation` selezionate e precompila endpoint, query params, body, items path e field mapping salvati.

Entrando normalmente in `Configura e testa` il form resta vuoto e serve a creare una nuova configurazione.
Il link `Nuova configurazione` torna allo stesso stato pulito anche dopo aver caricato una chiamata salvata.

La UI distingue due cancellazioni:

```text
Elimina configurazione
```

Cancella:

```text
data_provider_http_endpoints
data_provider_payload_mappings
```

Serve a rimuovere una configurazione runtime sbagliata o non piu' utile.

```text
Elimina campo interno
```

Cancella da:

```text
data_provider_contract_fields
```

solo se il campo non e' usato da un mapping salvato. Se il campo compare in `field_mappings`, la cancellazione viene bloccata e l'admin deve prima eliminare o modificare il mapping runtime.

---

## 6. Modello dati proposto

## Logging operativo

La procedura Provider Management mantiene due livelli di logging:

```text
storage/logs/laravel.log
```

Rimane il log applicativo generale. Gli errori bloccanti o le eccezioni catturate durante la procedura vengono tracciati anche qui.

```text
storage/logs/administration/provider_managment/provider_management.log
```

Contiene il diario verboso unico delle singole funzioni del menu Administration -> Provider Management.
Il file viene riscritto all'inizio di ogni richiesta Provider Management: non viene mantenuto in append tra richieste diverse.
Dentro la singola richiesta, invece, vengono registrati tutti i passaggi della procedura.

Ogni riga e' bollata nel messaggio con:

```text
[funzionalita][livello]
```

Esempio:

```text
[http_adapter_test][info] HTTP adapter test completed.
[provider_runtime][warning] Provider runtime toggle blocked: adapter missing.
[http_adapter_test][error] HTTP adapter test failed.
```

Ogni riga include anche contesto comune:

```text
menu
section
functionality
user_id
request_method
request_path
```

Le credenziali non vengono mai scritte in chiaro nei log. Si traccia solo la chiave tecnica, ad esempio `token` o `api_key`.

### `data_provider_contract_fields`

Contiene il contratto interno normalizzato per ogni capability. La UI non deve definire questi campi nel controller.

```text
id
capability
field_key
label
description nullable
data_type
is_required
sort_order
created_at
updated_at
```

Vincoli:

```text
unique(capability, field_key)
```

Esempio `competitions`:

```text
provider_competition_code
provider_competition_id
provider_area_id
competition_name
country_name
country_code
competition_type
competition_logo_url
```

Questi campi sono il vocabolario interno. Il mapping provider specifico dice invece da quale path del payload esterno arriva ogni campo.

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

`endpoint`, `query_params` e `body_template` possono contenere placeholder runtime come:

```text
{provider_competition_code}
{season_year}
```

I valori concreti usati per il test manuale non vengono salvati in questa tabella.

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
  "provider_competition_code": "idLeague",
  "competition_name": "strLeague",
  "country_name": "strCountry",
  "competition_logo_url": "strBadge"
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
provider_competition_code_path
competition_name_path
country_name_path
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

Per la UI, un provider senza endpoint HTTP runtime deve essere indicato come:

```text
Da configurare
```

Non esiste piu' uno stato operativo legato all'installazione di adapter PHP specifici.

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
- provider passa da `TO CONFIGURE` a `CONFIGURED`;
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
- chiarire differenza tra provider registrato, configurato e attivo.

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
