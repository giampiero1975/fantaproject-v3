# Provider Mapping Language

Questa guida descrive la sintassi usata nel campo `Field mapping` della pagina:

```text
Administration -> Provider Management -> Configura e testa
```

Il mapping serve a trasformare il payload JSON del provider esterno nel contratto interno Fanta Oracle.

## Concetto base

Il formato normale e':

```text
campo_interno=path_payload
```

Esempio:

```text
season_id=season.id
start_date=season.startDate
end_date=season.endDate
```

Il lato sinistro e' il campo interno Fanta Oracle.
Il lato destro e' il path nel JSON restituito dal provider.

## Items path

`Items path` decide qual e' l'oggetto o la lista principale su cui lavorare.

Se l'endpoint restituisce una lista:

```json
{
  "teams": [
    { "id": 109, "name": "Juventus FC" },
    { "id": 98, "name": "AC Milan" }
  ]
}
```

usa:

```text
Items path:
teams
```

Il `Field mapping` verra' applicato a ogni elemento di `teams`.

Se l'endpoint restituisce un oggetto singolo:

```json
{
  "season": {
    "id": 2494,
    "startDate": "2026-08-23"
  }
}
```

lascia:

```text
Items path:
vuoto
```

Il `Field mapping` verra' applicato all'oggetto root.

## Preview normalizzata

La UI mostra solo la preview del primo item normalizzato.

Questo non significa che il mapping produca un solo record.
Significa che la UI mostra un esempio per verificare che i path siano corretti.

## Path semplici

Payload:

```json
{
  "season": {
    "id": 2494,
    "startDate": "2026-08-23",
    "endDate": "2027-05-30"
  }
}
```

Mapping:

```text
season_id=season.id
start_date=season.startDate
end_date=season.endDate
```

Preview:

```json
{
  "season_id": 2494,
  "start_date": "2026-08-23",
  "end_date": "2027-05-30"
}
```

## `pluck(...)`

Usa `pluck(...)` quando vuoi estrarre un array semplice da una lista annidata.

Sintassi:

```text
campo_json=pluck(path_array, path_valore)
```

Payload Football-Data:

```json
{
  "standings": [
    {
      "table": [
        { "team": { "id": 109, "name": "Juventus FC" } },
        { "team": { "id": 98, "name": "AC Milan" } }
      ]
    }
  ]
}
```

Mapping:

```text
list_teams=pluck(standings.0.table, team.id)
```

Preview:

```json
{
  "list_teams": [109, 98]
}
```

`standings.0.table` seleziona l'array.
`team.id` indica quale valore prendere da ogni elemento.

## `map(...)`

Usa `map(...)` quando vuoi estrarre un array di oggetti da una lista annidata.

Sintassi:

```text
campo_json=map(path_array, campo=path, campo=path)
```

Payload Football-Data:

```json
{
  "standings": [
    {
      "table": [
        {
          "position": 1,
          "team": { "id": 109, "name": "Juventus FC" }
        },
        {
          "position": 2,
          "team": { "id": 98, "name": "AC Milan" }
        }
      ]
    }
  ]
}
```

Mapping:

```text
list_teams=map(standings.0.table, provider_team_id=team.id, team_name=team.name, position=position)
```

Preview:

```json
{
  "list_teams": [
    {
      "provider_team_id": 109,
      "team_name": "Juventus FC",
      "position": 1
    },
    {
      "provider_team_id": 98,
      "team_name": "AC Milan",
      "position": 2
    }
  ]
}
```

## Caso pratico: stagione Football-Data con squadre

Endpoint:

```text
competitions/{provider_competition_code}/standings
```

Query params:

```text
season={season_year}
```

Valori test variabili:

```text
provider_competition_code=SA
season_year=2026
```

Items path:

```text
vuoto
```

Field mapping con lista ID squadre:

```text
season_id=season.id
start_date=season.startDate
end_date=season.endDate
list_teams=pluck(standings.0.table, team.id)
```

Field mapping con lista oggetti squadra:

```text
season_id=season.id
start_date=season.startDate
end_date=season.endDate
list_teams=map(standings.0.table, provider_team_id=team.id, team_name=team.name, position=position)
```

## Quando usare cosa

```text
campo=path
```

Usalo per valori scalari: stringhe, numeri, date, booleani, URL.

```text
campo=pluck(path_array, path_valore)
```

Usalo quando vuoi un array semplice:

```json
[109, 98]
```

```text
campo=map(path_array, campo=path, campo=path)
```

Usalo quando vuoi un array di oggetti:

```json
[
  { "provider_team_id": 109, "team_name": "Juventus FC" }
]
```

## Regola sui dati canonici

I campi canonici devono essere valorizzati solo da valori espliciti presenti nel payload del provider.

Esempio corretto:

```text
start_date=season.startDate
end_date=season.endDate
```

Non dedurre date ufficiali da liste parziali di eventi o da aggregazioni calcolate localmente.

Esempio non ammesso:

```text
start_date=min(events, dateEvent)
end_date=max(events, dateEvent)
```

Se il provider non espone `start_date` e `end_date` espliciti, quel provider non e' valido per alimentare le date ufficiali della capability `seasons`.

## Errori comuni

Non scrivere:

```text
list_teams=standings.0.table.team.id
```

`standings.0.table` e' un array. Dopo un array non puoi accedere direttamente a `team.id`.

Usa invece:

```text
list_teams=pluck(standings.0.table, team.id)
```

oppure:

```text
list_teams=map(standings.0.table, provider_team_id=team.id, team_name=team.name)
```

Non usare `Items path = standings.0.table` se vuoi normalizzare la stagione.
Quello trasforma la classifica o le squadre nella collection principale.

Usa `Items path = standings.0.table` solo quando la capability che stai configurando e' una lista di squadre o righe classifica.

## Limiti attuali

`pluck(...)` e `map(...)` supportano path semplici separati da virgole.

Non sono ancora supportati:

- filtri condizionali;
- trasformazioni di tipo `uppercase`, `date_format`, `replace`;
- aggregazioni come `min` e `max` per derivare campi canonici;
- funzioni annidate;
- path con virgole nei nomi campo.
