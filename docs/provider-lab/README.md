# Provider Lab

Provider Lab e' il laboratorio locale e versionato per testare le API esterne prima di renderle adapter runtime in Laravel.

## Regola principale

```text
Bruno = laboratorio tecnico versionato
Laravel = runtime, mapping persistente e import dati
```

Il mapping finale non vive solo nei file del laboratorio: i file servono a validare request, payload e trasformazioni prima di salvarli nel database applicativo.

## Ordine di lavoro

```text
competitions
-> seasons
-> teams
```

Non si configurano squadre prima di aver risolto la competizione esterna.

## Esecuzione

```bash
npm run provider-lab
```

Il comando esegue la collection Bruno in `docs/provider-lab`.

## Struttura

```text
docs/provider-lab/
  bruno.json
  football_data/
    competitions/
      mapping.competitions.json
      sample-response.json
  api_football/
    competitions/
      mapping.competitions.json
      sample-response.json
  thesportsdb/
    competitions/
      search-all-leagues-italy.bru
      sample-response.json
      mapping.competitions.json
  sportmonks/
    competitions/
      mapping.competitions.json
```

Ogni provider parametrizzato deve avere almeno la mappatura `competitions`.

Le request Bruno eseguibili vengono aggiunte solo quando endpoint, auth e input di test sono verificati. Una mappatura con `validation_status = pending_documentation` non deve essere considerata pronta.

## Capability e operation

La capability indica la famiglia dati. L'operation indica cosa fa quello specifico endpoint.

Esempio Football-Data:

```text
competitions + list
GET /competitions
items_path = competitions
```

```text
competitions + detail
GET /competitions/SA
items_path = vuoto
```

`items_path` serve solo a dire dove si trova la lista nel JSON. Se l'endpoint restituisce un singolo oggetto, resta vuoto.

## Template runtime e valori test

Nel Provider Management Laravel gli endpoint vanno salvati come template riutilizzabili quando contengono parti variabili.

Esempio Football-Data standings:

```text
endpoint = competitions/{provider_competition_code}/standings
query_params = season={season_year}
items_path = standings.0.table
```

Per testare dalla UI si compila il campo `Valori test variabili`:

```text
provider_competition_code=SA
season_year=2024
```

Il test chiama:

```text
competitions/SA/standings?season=2024
```

ma il salvataggio runtime mantiene i placeholder:

```json
{"season":"{season_year}"}
```

Regola pratica:

```text
Endpoint / Query params / Body JSON = configurazione salvata
Valori test variabili              = solo prova manuale
```

Se in `Query params` si scrive:

```text
season=2024
```

la stagione viene cablata. Se invece si scrive:

```text
season={season_year}
```

il runtime potra' sostituire la stagione in base al contesto applicativo.

## Pulizia in Laravel

Da `Provider Management -> Configura e testa` e' possibile:

- eliminare un mapping runtime salvato;
- eliminare un campo interno del contratto.

L'eliminazione mapping rimuove endpoint e payload mapping.

L'eliminazione del campo interno e' bloccata se quel campo e' ancora usato da un mapping salvato.

## Visibilita' configurazioni salvate

La lista Provider Management mostra il contenuto delle chiamate HTTP salvate, non solo il numero di mapping:

```text
capability · operation
label
method endpoint
query params
items path
numero campi mappati
stato mapping
```

La pagina `Configura e testa` mostra in alto il blocco `Chiamate configurate`.
Da li' si usa `Carica nel form` per riprendere una configurazione esistente e ritestarla o modificarla.
Entrando normalmente nella pagina il form resta vuoto per creare una nuova configurazione.
Il pulsante `Nuova configurazione` riporta sempre al form pulito, anche dopo aver caricato una chiamata salvata.

## Stati da riportare in Laravel

```text
REGISTERED
CONFIGURED
REACHABLE
VALID
READY
AUTH_FAILED
UNREACHABLE
INVALID_PAYLOAD
MAPPING_INCOMPLETE
```

`active = true` non significa che il provider e' funzionante. Il Provider Lab deve aiutare a dimostrare che endpoint, auth, payload e mapping sono validi.
