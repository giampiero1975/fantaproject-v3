<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-6 text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-white">Imposta nuova password</h1>
            <p class="mt-2 text-sm leading-6 text-slate-400">Scegli una password sicura per rientrare nel tuo account.</p>
        </div>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <x-label for="email" value="Email" />
                <x-input id="email" class="mt-2 w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" placeholder="Inserisci la tua email" />
            </div>

            <div>
                <x-label for="password" value="Password" />
                <x-input id="password" class="mt-2 w-full" type="password" name="password" required autocomplete="new-password" placeholder="Nuova password" />
            </div>

            <div>
                <x-label for="password_confirmation" value="Conferma password" />
                <x-input id="password_confirmation" class="mt-2 w-full" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Ripeti la nuova password" />
            </div>

            <x-button class="w-full">
                Aggiorna password
            </x-button>
        </form>
    </x-authentication-card>
</x-guest-layout>
