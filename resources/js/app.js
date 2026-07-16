import './bootstrap';

const initializeProviderOnboarding = () => {
    const container = document.querySelector('#nuovo-provider');
    const form = container?.querySelector('form');
    const openButton = document.querySelector('a[href="#nuovo-provider"]');

    if (!container || !form) {
        return;
    }

    openButton?.addEventListener('click', () => {
        container.querySelector('details')?.setAttribute('open', 'open');
    });

    const credentialKeyInput = form.querySelector('input[name="credential_key"]');
    const credentialValueInput = form.querySelector('input[name="credential_value"]');
    const codeInput = form.querySelector('input[name="code"]');
    const credentialKeyLabel = credentialKeyInput?.closest('label');
    const credentialValueLabel = credentialValueInput?.closest('label');

    if (!credentialKeyInput || !credentialValueInput || !credentialKeyLabel || !credentialValueLabel) {
        return;
    }

    const credentialControl = document.createElement('label');
    credentialControl.className = 'space-y-1';
    credentialControl.innerHTML = `
        <span class="text-xs font-medium text-slate-700">Richiede credenziale?</span>
        <select name="credential_required" class="w-full rounded-lg bg-white px-3 py-2 text-slate-900 ring-1 ring-slate-300" required>
            <option value="1">Sì</option>
            <option value="0">No</option>
        </select>
        <span class="block text-[11px] text-slate-500">Scegli No per API pubbliche senza token o API key.</span>
    `;
    credentialKeyLabel.before(credentialControl);

    const capabilities = document.createElement('fieldset');
    capabilities.className = 'space-y-2 md:col-span-4 rounded-xl bg-white p-4 ring-1 ring-slate-300';
    capabilities.innerHTML = `
        <legend class="text-xs font-semibold text-slate-700">Capacità dichiarate</legend>
        <p class="text-[11px] text-slate-500">Indica quali dati il provider espone. Non attiva automaticamente l'integrazione.</p>
        <div class="mt-3 flex flex-wrap gap-4 text-sm text-slate-800">
            ${['competitions', 'seasons', 'teams', 'fixtures', 'standings', 'players', 'statistics']
                .map((capability) => `<label class="inline-flex items-center gap-2"><input type="checkbox" name="capabilities[]" value="${capability}" class="rounded border-slate-300">${capability}</label>`)
                .join('')}
        </div>
    `;
    credentialValueLabel.after(capabilities);

    const credentialRequired = credentialControl.querySelector('select[name="credential_required"]');
    const syncCredentialFields = () => {
        const required = credentialRequired.value === '1';
        credentialKeyLabel.classList.toggle('hidden', !required);
        credentialValueLabel.classList.toggle('hidden', !required);
        credentialKeyInput.required = required;
        credentialValueInput.required = required;

        if (!required) {
            credentialKeyInput.value = '';
            credentialValueInput.value = '';
        }
    };

    credentialRequired.addEventListener('change', syncCredentialFields);
    syncCredentialFields();

    codeInput?.addEventListener('blur', () => {
        codeInput.value = codeInput.value
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '');
    });
};

document.addEventListener('DOMContentLoaded', initializeProviderOnboarding);
