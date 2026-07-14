<x-app-layout>
    <x-slot name="header">
        <x-fo-page-header
            title="Gestione Stagioni"
            subtitle="Step 1 · discovery, confronto provider e sincronizzazione controllata"
        />
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-fo-card class="lg:col-span-2">
            <div class="space-y-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">Provider congruity audit</h2>
                    <p class="mt-1 text-sm text-slate-400">
                        Il primo blocco è volutamente non distruttivo: normalizza i payload di football-data.org e API-Football e confronta le squadre della stessa stagione.
                    </p>
                </div>

                <div class="rounded-xl border border-white/10 bg-black/20 p-4 font-mono text-sm text-slate-300">
                    php artisan season:audit-providers --competition=SA --league-id=135 --season=2024 --json
                </div>

                <div class="rounded-xl border border-amber-400/20 bg-amber-400/5 p-4 text-sm text-amber-100">
                    DRY-RUN obbligatorio: nessuna scrittura su database viene eseguita da questo comando.
                </div>
            </div>
        </x-fo-card>

        <x-fo-card>
            <div class="space-y-3">
                <h2 class="text-lg font-semibold text-white">Stato Step 1</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-400">Normalizer</dt>
                        <dd class="font-medium text-emerald-300">Pronto</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-400">Congruity validator</dt>
                        <dd class="font-medium text-emerald-300">Pronto</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-400">Dry-run</dt>
                        <dd class="font-medium text-emerald-300">Pronto</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-400">Persistenza</dt>
                        <dd class="font-medium text-slate-400">Bloccata fino ai test</dd>
                    </div>
                </dl>
            </div>
        </x-fo-card>
    </div>
</x-app-layout>
