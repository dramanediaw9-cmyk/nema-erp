(() => {
    'use strict';

    const endpoint = document.querySelector('meta[name="product-options-url"]')?.content;
    if (!endpoint) return;

    const controllers = new WeakMap();

    const setOptionData = (option, item, mode) => {
        option.dataset.name = item.name || '';
        option.dataset.price = item.price ?? '';
        option.dataset.cost = item.cost ?? '';
        option.dataset.saleDescription = item.sale_description || '';
        option.dataset.purchaseDescription = item.purchase_description || '';
        option.dataset.unitSummary = mode === 'purchasable'
            ? (item.purchase_unit_summary || item.sale_unit_summary || '')
            : (item.sale_unit_summary || item.purchase_unit_summary || '');
    };

    const enhance = (select) => {
        if (!(select instanceof HTMLSelectElement) || select.dataset.productPickerReady === 'true') return;
        select.dataset.productPickerReady = 'true';

        const wrapper = document.createElement('div');
        wrapper.className = 'nema-product-picker';
        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'nema-product-picker__search';
        search.placeholder = select.dataset.productPlaceholder || 'Rechercher par nom, SKU ou code-barres';
        search.autocomplete = 'off';
        search.setAttribute('aria-label', search.placeholder);
        const status = document.createElement('div');
        status.className = 'nema-product-picker__status';
        status.setAttribute('aria-live', 'polite');

        select.parentNode.insertBefore(wrapper, select);
        wrapper.append(search, select, status);

        if (select.disabled) {
            search.disabled = true;
            status.textContent = 'Produit impose par le document source.';
            return;
        }

        let timer = null;
        const load = async () => {
            const query = search.value.trim();
            if (query.length < 2) {
                status.textContent = query ? 'Saisissez au moins 2 caracteres.' : 'Saisissez 2 caracteres pour parcourir tout le catalogue.';
                return;
            }

            controllers.get(select)?.abort();
            const controller = new AbortController();
            controllers.set(select, controller);
            status.textContent = 'Recherche en cours…';

            try {
                const url = new URL(endpoint, window.location.origin);
                url.searchParams.set('q', query);
                url.searchParams.set('mode', select.dataset.productMode || 'active');
                url.searchParams.set('limit', '40');
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: controller.signal,
                });
                if (response.redirected && new URL(response.url).pathname === '/login') {
                    window.location.assign(response.url);
                    return;
                }
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const payload = await response.json();
                const selectedValues = new Set(Array.from(select.selectedOptions).map((option) => option.value).filter(Boolean));
                const preserved = Array.from(select.options).filter((option) => option.value === '' || selectedValues.has(option.value));
                select.replaceChildren(...preserved);

                for (const item of payload.results || []) {
                    const value = String(item.id);
                    let option = Array.from(select.options).find((candidate) => candidate.value === value);
                    if (!option) {
                        option = document.createElement('option');
                        option.value = value;
                        select.appendChild(option);
                    }
                    option.textContent = item.text;
                    option.selected = selectedValues.has(value);
                    setOptionData(option, item, select.dataset.productMode || 'active');
                }

                const count = (payload.results || []).length;
                status.textContent = count === 0
                    ? 'Aucun produit trouve.'
                    : `${count} resultat(s)${payload.has_more ? ' — precisez la recherche pour affiner' : ''}.`;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    status.textContent = 'Recherche indisponible. Reessayez.';
                }
            }
        };

        search.addEventListener('input', () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(load, 250);
        });
        search.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' && select.options.length > 1) {
                event.preventDefault();
                select.focus();
            }
        });
        status.textContent = 'Saisissez 2 caracteres pour parcourir tout le catalogue.';
    };

    const scan = (root = document) => {
        if (root.matches?.('select[data-product-picker]')) enhance(root);
        root.querySelectorAll?.('select[data-product-picker]').forEach(enhance);
    };

    scan();
    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
            if (node instanceof Element) scan(node);
        }));
    }).observe(document.documentElement, { childList: true, subtree: true });
})();
