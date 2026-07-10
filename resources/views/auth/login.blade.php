<x-guest-layout>
    <div class="relative min-h-screen overflow-hidden bg-[#050B18] text-white selection:bg-[#7B2CFF]/30 selection:text-white">
        <div class="absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(123,44,255,0.22),transparent_28%),radial-gradient(circle_at_78%_18%,rgba(41,98,255,0.18),transparent_30%),linear-gradient(135deg,#050B18_0%,#071226_48%,#0B1026_100%)]"></div>
            <div class="absolute inset-x-0 bottom-0 h-1/2 bg-[linear-gradient(180deg,transparent,rgba(3,8,20,0.92))]"></div>
        </div>

        <main class="relative z-10 grid min-h-screen grid-cols-1 xl:grid-cols-[minmax(360px,0.82fr)_minmax(720px,1.8fr)]">
            <section class="flex min-h-screen items-center justify-center border-white/10 px-5 py-8 xl:border-r xl:px-8">
                <x-auth.card title="Accedi al tuo account" description="Entra nella tua area personale.">
                    <livewire:auth.login-form />
                </x-auth.card>
            </section>

            <x-auth.product-preview />
        </main>
    </div>
</x-guest-layout>
