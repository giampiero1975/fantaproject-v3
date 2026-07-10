<x-guest-layout>
    <div class="min-h-screen bg-[#050B18] text-white selection:bg-[#7B2CFF]/30 selection:text-white">
        <div class="absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(123,44,255,0.22),transparent_28%),radial-gradient(circle_at_78%_18%,rgba(41,98,255,0.18),transparent_30%),linear-gradient(135deg,#050B18_0%,#071226_48%,#0B1026_100%)]"></div>
            <div class="absolute inset-x-0 bottom-0 h-1/2 bg-[linear-gradient(180deg,transparent,rgba(3,8,20,0.92))]"></div>
        </div>

        <main class="relative z-10 grid min-h-screen grid-cols-1 xl:grid-cols-[minmax(360px,0.82fr)_minmax(720px,1.8fr)]">
            <section class="flex min-h-screen items-center justify-center border-white/10 px-5 py-8 xl:border-r xl:px-8">
                <div class="w-full max-w-[470px] overflow-hidden rounded-lg border border-white/15 bg-[#071225]/78 shadow-2xl shadow-black/35 backdrop-blur-xl">
                    <div class="relative min-h-[260px] overflow-hidden border-b border-white/10 bg-[#08152B] px-6 pb-8 pt-8">
                        <div class="absolute -bottom-24 -left-20 h-72 w-72 rounded-full border border-[#2962FF]/35 bg-[#0D1B3D]/80 shadow-[0_0_80px_rgba(41,98,255,0.24)]"></div>
                        <div class="relative flex flex-col items-center text-center">
                            <a href="/" class="group inline-flex flex-col items-center gap-4" aria-label="Fanta Oracle home">
                                <x-fanta-oracle-logo variant="full-dark" size="card" />
                                <span class="-mt-2 block max-w-xs text-sm leading-6 text-slate-300">Advanced Predictive Engine for Fantasy Football Analytics</span>
                            </a>
                        </div>
                    </div>

                    <div class="px-6 py-7 sm:px-8">
                        <div class="mb-6 text-center">
                            <h1 class="text-xl font-semibold tracking-tight text-white">Accedi al tuo account</h1>
                            <p class="mt-2 text-sm text-slate-400">Entra nella tua area personale.</p>
                        </div>

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
                                        type="password"
                                        required
                                        autocomplete="current-password"
                                        placeholder="Inserisci la password"
                                        aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                        class="block h-12 w-full rounded-md border border-white/15 bg-[#07101F] px-4 pe-12 text-base text-white shadow-sm outline-none transition placeholder:text-slate-500 focus:border-[#7B2CFF] focus:ring-2 focus:ring-[#7B2CFF]/45"
                                    />
                                    <button type="button" id="togglePassword" class="absolute inset-y-0 right-3 flex items-center text-slate-400 transition hover:text-white" aria-label="Mostra password">
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

                            <button id="loginSubmit" type="submit" class="inline-flex h-12 w-full items-center justify-center rounded-md bg-gradient-to-r from-[#8B2CFF] to-[#2962FF] px-5 text-sm font-semibold text-white shadow-lg shadow-[#2962FF]/25 transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-[#7B2CFF]/60 focus:ring-offset-2 focus:ring-offset-[#071225] disabled:cursor-not-allowed disabled:opacity-70">
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
                </div>
            </section>

            <section class="hidden min-h-screen p-5 xl:block">
                <div class="flex h-full flex-col overflow-hidden rounded-lg border border-white/15 bg-[#071225]/70 shadow-2xl shadow-black/30 backdrop-blur-xl">
                    <header class="flex h-16 items-center justify-between border-b border-white/10 px-8">
                        <a href="/" class="inline-flex items-center gap-3">
                            <x-fanta-oracle-logo variant="full-dark" size="nav" />
                        </a>

                        <nav class="flex items-center gap-9 text-sm text-slate-300">
                            <span class="text-white">Dashboard</span>
                            <span>Proiezioni</span>
                            <span>Giocatori</span>
                            <span>Squadre</span>
                            <span>Strumenti</span>
                        </nav>
                    </header>

                    <div class="relative flex-1 overflow-hidden px-12 py-10">
                        <div class="absolute inset-0 bg-[radial-gradient(circle_at_78%_20%,rgba(123,44,255,0.32),transparent_26%),linear-gradient(125deg,rgba(5,11,24,0.2),rgba(13,27,61,0.74)),url('data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22180%22 height=%22180%22 viewBox=%220 0 180 180%22%3E%3Cg fill=%22none%22 stroke=%22%232962FF%22 stroke-opacity=%220.12%22%3E%3Cpath d=%22M0 90h180M90 0v180%22/%3E%3Ccircle cx=%2290%22 cy=%2290%22 r=%2268%22/%3E%3C/g%3E%3C/svg%3E')]"></div>

                        <div class="relative grid grid-cols-[1fr_420px] items-center gap-8">
                            <div>
                                <h2 class="max-w-2xl text-5xl font-semibold leading-tight tracking-tight">
                                    Intelligenza. Dati. Futuro.<br>
                                    Fanta <span class="text-[#8B2CFF]">Oracle.</span>
                                </h2>
                                <p class="mt-6 max-w-xl text-xl leading-8 text-slate-300">
                                    Il motore predittivo avanzato per il Fantacalcio. Analisi profonde, proiezioni affidabili, vantaggio competitivo.
                                </p>
                                <div class="mt-8 flex gap-4">
                                    <span class="inline-flex h-12 items-center justify-center rounded-md bg-gradient-to-r from-[#8B2CFF] to-[#2962FF] px-8 text-sm font-semibold text-white">Esplora Proiezioni</span>
                                    <span class="inline-flex h-12 items-center justify-center gap-3 rounded-md border border-white/15 bg-white/5 px-7 text-sm font-semibold text-white">
                                        <span class="flex h-5 w-5 items-center justify-center rounded-full border border-white/60 text-[10px]">▶</span>
                                        Guarda il Video
                                    </span>
                                </div>
                            </div>

                            <div class="relative flex h-[320px] items-center justify-center">
                                <div class="absolute h-72 w-72 rounded-full border border-[#7B2CFF]/50 bg-[#0D1B3D]/60 shadow-[0_0_120px_rgba(123,44,255,0.55)]"></div>
                                <x-fanta-oracle-logo variant="symbol" size="icon-lg" />
                            </div>
                        </div>

                        <div class="relative mt-9 grid grid-cols-4 gap-5">
                            @foreach ([
                                ['label' => 'Giocatori analizzati', 'value' => '6.452', 'delta' => '+128 vs ieri'],
                                ['label' => 'Proiezioni calcolate', 'value' => '18.732', 'delta' => '+256 vs ieri'],
                                ['label' => 'Accuratezza MV', 'value' => '84,7%', 'delta' => '+2,3% vs scorsa sett.'],
                                ['label' => 'Aggiornamento dati', 'value' => '15 min fa', 'delta' => 'Prossimo: 14 min'],
                            ] as $stat)
                                <div class="rounded-lg border border-white/12 bg-[#08152B]/82 p-5 shadow-lg shadow-black/15">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $stat['label'] }}</p>
                                    <p class="mt-3 text-3xl font-semibold text-white">{{ $stat['value'] }}</p>
                                    <p class="mt-2 text-sm text-emerald-400">{{ $stat['delta'] }}</p>
                                </div>
                            @endforeach
                        </div>

                        <div class="relative mt-5 grid grid-cols-[1.2fr_0.8fr] gap-5">
                            <div class="rounded-lg border border-white/12 bg-[#08152B]/82 p-5">
                                <div class="mb-5 flex items-center justify-between">
                                    <h3 class="text-xl font-semibold">Proiezioni Top Giocatori</h3>
                                    <span class="text-sm text-[#B470FF]">Vedi tutte</span>
                                </div>
                                <div class="space-y-4">
                                    @foreach ([
                                        ['Lautaro Martinez', 'Inter', '9.12', '9.48', '+0.36'],
                                        ['Khvicha Kvaratskhelia', 'Napoli', '8.45', '8.83', '+0.38'],
                                        ['Rafael Leao', 'Milan', '8.01', '8.32', '+0.31'],
                                        ['Paulo Dybala', 'Roma', '7.58', '7.91', '+0.33'],
                                    ] as $player)
                                        <div class="grid grid-cols-[1.6fr_0.8fr_0.6fr_0.6fr_0.6fr] items-center border-b border-white/8 pb-3 text-sm last:border-b-0 last:pb-0">
                                            <span class="font-medium text-white">{{ $player[0] }}</span>
                                            <span class="text-slate-300">{{ $player[1] }}</span>
                                            <span>{{ $player[2] }}</span>
                                            <span>{{ $player[3] }}</span>
                                            <span class="text-emerald-400">{{ $player[4] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="rounded-lg border border-white/12 bg-[#08152B]/82 p-5">
                                <h3 class="text-xl font-semibold">Distribuzione Varianza MV</h3>
                                <div class="mt-8 flex items-center justify-center">
                                    <div class="flex h-44 w-44 items-center justify-center rounded-full bg-[conic-gradient(#2962FF_0_28%,#8B2CFF_28%_64%,#4F46E5_64%_89%,#F97316_89%_100%)]">
                                        <div class="flex h-28 w-28 flex-col items-center justify-center rounded-full bg-[#08152B]">
                                            <span class="text-3xl font-semibold">84,7%</span>
                                            <span class="text-xs text-slate-400">entro +/-0.15</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('form');
            const submit = document.getElementById('loginSubmit');
            if (form && submit) {
                form.addEventListener('submit', function () {
                    submit.disabled = true;
                    submit.textContent = 'Accesso in corso...';
                });
            }

            const toggle = document.getElementById('togglePassword');
            const password = document.getElementById('password');
            if (toggle && password) {
                toggle.addEventListener('click', function () {
                    password.type = password.type === 'password' ? 'text' : 'password';
                    toggle.setAttribute('aria-label', password.type === 'password' ? 'Mostra password' : 'Nascondi password');
                });
            }
        });
    </script>
</x-guest-layout>
