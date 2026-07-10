<div>
    <x-validation-errors class="mb-4 rounded-lg border border-red-400/20 bg-red-500/10 p-4 text-sm text-red-100" />

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-400/20 bg-emerald-500/10 p-4 text-sm font-medium text-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="mb-2 block text-sm font-medium text-slate-300">Email</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                placeholder="Inserisci la tua email"
                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                class="block h-12 w-full rounded-md border border-white/15 bg-[#07101F] px-4 text-base text-white shadow-sm outline-none transition placeholder:text-slate-500 focus:border-[#7B2CFF] focus:ring-2 focus:ring-[#7B2CFF]/45"
            />

            @error('email')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-2 block text-sm font-medium text-slate-300">Password</label>
            <div class="relative">
                <input
                    id="password"
                    name="password"
                    type="{{ $showPassword ? 'text' : 'password' }}"
                    required
                    autocomplete="current-password"
                    placeholder="Inserisci la password"
                    aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                    class="block h-12 w-full rounded-md border border-white/15 bg-[#07101F] px-4 pe-12 text-base text-white shadow-sm outline-none transition placeholder:text-slate-500 focus:border-[#7B2CFF] focus:ring-2 focus:ring-[#7B2CFF]/45"
                />
                <button
                    type="button"
                    wire:click="togglePassword"
                    class="absolute inset-y-0 right-3 flex items-center text-slate-400 transition hover:text-white"
                    aria-label="{{ $showPassword ? 'Nascondi password' : 'Mostra password' }}"
                >
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>

            @error('password')
                <p class="mt-2 text-sm text-red-300">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between gap-4">
            <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-slate-300">
                <input id="remember_me" name="remember" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-[#07101F] text-[#7B2CFF] focus:ring-[#7B2CFF]/45">
                <span>Ricordami</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-[#B470FF] transition hover:text-white" href="{{ route('password.request') }}">
                    Password dimenticata?
                </a>
            @endif
        </div>

        <button type="submit" class="inline-flex h-12 w-full items-center justify-center rounded-md bg-gradient-to-r from-[#8B2CFF] to-[#2962FF] px-5 text-sm font-semibold text-white shadow-lg shadow-[#2962FF]/25 transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[#7B2CFF]/60 focus:ring-offset-2 focus:ring-offset-[#071225]">
            Accedi
        </button>
    </form>

    @if (Route::has('register'))
        <p class="mt-5 text-center text-sm text-slate-400">
            Non hai un account?
            <a href="{{ route('register') }}" class="font-semibold text-[#B470FF] transition hover:text-white">Registrati</a>
        </p>
    @endif
</div>
