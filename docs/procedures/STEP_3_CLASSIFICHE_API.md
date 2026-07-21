# STEP 3 - Classifiche API

**Progetto:** Fanta Oracle V3  
**Modulo:** Setup Dati - Classifiche  
**Stato:** Implementazione runtime provider layer  
**Interfaccia:** `/admin/standings`

---

## 1. Obiettivo

Lo Step 3 importa e controlla le classifiche stagionali tramite provider configurati da DB.

Il sistema deve:

- ricevere una lega/stagione canonica;
- risolvere i mapping provider disponibili;
- chiamare provider attivi con capability `standings`;
- normalizzare la classifica nel contratto interno;
- salvare posizione, punti, gare, gol e differenza reti quando disponibili;
- mantenere audit della chiamata senza salvare payload raw.

---

## 2. Capability richiesta

```text
standings
```

La capability viene letta da:

```text
data_provider_capabilities
```

Quindi `standing` non e' una capability valida se non viene registrata nel catalogo. La nomenclatura canonica e' plurale:

```text
standings
```

---

## 3. Input runtime

Lo Step 3 non deve chiedere codici provider a mano nel flusso ordinario.

I riferimenti arrivano da:

```text
league_provider_mappings
league_season_provider_mappings
```

Esempio:

```text
Serie A interna
-> football_data = SA
-> api_football = 135
```

La UI puo' offrire override diagnostici, ma il flusso corretto usa il registry.

---

## 4. Mapping HTTP

Esempio Football-Data:

```text
capability = standings
operation = by_competition
endpoint = competitions/{provider_competition_code}/standings
query = season={season_year}
items_path = standings.0.table
```

Esempio mapping:

```text
provider_team_id=team.id
team_name=team.name
position=position
played_games=playedGames
won=won
draw=draw
lost=lost
points=points
goals_for=goalsFor
goals_against=goalsAgainst
goal_difference=goalDifference
```

Se un provider restituisce piu' gruppi/classifiche, `items_path` deve selezionare esplicitamente la collection corretta.

---

## 5. Audit chiamate

Ogni chiamata provider viene registrata in:

```text
data_provider_api_call_audits
```

La tabella conserva:

- provider e endpoint configurato;
- capability e operation;
- endpoint risolto;
- query depurata;
- status code;
- durata;
- numero item;
- fingerprint risposta;
- header provider utili.

Non conserva:

- payload raw;
- token;
- api key;
- request id inventati;
- metadata generico.

---

## 6. Regole

- niente provider hardcoded;
- niente scraping;
- il provider deve essere attivo;
- la capability `standings` deve essere configurata;
- il mapping lega-provider deve esistere;
- la stagione deve essere risolta dal registry o da placeholder runtime;
- i dati parziali sono ammessi solo se il contratto interno li considera opzionali.

