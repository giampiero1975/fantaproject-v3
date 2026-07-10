<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-6 text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-white">Crea il tuo account</h1>
            <p class="mt-2 text-sm leading-6 text-slate-400">Accedi alla nuova piattaforma Fanta Oracle.</p>
        </div>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('register') }}" class="space-y-5">
            @csrf

            <div>
                <x-label for="name" value="Nome" />
                <x-input id="name" class="mt-2 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" placeholder="Il tuo nome" />
            </div>

            <div>
                <x-label for="email" value="Email" />
                <x-input id="email" class="mt-2 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="La tua email" />
            </div>

            <div>
                <x-label for="password" value="Password" />
                <x-input id="password" class="mt-2 w-full" type="password" name="password" required autocomplete="new-password" placeholder="Crea una password" />
            </div>

            <div>
                <x-label for="password_confirmation" value="Conferma password" />
                <x-input id="password_confirmation" class="mt-2 w-full" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Ripeti la password" />
            </div>

            @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                <label for="terms" class="flex items-start gap-3 text-sm leading-6 text-slate-300">
                    <x-checkbox name="terms" id="terms" required class="mt-1" />
                    <span>
                        Accetto
                        <a target="_blank" href="{{ route('terms.show') }}" class="font-medium text-[#B470FF] transition hover:text-white">termini di servizio</a>
                        e
                        <a target="_blank" href="{{ route('policy.show') }}" class="font-medium text-[#B470FF] transition hover:text-white">privacy policy</a>.
                    </span>
                </label>
            @endif

            <div class="space-y-4">
                <x-button class="w-full">
                    Registrati
                </x-button>

                <a class="block text-center text-sm font-medium text-[#B470FF] transition hover:text-white" href="{{ route('login') }}">
                    Hai gia un account? Accedi
                </a>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
