<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Admin Dashboard - FantaOracle') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <h3 class="text-2xl font-bold mb-4">Benvenuto nell'Area Amministrativa</h3>
                <p class="text-gray-600">Da qui inizieremo il vero porting delle funzionalità FantaOracle.</p>
                <div class="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Placeholder per future sezioni admin -->
                    <div class="p-4 border rounded shadow-sm bg-gray-50">
                        <h4 class="font-bold">Utenti</h4>
                        <p class="text-sm">Gestione iscritti</p>
                    </div>
                    <div class="p-4 border rounded shadow-sm bg-gray-50">
                        <h4 class="font-bold">Impostazioni</h4>
                        <p class="text-sm">Configurazioni globali</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
