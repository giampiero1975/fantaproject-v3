<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-semibold text-white">Provider Management</h1>
            <p class="mt-1 text-sm text-slate-400">Configurazione runtime, priorità, credenziali cifrate e mapping delle fonti dati.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-xl border border-emerald-400/20 bg-emerald-400/5 p-4 text-sm text-emerald-100">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-400/20 bg-red-400/5 p-4 text-sm text-red-100">
                <ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">Guida operativa</h2>
                    <p class="mt-1 text-sm text-slate-400">Il DB è la fonte primaria della configurazione provider. Disattivare non cancella mapping, credenziali o storico.</p>
                </div>
                <span class="rounded-full border border-violet-400/20 bg-violet-400/10 px-3 py-1 text-xs text-violet-200">Ambiente: {{ $environment }}</span>
            </div>
            <div class="mt-4 grid gap-4 md:grid-cols-3 text-sm text-slate-300">
                <div><strong class="text-white">Attivazione</strong><p class="mt-1 text-slate-400">Controlla se il provider entra nel registry runtime.</p></div>
                <div><strong class="text-white">Priorità e ruolo</strong><p class="mt-1 text-slate-400">Definiscono ordine, primary/fallback/audit/statistics.</p></div>
                <div><strong class="text-white">Credenziali</strong><p class="mt-1 text-slate-400">Sono cifrate con APP_KEY e non vengono mai mostrate in chiaro.</p></div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            @foreach ($providers as $provider)
                <article class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-3">
                                <h2 class="text-lg font-semibold text-white">{{ $provider->name }}</h2>
                                <span class="rounded-full px-2.5 py-1 text-xs {{ $provider->is_enabled ? 'bg-emerald-400/10 text-emerald-200' : 'bg-slate-700 text-slate-300' }}">
                                    {{ $provider->is_enabled ? 'Attivo' : 'Disattivato' }}
                                </span>
                            </div>
                            <p class="mt-1 font-mono text-xs text-slate-500">{{ $provider->code }}</p>
                        </div>
                        <form method="POST" action="{{ route('admin.providers.toggle', $provider->id) }}">
                            @csrf @method('PATCH')
                            <button class="rounded-xl border border-white/10 px-3 py-2 text-xs text-slate-200 hover:bg-white/5">{{ $provider->is_enabled ? 'Disattiva' : 'Attiva' }}</button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('admin.providers.update', $provider->id) }}" class="mt-5 grid gap-4 md:grid-cols-2">
                        @csrf @method('PUT')
                        <label class="space-y-1"><span class="text-xs text-slate-400">Nome</span><input name="name" value="{{ $provider->name }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Piano</span><input name="plan" value="{{ $provider->plan }}" placeholder="free, paid, enterprise..." class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <label class="space-y-1 md:col-span-2"><span class="text-xs text-slate-400">Base URL</span><input name="base_url" value="{{ $provider->base_url }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Ruolo</span><select name="role" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white">@foreach(['primary','fallback','audit','statistics'] as $role)<option value="{{ $role }}" @selected($provider->role === $role)>{{ $role }}</option>@endforeach</select></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Priorità</span><input type="number" name="priority" value="{{ $provider->priority ?? 100 }}" min="1" max="9999" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Timeout</span><input type="number" name="timeout" value="{{ $provider->timeout ?? 30 }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Connect timeout</span><input type="number" name="connect_timeout" value="{{ $provider->connect_timeout ?? 10 }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Retry</span><input type="number" name="retry_times" value="{{ $provider->retry_times ?? 3 }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <label class="space-y-1"><span class="text-xs text-slate-400">Retry sleep ms</span><input type="number" name="retry_sleep_ms" value="{{ $provider->retry_sleep_ms ?? 500 }}" class="w-full rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white"></label>
                        <div class="md:col-span-2"><button class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Salva configurazione</button></div>
                    </form>

                    <div class="mt-6 border-t border-white/10 pt-5">
                        <h3 class="text-sm font-semibold text-white">Credenziali cifrate</h3>
                        <div class="mt-3 space-y-2 text-sm">
                            @forelse ($provider->credentials as $credential)
                                <div class="flex items-center justify-between rounded-lg bg-black/20 px-3 py-2"><span class="font-mono text-slate-300">{{ $credential->credential_key }}</span><span class="text-xs text-slate-500">•••••••• · {{ $credential->rotated_at ?? 'mai ruotata' }}</span></div>
                            @empty
                                <p class="text-slate-500">Nessuna credenziale per l’ambiente corrente.</p>
                            @endforelse
                        </div>
                        <form method="POST" action="{{ route('admin.providers.credentials.rotate', $provider->id) }}" class="mt-3 grid gap-3 md:grid-cols-3">
                            @csrf
                            <input name="credential_key" placeholder="token / api_key" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required>
                            <input type="password" name="credential_value" placeholder="Nuovo valore" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required>
                            <button class="rounded-xl border border-amber-400/30 bg-amber-400/10 px-4 py-2 text-sm text-amber-100">Ruota credenziale</button>
                        </form>
                    </div>

                    <div class="mt-6 border-t border-white/10 pt-5">
                        <h3 class="text-sm font-semibold text-white">Mapping competizioni</h3>
                        <div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm"><thead class="text-slate-500"><tr><th class="pb-2">Lega interna</th><th class="pb-2">External ID</th><th class="pb-2">Nome esterno</th></tr></thead><tbody class="divide-y divide-white/5">@forelse($provider->mappings as $mapping)<tr><td class="py-2 text-white">{{ $mapping->league_name }}</td><td class="py-2 font-mono text-slate-300">{{ $mapping->external_id }}</td><td class="py-2 text-slate-400">{{ $mapping->external_name }}</td></tr>@empty<tr><td colspan="3" class="py-3 text-slate-500">Nessun mapping registrato.</td></tr>@endforelse</tbody></table></div>
                    </div>
                </article>
            @endforeach
        </section>

        <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <h2 class="text-lg font-semibold text-white">Aggiungi provider</h2>
            <p class="mt-1 text-sm text-slate-400">Crea catalogo e configurazione DB. Per usarlo realmente serve anche un adapter applicativo compatibile.</p>
            <form method="POST" action="{{ route('admin.providers.store') }}" class="mt-5 grid gap-4 md:grid-cols-4">
                @csrf
                <input name="code" placeholder="codice_provider" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required>
                <input name="name" placeholder="Nome provider" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required>
                <input name="base_url" placeholder="https://api.example.com" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required>
                <select name="role" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white">@foreach(['primary','fallback','audit','statistics'] as $role)<option value="{{ $role }}">{{ $role }}</option>@endforeach</select>
                <input type="number" name="priority" value="100" min="1" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white" required>
                <input name="plan" placeholder="Piano" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white">
                <input name="credential_key" placeholder="credential key" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white">
                <input type="password" name="credential_value" placeholder="credential value" class="rounded-xl border border-white/10 bg-black/20 px-3 py-2 text-white">
                <div class="md:col-span-4"><button class="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white">Aggiungi provider</button></div>
            </form>
        </section>
    </div>
</x-app-layout>
