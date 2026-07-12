
# SETUP_DATI_1.1–1.2 — Football Registry and League Seasons

**Project:** Fanta Oracle V3  
**Path:** `/docs/architecture/SETUP_DATI_1.1-1.2_Football_Registry_and_League_Seasons.md`  
**Status:** ✅ APPROVED (Step Closed)

---

# Executive Summary

Questo documento descrive la progettazione del nuovo **Football Registry** e del modello **League Seasons**, che sostituiscono definitivamente l'approccio iniziale centrato esclusivamente sulla Serie A.

L'obiettivo è costruire un Data Platform internazionale, indipendente dai provider e scalabile.

---

# Problema iniziale

Il progetto nasceva partendo dai listoni Fantacalcio.

Conseguenze:

- modello Serie A-centrico;
- difficoltà nell'analisi storica;
- impossibilità di gestire correttamente nuovi giocatori;
- nessun supporto reale per campionati esteri;
- Translation League Model limitato.

Da qui la decisione di partire dalla struttura del calcio mondiale.

---

# Obiettivi

- Registry proprietario.
- Provider-agnostic.
- Supporto multi-provider.
- Separazione dati statici / dati dinamici.
- Fondamenta per Team Seasons e Player Seasons.

---

# Architettura

```text
Confederations
    │
    ▼
Countries
    │
    ▼
Leagues
    ├──────────────┐
    ▼              ▼
League Aliases   League Provider Mappings
    │
    ▼
League Seasons
    │
    ├──────────────┐
    ▼              ▼
Seasons      League Season Provider Mappings
                    │
                    ▼
             Data Providers
```

---

# Tabelle

## confederations

Macro aree FIFA.

Stato:

- UEFA
- CONMEBOL
- CONCACAF

---

## countries

Ogni Paese appartiene ad una Confederazione.

Relazione:

```text
Confederation
      │
      ▼
 Country
```

---

## leagues

Registro canonico delle leghe.

Le leghe NON appartengono ad alcun provider.

---

## data_providers

Registro dei provider.

Attualmente:

- API-Football

Estendibile a:

- Football-Data.org
- SportMonks
- altri

---

## league_provider_mappings

Associa una lega interna agli identificativi esterni.

Il Core utilizza sempre gli ID interni.

---

## league_aliases

Normalizzazione dei nomi.

Esempio:

- Serie A
- Serie A TIM
- Italian Serie A

↓

stessa lega interna.

---

# League Seasons

## Principio

La stagione è una dimensione globale.

La lega rappresenta l'edizione sportiva.

---

## seasons

Schema:

```text
id
season_key
label
created_at
updated_at
```

Regola ufficiale:

| season_key | label |
|------------|-------|
| 2025 | 2025/26 |
| 2025 | 2025 |

`season_key` è la chiave tecnica.

`label` è la tassonomia.

---

## league_seasons

Schema:

```text
id
league_id
season_id
created_at
updated_at
```

Vincolo:

```text
UNIQUE(league_id, season_id)
```

---

## league_season_provider_mappings

Schema:

```text
id
league_season_id
data_provider_id
external_id
external_year
metadata
verified_at
timestamps
```

---

# Decisioni Architetturali (ADR)

## ADR-001

Abbandono del modello Serie A-centrico.

## ADR-002

Registry proprietario.

## ADR-003

Provider-agnostic.

## ADR-004

API-Football come primo provider.

## ADR-005

Seasons globali.

## ADR-006

`season_key` come chiave tecnica.

## ADR-007

`label` come rappresentazione umana.

## ADR-008

Esclusi:

- start_year
- end_year
- cross_year
- slug
- active
- status

---

# Validazione

Schema PostgreSQL verificato.

Popolamento attuale:

| Entità | Record |
|--------|-------:|
| Confederations | 3 |
| Countries | 100 |
| Leagues | 183 |
| Data Providers | 1 |
| League Provider Mappings | 103 |
| League Aliases | 0 |
| Seasons | 0 |
| League Seasons | 0 |
| League Season Provider Mappings | 0 |

Controlli eseguiti:

- ✅ Nessun paese orfano
- ✅ Nessuna lega orfana
- ✅ Nessun duplicato mapping
- ✅ Foreign key corrette
- ✅ Vincoli univoci corretti

---

# Convenzioni

- Il dominio usa esclusivamente gli ID interni.
- I provider vengono astratti dal Mapping Layer.
- Tutti i provider futuri dovranno adattarsi al Registry, mai il contrario.

---

# Roadmap

## Completati

- ✅ SETUP_DATI_1.1 Football Registry
- ✅ SETUP_DATI_1.2 League Seasons

## Successivo

**SETUP_DATI_1.3 — League Seasons Importer**

Pipeline:

```text
API-Football
      │
      ▼
Importer
      ▼
Dry Run
      ▼
Audit
      ▼
Apply
      ▼
seasons
league_seasons
league_season_provider_mappings
```

Successivamente saranno integrati Football-Data.org e SportMonks senza modificare il modello dati.

---

# Conclusione

Con questo step Fanta Oracle dispone di un Registry internazionale, indipendente dai provider e pronto a sostenere l'evoluzione verso Team Seasons, Player Seasons e Oracle Engine.
