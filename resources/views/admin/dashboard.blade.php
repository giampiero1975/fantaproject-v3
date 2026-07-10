<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#8B7CFF]">Amministrazione</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight text-white">
                    Fanta Oracle Control Center
                </h1>
            </div>

            <span class="rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-300">
                Admin
            </span>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl border border-white/10 bg-[#08152B]/85 shadow-2xl shadow-black/20 backdrop-blur-xl">
                <div class="border-b border-white/10 px-6 py-6 sm:px-8">
                    <h2 class="text-xl font-semibold text-white">Benvenuto nell'Area Amministrativa</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-400">
                        Da qui inizieremo il porting delle funzionalità di amministrazione di Fanta Oracle.
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-5 p-6 sm:grid-cols-2 sm:p-8 lg:grid-cols-3">
                    <article class="group rounded-xl border border-white/10 bg-white/[0.035] p-5 transition hover:-translate-y-0.5 hover:border-[#7B2CFF]/50 hover:bg-white/[0.055]">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold text-white">Utenti</h3>
                                <p class="mt-1 text-sm text-slate-400">Ruoli, permessi e gestione iscritti</p>
                            </div>
                            <span class="rounded-lg bg-[#7B2CFF]/15 px-2.5 py-1 text-xs font-medium text-[#B99AFF]">Spatie</span>
                        </div>
                    </article>

                    <article class="group rounded-xl border border-white/10 bg-white/[0.035] p-5 transition hover:-translate-y-0.5 hover:border-[#2962FF]/50 hover:bg-white/[0.055]">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-semibold text-white">Impostazioni</h3>
                                <p class="mt-1 text-sm text-slate-400">Configurazioni globali della piattaforma</p>
                            </div>
                            <span class="rounded-lg bg-[#2962FF]/15 px-2.5 py-1 text-xs font-medium text-[#8FB1FF]">Config</span>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
