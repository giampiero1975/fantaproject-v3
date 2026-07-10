<x-app-layout>
    <x-slot name="header">
        <x-fo.page-header
            eyebrow="Administration"
            title="Fanta Oracle Control Center"
            description="Vista operativa centralizzata per configurazione, diagnostica e processi del motore predittivo."
        >
            <x-slot name="actions">
                <flux:badge color="green" size="sm">Sistema operativo</flux:badge>
                <flux:button variant="primary" icon="bolt">Azione rapida</flux:button>
            </x-slot>
        </x-fo.page-header>
    </x-slot>

    <div class="space-y-6">
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-fo.stat label="Utenti attivi" value="1" hint="Ruoli gestiti con Spatie" icon="users" tone="purple" />
            <x-fo.stat label="Job in coda" value="0" hint="Nessuna elaborazione pendente" icon="queue-list" tone="blue" />
            <x-fo.stat label="Stato API" value="Online" hint="Endpoint applicativi disponibili" icon="signal" tone="green" />
            <x-fo.stat label="Alert sistema" value="0" hint="Nessuna anomalia rilevata" icon="bell-alert" tone="amber" />
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.35fr_0.65fr]">
            <x-fo.panel
                title="Aree operative"
                description="Le sezioni principali del Control Center verranno sviluppate all'interno di questa struttura."
            >
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    @foreach ([
                        ['Administration', 'Utenti, ruoli, database e configurazione.', 'shield-check', 'oracle'],
                        ['Diagnostics', 'Stato sistema, code, log e connettività API.', 'heart', 'blue'],
                        ['Operations', 'Importazioni, proiezioni e processi del motore.', 'wrench-screwdriver', 'green'],
                        ['AI Intelligence', 'Modelli locali, agenti e analisi assistita.', 'cpu-chip', 'amber'],
                    ] as [$title, $description, $icon, $tone])
                        <article class="group rounded-xl border border-white/10 bg-white/[0.025] p-5 transition hover:-translate-y-0.5 hover:border-oracle-500/35 hover:bg-white/[0.045]">
                            <div class="flex items-start gap-4">
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-oracle-600/10 text-oracle-300 ring-1 ring-oracle-500/20">
                                    <flux:icon :name="$icon" class="size-5" />
                                </div>
                                <div>
                                    <h3 class="font-semibold text-white">{{ $title }}</h3>
                                    <p class="mt-1 text-sm leading-6 text-slate-400">{{ $description }}</p>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </x-fo.panel>

            <x-fo.panel title="Ambiente" description="Configurazione corrente dell'applicazione.">
                <dl class="space-y-4 text-sm">
                    <div class="flex items-center justify-between gap-4 border-b border-white/10 pb-4">
                        <dt class="text-slate-400">Framework</dt>
                        <dd class="font-medium text-white">Laravel 12</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-white/10 pb-4">
                        <dt class="text-slate-400">Interfaccia</dt>
                        <dd class="font-medium text-white">Livewire + Flux</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-white/10 pb-4">
                        <dt class="text-slate-400">Autorizzazioni</dt>
                        <dd class="font-medium text-white">Spatie</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-slate-400">Database</dt>
                        <dd class="font-medium text-white">PostgreSQL</dd>
                    </div>
                </dl>
            </x-fo.panel>
        </section>
    </div>
</x-app-layout>
