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
