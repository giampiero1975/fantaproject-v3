<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div x-data="{ recovery: false }">
            <div class="mb-6 text-center">
                <h1 class="text-2xl font-semibold tracking-tight text-white">Verifica accesso</h1>
                <p class="mt-2 text-sm leading-6 text-slate-400" x-show="! recovery">
                    Inserisci il codice generato dalla tua app di autenticazione.
                </p>
                <p class="mt-2 text-sm leading-6 text-slate-400" x-cloak x-show="recovery">
                    Inserisci uno dei codici di recupero del tuo account.
                </p>
            </div>

            <x-validation-errors class="mb-4" />

            <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-5">
                @csrf

                <div x-show="! recovery">
                    <x-label for="code" value="Codice autenticazione" />
                    <x-input id="code" class="mt-2 w-full" type="text" inputmode="numeric" name="code" autofocus x-ref="code" autocomplete="one-time-code" placeholder="Inserisci il codice" />
                </div>

                <div x-cloak x-show="recovery">
                    <x-label for="recovery_code" value="Codice di recupero" />
                    <x-input id="recovery_code" class="mt-2 w-full" type="text" name="recovery_code" x-ref="recovery_code" autocomplete="one-time-code" placeholder="Inserisci il codice di recupero" />
                </div>

                <div class="space-y-4">
                    <button type="button" class="block w-full text-center text-sm font-medium text-[#B470FF] transition hover:text-white"
                        x-show="! recovery"
                        x-on:click="
                            recovery = true;
                            $nextTick(() => { $refs.recovery_code.focus() })
                        ">
                        Usa un codice di recupero
                    </button>

                    <button type="button" class="block w-full text-center text-sm font-medium text-[#B470FF] transition hover:text-white"
                        x-cloak
                        x-show="recovery"
                        x-on:click="
                            recovery = false;
                            $nextTick(() => { $refs.code.focus() })
                        ">
                        Usa il codice dell'app
                    </button>

                    <x-button class="w-full">
                        Accedi
                    </x-button>
                </div>
            </form>
        </div>
    </x-authentication-card>
</x-guest-layout>
