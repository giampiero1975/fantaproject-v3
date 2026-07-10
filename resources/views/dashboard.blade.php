<x-app-layout>
    <x-slot name="header">
        <x-fo.page-header
            eyebrow="Workspace"
            title="Dashboard"
            description="Benvenuto in Fanta Oracle. Le funzionalità disponibili dipendono dal tuo ruolo e dai permessi assegnati."
        />
    </x-slot>

    <div class="space-y-6">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-fo.stat label="Profilo" value="Attivo" hint="Account verificato" icon="user-circle" tone="green" />
            <x-fo.stat label="Proiezioni" value="—" hint="Modulo in preparazione" icon="chart-bar-square" tone="purple" />
            <x-fo.stat label="Watchlist" value="0" hint="Nessun giocatore seguito" icon="star" tone="blue" />
            <x-fo.stat label="Notifiche" value="0" hint="Nessun nuovo avviso" icon="bell" tone="amber" />
        </section>

        <x-fo.panel
            title="Fanta Oracle Workspace"
            description="Questa area diventerà il punto di accesso alle funzionalità utente del motore predittivo."
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                @foreach ([
                    ['Proiezioni', 'Consulta le future proiezioni elaborate dal motore.', 'sparkles'],
                    ['Giocatori', 'Ricerca, confronto e analisi dei calciatori.', 'users'],
                    ['Strumenti', 'Watchlist, report e strumenti decisionali.', 'wrench-screwdriver'],
                ] as [$title, $description, $icon])
                    <article class="rounded-xl border border-white/10 bg-white/[0.025] p-5">
                        <div class="flex size-10 items-center justify-center rounded-xl bg-oracle-600/10 text-oracle-300 ring-1 ring-oracle-500/20">
                            <flux:icon :name="$icon" class="size-5" />
                        </div>
                        <h3 class="mt-4 font-semibold text-white">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-400">{{ $description }}</p>
                    </article>
                @endforeach
            </div>
        </x-fo.panel>
    </div>
</x-app-layout>
