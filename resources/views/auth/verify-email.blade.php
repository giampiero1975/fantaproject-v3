<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-6 text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-white">Verifica la tua email</h1>
            <p class="mt-2 text-sm leading-6 text-slate-400">
                Ti abbiamo inviato un link di verifica. Aprilo dalla tua casella email per continuare.
            </p>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="mb-4 rounded-lg border border-emerald-400/20 bg-emerald-500/10 p-4 text-sm font-medium text-emerald-100">
                Un nuovo link di verifica e stato inviato al tuo indirizzo email.
            </div>
        @endif

        <div class="space-y-4">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf

                <x-button type="submit" class="w-full">
                    Reinvia email di verifica
                </x-button>
            </form>

            <div class="flex items-center justify-center gap-4 text-sm">
                <a href="{{ route('profile.show') }}" class="font-medium text-[#B470FF] transition hover:text-white">
                    Modifica profilo
                </a>

                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf

                    <button type="submit" class="font-medium text-slate-400 transition hover:text-white">
                        Esci
                    </button>
                </form>
            </div>
        </div>
    </x-authentication-card>
</x-guest-layout>
