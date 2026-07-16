import './bootstrap';

const initializeProviderOnboarding = async () => {
    const form = document.querySelector('#nuovo-provider form');

    if (!form) {
        return;
    }

    const codeInput = form.querySelector('input[name="code"]');
    const nameInput = form.querySelector('input[name="name"]');
    const credentialKeyInput = form.querySelector('input[name="credential_key"]');
    const submitButton = form.querySelector('button[type="submit"], button:not([type])');

    if (!codeInput) {
        return;
    }

    const codeLabel = codeInput.closest('label');
    const nameLabel = nameInput?.closest('label');
    const credentialKeyLabel = credentialKeyInput?.closest('label');

    try {
        const response = await fetch('/admin/providers/available-adapters', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error(`Unable to load adapters (${response.status})`);
        }

        const payload = await response.json();
        const adapters = Array.isArray(payload.data) ? payload.data : [];

        const select = document.createElement('select');
        select.name = 'code';
        select.required = true;
        select.className = codeInput.className;
        select.setAttribute('aria-label', 'Provider disponibile');

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = adapters.length > 0
            ? 'Seleziona un adapter installato'
            : 'Nessun nuovo adapter disponibile';
        select.appendChild(placeholder);

        adapters.forEach((adapter) => {
            const option = document.createElement('option');
            option.value = adapter.code;
            option.textContent = `${adapter.name} (${adapter.code})`;
            select.appendChild(option);
        });

        codeInput.replaceWith(select);
        nameLabel?.remove();
        credentialKeyLabel?.remove();

        const title = codeLabel?.querySelector('span');
        if (title) {
            title.textContent = 'Provider disponibile';
        }

        const helper = codeLabel?.querySelector('span:last-child');
        if (helper && helper !== title) {
            helper.textContent = 'La lista contiene solo adapter installati e non ancora registrati.';
        }

        const details = document.createElement('div');
        details.className = 'rounded-xl bg-blue-50 p-4 text-sm text-blue-950 ring-1 ring-blue-200 md:col-span-3';
        details.innerHTML = '<strong>Seleziona un provider</strong><p class="mt-1 text-xs">Nome, codice, credenziale richiesta e capacità arrivano automaticamente dall’adapter.</p>';
        codeLabel?.after(details);

        const renderDetails = () => {
            const adapter = adapters.find((item) => item.code === select.value);

            if (!adapter) {
                details.innerHTML = '<strong>Seleziona un provider</strong><p class="mt-1 text-xs">Nome, codice, credenziale richiesta e capacità arrivano automaticamente dall’adapter.</p>';
                return;
            }

            const capabilities = adapter.capabilities.length > 0
                ? adapter.capabilities.join(', ')
                : 'nessuna dichiarata';

            details.innerHTML = `
                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    <div><span class="block text-xs font-semibold uppercase tracking-wide text-blue-700">Nome</span>${adapter.name}</div>
                    <div><span class="block text-xs font-semibold uppercase tracking-wide text-blue-700">Codice</span><code>${adapter.code}</code></div>
                    <div><span class="block text-xs font-semibold uppercase tracking-wide text-blue-700">Credenziale</span><code>${adapter.credential_key ?? 'non richiesta'}</code></div>
                    <div><span class="block text-xs font-semibold uppercase tracking-wide text-blue-700">Capacità</span>${capabilities}</div>
                </div>`;
        };

        select.addEventListener('change', renderDetails);
        renderDetails();

        if (adapters.length === 0) {
            select.disabled = true;
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.classList.add('cursor-not-allowed', 'opacity-50');
                submitButton.textContent = 'Nessun provider da registrare';
            }
        }
    } catch (error) {
        console.error(error);

        if (codeLabel) {
            const warning = document.createElement('p');
            warning.className = 'text-xs font-medium text-red-700';
            warning.textContent = 'Impossibile caricare gli adapter disponibili. Ricarica la pagina.';
            codeLabel.appendChild(warning);
        }

        if (submitButton) {
            submitButton.disabled = true;
        }
    }
};

document.addEventListener('DOMContentLoaded', initializeProviderOnboarding);
