<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'FantaOracle') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @fluxAppearance
    </head>
    <body class="min-h-screen bg-oracle-bg font-sans text-slate-100 antialiased selection:bg-oracle-purple/40 selection:text-white">
        <x-banner />

        <flux:sidebar sticky collapsible="mobile" class="fo-app-sidebar border-r backdrop-blur-xl">
            <flux:sidebar.header class="border-b border-white/10 px-4 py-4">
                <a href="{{ auth()->user()?->hasRole('admin') ? route('admin.dashboard') : route('dashboard') }}" class="flex items-center gap-3">
                    <x-fanta-oracle-logo variant="symbol" size="nav" />
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold tracking-wide text-white">Fanta Oracle</div>
                        <div class="truncate text-xs text-slate-400">Control Center</div>
                    </div>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav class="fo-scrollbar px-2 py-4">
                <flux:sidebar.item
                    class="fo-sidebar-item"
                    icon="home"
                    href="{{ auth()->user()?->hasRole('admin') ? route('admin.dashboard') : route('dashboard') }}"
                    :current="request()->routeIs('dashboard', 'admin.dashboard')"
                >
                    Dashboard
                </flux:sidebar.item>

                @hasanyrole('admin|super_admin')
                    <flux:sidebar.group class="fo-sidebar-group grid" heading="Administration" expandable :expanded="false">
                        <div class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">Accessi</div>
                        <flux:sidebar.item class="fo-sidebar-item" icon="users" href="#">Utenti</flux:sidebar.item>
                        <flux:sidebar.item class="fo-sidebar-item" icon="shield-check" href="#">Ruoli e permessi</flux:sidebar.item>

                        <div class="mt-2 px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">Data Platform</div>
                        <flux:sidebar.item class="fo-sidebar-item" icon="server-stack" href="{{ route('admin.providers.index') }}" :current="request()->routeIs('admin.providers.*')">Provider Management</flux:sidebar.item>
                        <flux:sidebar.item class="fo-sidebar-item" icon="calendar-days" href="{{ route('admin.seasons.index') }}" :current="request()->routeIs('admin.seasons.*')">Gestione Stagioni</flux:sidebar.item>

                        <div class="mt-2 px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">Sistema</div>
                        <flux:sidebar.item class="fo-sidebar-item" icon="cog-6-tooth" href="#">Configurazione</flux:sidebar.item>
                        <flux:sidebar.item class="fo-sidebar-item" icon="circle-stack" href="#">Database</flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group class="fo-sidebar-group grid" heading="Diagnostics" expandable :expanded="false">
                        <flux:sidebar.item class="fo-sidebar-item" icon="heart" href="#">Stato sistema</flux:sidebar.item>
                        <flux:sidebar.item class="fo-sidebar-item" icon="queue-list" href="#">Code e job</flux:sidebar.item>
                        <flux:sidebar.item class="fo-sidebar-item" icon="document-text" href="#">Log</flux:sidebar.item>
                        <flux:sidebar.item class="fo-sidebar-item" icon="signal" href="#">API</flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group class="fo-sidebar-group grid" heading="Operations" expandable :expanded="false">
                        <flux:sidebar.item class="fo-sidebar-item" icon="arrow-down-tray" href="#">Importazioni</flux:sidebar.item>
                        <flux:sidebar.item class="fo-sidebar-item" icon="chart-bar-square" href="#">Proiezioni</flux:sidebar.item>
                        <flux:sidebar.item class="fo-sidebar-item" icon="sparkles" href="#">Oracle Engine</flux:sidebar.item>
                        <flux:sidebar.item class="fo-sidebar-item" icon="cpu-chip" href="#">AI Engine</flux:sidebar.item>
                    </flux:sidebar.group>
                @endhasanyrole
            </flux:sidebar.nav>

            <flux:sidebar.spacer />

            <div class="border-t border-white/10 p-3">
                <flux:dropdown position="top" align="start" class="w-full">
                    <button type="button" class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition hover:bg-white/5">
                        <img class="size-9 rounded-full object-cover ring-1 ring-white/15" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}">
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-white">{{ Auth::user()->name }}</div>
                            <div class="truncate text-xs text-slate-400">{{ Auth::user()->getRoleNames()->join(' · ') ?: 'utente' }}</div>
                        </div>
                        <flux:icon.chevron-up-down class="size-4 text-slate-500" />
                    </button>

                    <flux:menu class="min-w-56">
                        <flux:menu.item icon="user-circle" href="{{ route('profile.show') }}">Profilo</flux:menu.item>
                        <flux:menu.separator />
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-red-300 transition hover:bg-red-500/10 hover:text-red-200">
                                <flux:icon.arrow-right-start-on-rectangle class="size-4" />
                                Esci
                            </button>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </flux:sidebar>

        <flux:header class="fo-mobile-header border-b lg:hidden">
            <flux:sidebar.toggle icon="bars-2" inset="left" />
            <div class="ml-2 text-sm font-semibold text-white">Fanta Oracle</div>
            <flux:spacer />
            <img class="size-8 rounded-full object-cover ring-1 ring-white/15" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}">
        </flux:header>

        <flux:main class="min-h-screen bg-transparent px-4 py-5 sm:px-6 lg:px-8 lg:py-7">
            @if (isset($header))
                <header class="mb-7">
                    {{ $header }}
                </header>
            @endif

            {{ $slot }}
        </flux:main>

        @stack('modals')
        @livewireScripts
        @fluxScripts
    </body>
</html>