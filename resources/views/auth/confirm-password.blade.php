<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-6 text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-white">Conferma password</h1>
            <p class="mt-2 text-sm leading-6 text-slate-400">Per sicurezza, conferma la password prima di continuare.</p>
        </div>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
            @csrf

            <div>
                <x-label for="password" value="Password" />
                <x-input id="password" class="mt-2 w-full" type="password" name="password" required autocomplete="current-password" autofocus placeholder="Inserisci la password" />
            </div>

            <x-button class="w-full">
                Conferma
            </x-button>
        </form>
    </x-authentication-card>
</x-guest-layout>
