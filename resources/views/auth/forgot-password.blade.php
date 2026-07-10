<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-6 text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-white">Recupera password</h1>
            <p class="mt-2 text-sm leading-6 text-slate-400">
                Inserisci la tua email e ti invieremo il link per scegliere una nuova password.
            </p>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-emerald-400/20 bg-emerald-500/10 p-4 text-sm font-medium text-emerald-100">
                {{ session('status') }}
            </div>
        @endif

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf

            <div>
                <x-label for="email" value="Email" />
                <x-input id="email" class="mt-2 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="Inserisci la tua email" />
            </div>

            <div class="space-y-4">
                <x-button class="w-full">
                    Invia link di reset
                </x-button>

                <a href="{{ route('login') }}" class="block text-center text-sm font-medium text-[#B470FF] transition hover:text-white">
                    Torna al login
                </a>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
