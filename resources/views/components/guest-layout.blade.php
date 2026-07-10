<div class="min-h-screen bg-[#0D1B3D] text-slate-100">
    <div class="absolute inset-x-0 top-0 h-64 bg-gradient-to-r from-[#7B2CFF] via-[#2962FF] to-[#0D1B3D] opacity-40 blur-3xl"></div>
    <div class="relative z-10 flex min-h-screen items-center justify-center px-4 py-10 sm:px-6 lg:px-8">
        <div class="w-full max-w-6xl">
            <div class="grid gap-8 lg:grid-cols-2 lg:items-center">
                <div class="hidden rounded-[2rem] border border-[#7B2CFF]/20 bg-[#08112A]/80 p-10 shadow-2xl shadow-[#0D1B3D]/50 backdrop-blur-xl lg:block">
                    <div class="mb-8">
                        <h1 class="text-4xl font-semibold tracking-tight text-white"><span class="text-[#7B2CFF]">Fanta</span><span class="text-[#2962FF]">Oracle</span></h1>
                        <p class="mt-4 max-w-xl text-base text-slate-300">Analizza, predici e gestisci il tuo fantacalcio con una dashboard intelligente, accessi protetti e un design moderno.</p>
                    </div>
                    <div class="space-y-6">
                        <div class="rounded-3xl border border-white/10 bg-slate-950/70 p-6">
                            <h2 class="text-xl font-semibold text-white">Design corporate</h2>
                            <p class="mt-2 text-sm text-slate-300">Un’interfaccia elegante con palette viola-blu e componenti ottimizzati per la tua esperienza.</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-slate-950/70 p-6">
                            <h2 class="text-xl font-semibold text-white">Login sicuro</h2>
                            <p class="mt-2 text-sm text-slate-300">Autenticazione con password e due fattori pronta all’uso.</p>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-slate-950/70 p-6">
                            <h2 class="text-xl font-semibold text-white">Postgres pronto</h2>
                            <p class="mt-2 text-sm text-slate-300">Database configurato con il tuo schema `fantaproject_v3`.</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-[#7B2CFF]/15 bg-[#071023]/95 shadow-2xl shadow-[#0D1B3D]/40 backdrop-blur-xl">
                    <div class="p-8 sm:p-10">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
