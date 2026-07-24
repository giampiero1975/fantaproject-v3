# Step 4 - Tier Squadre

## Origine V2

Porting dello step V2 `documentazione_step_4_tier.md` e del motore operativo `TeamDataService::updateTeamTiers()`.

La V3 mantiene il principio del motore Gold Standard:

- punteggio piu basso = squadra piu forte;
- componente storica pesata;
- componente momentum recente;
- fusione storico/momentum;
- penalita trend negativo;
- moltiplicatori per competizione;
- soglie finali per assegnare il tier.

## Differenze V3

La V3 non usa scraping e non legge direttamente provider dentro questo step.

Il calcolo legge solo il dataset canonico gia normalizzato:

- `teams`
- `league_season_teams`
- `league_season_team_standings`
- `league_seasons`
- `seasons`
- `leagues`

Il layer provider e le chiamate HTTP devono essere gia state gestite negli step precedenti.

## Configurazione

Niente file config per le regole tier.

Le impostazioni vivono in tabella:

- `team_tier_settings`

Campi principali:

- `setting_group`
- `setting_key`
- `value` JSON
- `data_type`
- `label`
- `description`
- `sort_order`

Questa scelta evita configurazioni miste tra file e DB. Se in futuro emergono altri micro-file di configurazione collegati allo stesso dominio, vanno valutati per accorpamento in una tabella di configurazione/pivot dedicata.

## Auto-tuning da audit

L'audit iniziale su Serie A 2023/24, 2024/25 e 2025/26 ha evidenziato una sovrastima ricorrente delle neopromosse: una stagione molto forte in Serie B non equivale automaticamente a una forza alta in Serie A.

Per correggere il bias senza hardcoding e senza cambiare il modello base, e stata aggiunta la configurazione:

```text
team_tier_settings
setting_group = transition_penalties
setting_key   = by_case
value         = {"promoted_from_lower_league":1.25}
```

Il moltiplicatore entra solo quando l'ultima stagione storica disponibile della squadra appartiene a una lega diversa dalla lega target calcolata. Esempio: calcolo Serie A e ultimo storico della squadra in Serie B.

Nel report tecnico viene esposto anche:

```text
transition_penalty
```

Valore `1.0` significa nessuna transizione rilevata. Valore `1.25` significa penalita neopromossa applicata.

### Roadmap primo layer

Il primo layer deve continuare a usare esclusivamente i dati canonici gia disponibili, ma puo estrarne piu segnale. Le evoluzioni da verificare tramite backtest walk-forward sono:

- rendimento normalizzato rispetto alle altre squadre della stessa stagione;
- punti per partita, invece dei soli punti assoluti;
- differenza reti e rendimento offensivo/difensivo separati;
- andamento delle ultime stagioni come pendenza, non solo come media pesata;
- volatilita, per distinguere una squadra stabile da una imprevedibile;
- regressione verso la media dopo stagioni eccezionali o disastrose;
- forza delle neopromosse basata sul rendimento reale in Serie B, invece del solo moltiplicatore fisso `1.25`;
- affidabilita del punteggio in base alla quantita di storico disponibile.

Ogni modifica deve essere confrontata con la baseline walk-forward su piu stagioni. Un miglioramento isolato sull'ultima stagione non e sufficiente e viene scartato come possibile overfitting.

### Roadmap segnali contestuali

I dati contestuali potrebbero migliorare il motore piu di ulteriori ritocchi ai soli pesi storici. Restano ipotesi da misurare tramite audit e non entrano nel motore finche non superano i guardrail walk-forward.

Segnali candidati:

- `coach_change`: cambio allenatore, data, esperienza e rendimento storico;
- `squad_continuity`: percentuale di minuti conservati rispetto alla stagione precedente;
- `squad_strength_delta`: miglioramento o indebolimento della rosa;
- `market_value_rank`: forza economica relativa nella competizione;
- `preseason_odds_rank`: aspettativa aggregata del mercato prima dell'inizio;
- `promoted_relative_strength`: rendimento della neopromossa normalizzato rispetto alla propria Serie B;
- `european_load`: partecipazione alle coppe e numero previsto di partite;
- `injury_impact`: indisponibilita pesate per importanza del calciatore;
- `advanced_performance`: xG, xGA, tiri, occasioni e differenza reti attesa;
- `coach_squad_fit`: valutazione della compatibilita tra allenatore e rosa, inizialmente anche manuale e dichiaratamente soggettiva.

Ogni osservazione contestuale dovra conservare almeno:

```text
team_id
league_season_id
signal_key
numeric_value oppure categorical_value
confidence
source_type
source_reference
observed_at
projection_cutoff_at
```

`projection_cutoff_at` e obbligatorio: l'audit deve usare soltanto informazioni disponibili al momento della proiezione, evitando contaminazioni con dati conosciuti successivamente.

La prima valutazione dovrebbe concentrarsi su:

```text
coach_change
squad_continuity
squad_strength_delta
preseason_odds_rank
european_load
```

L'acquisizione puo iniziare dalla UI con fonte, data e livello di confidenza. L'automazione o l'acquisto di nuove API devono essere valutati soltanto dopo aver misurato l'utilita predittiva del segnale.

### Dataset per analisi AI futura

Convenzione architetturale:

```text
le tabelle destinate ad audit e analisi AI iniziano sempre con ai_
```

L'audit dei segnali non modifica il motore tier. Ricostruisce per ogni squadra cio che era conoscibile prima della stagione target e usa il risultato finale soltanto come outcome.

Tabelle:

```text
ai_team_tier_audit_runs
ai_team_tier_audit_observations
ai_team_tier_audit_metrics
```

Ogni osservazione contiene colonne esplicite, non un campo metadata generico:

- posizione, tier e score previsti;
- posizione, tier e score reali;
- copertura dello storico;
- punti per partita;
- gol fatti, subiti e differenza reti per partita;
- forza relativa nell'ultima lega disputata;
- pendenza del rendimento storico;
- volatilita;
- distanza dell'ultima stagione dalla media storica;
- indicatore neopromossa;
- forza relativa nella lega inferiore.

La forza relativa e normalizzata tra `0` e `1` usando posizione e numero di squadre della competizione. Valore `1` indica il miglior piazzamento.

La pendenza usa lo storico in ordine cronologico:

```text
positiva = rendimento in crescita
negativa = rendimento in calo
```

La volatilita e la deviazione standard della forza relativa storica. Il `regression_gap` confronta l'ultima stagione con la media disponibile:

```text
positivo = ultima stagione sopra la propria media
negativo = ultima stagione sotto la propria media
```

`ai_team_tier_audit_metrics` salva, per ogni segnale, numerosita e correlazione di Pearson con la forza relativa reale della stagione successiva. Questi valori servono per valutare utilita concettuale e stabilita dei segnali, non autorizzano modifiche automatiche ai parametri.

Primo audit su 60 osservazioni, Serie A 2023/24-2025/26:

```text
Segnale                              Pearson r   Campione
Score tier attuale                    -0.6750       60
Differenza reti per partita            0.5366       60
Gol fatti per partita                  0.5113       60
Punti per partita                      0.4946       60
Forza relativa ultima stagione         0.4635       60
Gol subiti per partita                -0.4381       60
Forza relativa lega inferiore          0.3604        9
Neopromossa                            -0.3602       60
Volatilita storica                    -0.2057       40
Distanza dalla media storica          -0.0594       60
Pendenza rendimento                   -0.0349       40
Copertura storico                      0.0000       60
```

Interpretazione iniziale:

- differenza reti, attacco, punti e forza relativa sono segnali promettenti;
- il segno negativo dei gol subiti e coerente: piu gol subiti anticipano minore forza futura;
- la condizione di neopromossa conferma un impatto negativo;
- la forza realmente mostrata nella lega inferiore contiene segnale utile, ma il campione di 9 squadre e ancora piccolo;
- volatilita mostra un primo segnale negativo moderato;
- pendenza e regressione verso la media non risultano validate dal campione attuale;
- la copertura storico non puo essere giudicata con questo dataset, perche varia soprattutto tra stagioni e non tra squadre della stessa stagione.

Questi risultati descrivono associazioni, non causalita. Prima di attivare nuovi pesi occorre verificarne stabilita su altre stagioni o campionati.

### Verifica formale auto-tuning

Il profilo attivo viene confrontato con una baseline registrata nel DB:

```text
team_tier_settings
auto_tuning_profiles.legacy_baseline
```

Anche le regole di accettazione sono configurate nel DB:

```text
team_tier_settings
auto_tuning_guards.acceptance
```

Guardrail iniziali:

```text
uplift medio minimo:             +0.10 punti percentuali
peggioramento massimo stagione:   0.00 punti percentuali
aumento massimo MAE:              0.00
```

Il benchmark usa override in memoria. Non modifica temporaneamente e non riscrive `team_tier_settings`.

La persistenza AI usa:

```text
ai_team_tier_tuning_runs
ai_team_tier_tuning_candidates
ai_team_tier_tuning_candidate_seasons
```

Per ogni candidato vengono salvati:

- tutti i pesi effettivi;
- penalita neopromosse;
- accuratezza media, MAE e accuratezza tier;
- uplift rispetto alla baseline;
- esito dei guardrail;
- risultati separati per ogni stagione.

Il run di tuning mantiene inoltre il collegamento al dataset dei segnali in `ai_team_tier_audit_runs`, rendendo riproducibile il contesto fornito a una futura AI.

Baseline iniziale Serie A:

```text
2023/24: 77.14%
2024/25: 80.15%
2025/26: 84.36%
Media:   80.55%
```

La percentuale rappresenta la correlazione di rango di Spearman convertita in percentuale. Non rappresenta la probabilita di indovinare una singola posizione.

Primo esperimento scartato:

```text
posizione normalizzata con peso 10%
2025/26: 85.41%
media:   79.60%
```

La variante migliora l'ultima stagione ma peggiora il comportamento complessivo, quindi non viene attivata.

Primo profilo auto-tuning approvato:

```text
weights.metrics = {
  "points": 0.48,
  "goals_for": 0.34,
  "goals_against": 0.18
}

weights.fusion = {
  "historical": 0.65,
  "momentum": 0.35
}
```

Confronto walk-forward:

```text
          Baseline  Profilo approvato
2023/24     77.14%             77.44%
2024/25     80.15%             80.75%
2025/26     84.36%             85.11%
Media       80.55%             81.10%
MAE           2.67               2.63
```

Il profilo e stato approvato perche migliora tutte le stagioni osservate e riduce l'errore medio di posizione. La penalita neopromosse resta `1.25`.

### Verifica incrementale dei segnali

La griglia degli esperimenti e registrata nel DB:

```text
team_tier_settings
auto_tuning_experiments.incremental_grid
```

Il benchmark combina 4 profili metrici, 5 trattamenti delle neopromosse e 5 fattori di volatilita: 100 candidati. Ogni candidato incrementale viene confrontato con il profilo attivo, non con la baseline legacy.

Esito sul campione Serie A 2023/24-2025/26:

```text
Profilo attivo:            ranking 81.10% · MAE 2.63
GD 10%:                    ranking 81.15% · MAE 2.60
GD 15%:                    ranking 81.30% · MAE 2.57
Neopromosse dinamiche:     nessun miglioramento stabile
Penalita volatilita:       nessun miglioramento
```

Il profilo con differenza reti al 15% migliora media e MAE, ma perde `0.45` punti percentuali nel 2023/24. Viola quindi il guardrail che vieta peggioramenti stagionali e non viene applicato.

Conclusione: i nuovi segnali contengono informazione utile, ma sul campione attuale nessuna delle 100 formulazioni aggiunge valore stabile al motore gia attivo. Tutti i candidati, i parametri, gli esiti stagionali e i motivi di rifiuto vengono conservati nelle tabelle `ai_`; `team_tier_settings` non viene modificata.

## Comandi

Audit readiness, senza scritture:

```bash
php artisan team-tiers:audit --league-season-id=120 --json
```

Audit prestazione reale, senza scritture:

```bash
php artisan team-tiers:audit-performance --league-season-id=120 --json
```

Audit walk-forward multi-stagione, senza scritture:

```bash
php artisan team-tiers:audit-walk-forward \
  --league-season-id=4 \
  --league-season-id=3 \
  --league-season-id=2 \
  --json
```

In alternativa e possibile selezionare tutte le stagioni verificabili di una competizione:

```bash
php artisan team-tiers:audit-walk-forward --league-id=120 --json
```

Audit dei segnali, senza scritture:

```bash
php artisan team-tiers:audit-signals \
  --league-season-id=4 \
  --league-season-id=3 \
  --league-season-id=2 \
  --json
```

Persistenza del dataset nelle tabelle `ai_`:

```bash
php artisan team-tiers:audit-signals \
  --league-season-id=4 \
  --league-season-id=3 \
  --league-season-id=2 \
  --persist \
  --json
```

Verifica auto-tuning senza scritture:

```bash
php artisan team-tiers:audit-auto-tuning \
  --league-season-id=4 \
  --league-season-id=3 \
  --league-season-id=2
```

Persistenza della validazione nelle tabelle `ai_`:

```bash
php artisan team-tiers:audit-auto-tuning \
  --league-season-id=4 \
  --league-season-id=3 \
  --league-season-id=2 \
  --persist \
  --json
```

Questo audit confronta il valore atteso del tier con la prestazione reale della classifica finale, usando la stessa metrica punti/gol fatti/gol subiti del motore. Non controlla soltanto la posizione finale.

Dry-run:

```bash
php artisan team-tiers:sync --league-season-id=120 --json
```

Apply:

```bash
php artisan team-tiers:sync --league-season-id=120 --apply --json
```

## UI

Menu:

```text
Administration -> Data Platform -> Tier Squadre
```

La UI espone:

- copertura tier per lega/stagione;
- filtro a imbuto;
- ricerca rapida;
- analisi dry-run;
- audit prestazione reale;
- apply con conferma `CALCOLA`;
- registry tier gia calcolati;
- ultimo report tecnico.

## Scritture DB

Apply aggiorna:

- `teams.tier_globale`
- `teams.posizione_media_storica`
- `league_season_teams.tier_stagionale`
- `league_season_teams.tier_score`

`teams.tier_globale` e `teams.posizione_media_storica` rappresentano il valore globale/corrente e vengono aggiornati solo quando si applica una stagione corrente.

Lo storico stagionale rimane nella pivot:

- `league_season_teams.tier_stagionale`
- `league_season_teams.tier_score`

Non duplica dettagli tecnici nei metadata.

## Log

File dedicato:

```text
storage/logs/administration/tier-squadre/tier-squadre.log
```

Formato righe:

```text
[timestamp][tier_squadre][INFO] messaggio {context}
[timestamp][tier_squadre][ERROR] messaggio {context}
```

Il log viene riscritto a ogni esecuzione, coerente con la regola attuale dei log funzionali.

## Test

Test principale:

```text
tests/Feature/SyncTeamTiersCommandTest.php
```

Copre:

- settings caricati da DB;
- audit readiness senza scrittura;
- dry-run senza scrittura;
- apply con scrittura su team e pivot stagionale;
- log dedicato generato.



## Compatibilita V2

La V2 esponeva il comando `teams:update-tiers` in `app/Console/Commands/Core/TeamsUpdateTiers.php`.

In V3 il comando resta disponibile come alias controllato verso il motore nuovo:

```bash
php artisan teams:update-tiers --league-season-id=120 --apply
```

L'opzione `--year` e supportata solo quando risolve una singola riga `league_seasons`. Se l'anno e ambiguo, il comando mostra gli ID disponibili e si ferma: in V3 la competizione va resa esplicita.

## Fonti V2 ispezionate

Sono state verificate entrambe le tracce presenti nel sorgente V2:

- `app/Services/TeamDataService.php` -> motore Gold Standard 70/30, scelto come sorgente funzionale dello Step 4 V3;
- `app/Console/Commands/UpdateTeamTiersCommand.php` -> wrapper del motore Gold Standard;
- `app/Console/Commands/Core/TeamsUpdateTiers.php` -> comando V2 piu recente lato Core, mantenuto in V3 come alias compatibile;
- `app/Services/TeamTieringService.php` + `config/team_tiering_settings.php` -> motore legacy percentile/min-max, non attivato in V3 per evitare doppia logica concorrente.

I parametri attivi non sono su file: sono registrati in `team_tier_settings`. Se in futuro servira recuperare il motore percentile come confronto diagnostico, andra introdotto come profilo disattivato in tabella, non come file config.
