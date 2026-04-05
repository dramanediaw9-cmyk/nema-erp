@php
    $tableId = $tableId ?? '';
    $partnerSelectId = $partnerSelectId ?? '';
    $pricingHintId = $pricingHintId ?? '';
    $lineAmountClass = $lineAmountClass ?? 'line-price';
    $priceDataKey = $priceDataKey ?? 'price';
    $partnerKind = $partnerKind ?? 'client';
    $defaultPricingText = $defaultPricingText ?? 'Aucune liste de prix specifique: prix catalogue par defaut.';
@endphp

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const table = document.getElementById(@json($tableId));
        if (!table) {
            return;
        }

        const rows = Array.from(table.querySelectorAll('tbody tr'));
        const partnerSelect = document.getElementById(@json($partnerSelectId));
        const pricingHint = document.getElementById(@json($pricingHintId));
        const amountClass = @json($lineAmountClass);
        const priceDataKey = @json($priceDataKey);
        const partnerKind = @json($partnerKind);
        const priceRules = @json($priceRules ?? []);
        const defaultPricingText = @json($defaultPricingText);

        const readPartnerPricing = () => {
            const option = partnerSelect?.options?.[partnerSelect.selectedIndex];

            return {
                id: option?.dataset?.priceListId || '',
                name: option?.dataset?.priceListName || '',
            };
        };

        const resolveAmount = (productId, qty, fallbackValue) => {
            const pricing = readPartnerPricing();
            const rules = priceRules?.[pricing.id]?.[productId] || [];
            const matched = Array.isArray(rules)
                ? rules.find((rule) => Number(rule.min_qty || 0) <= qty)
                : null;

            return matched ? Number(matched.price || fallbackValue || 0) : Number(fallbackValue || 0);
        };

        const refreshPricingHint = () => {
            if (!pricingHint) {
                return;
            }

            const pricing = readPartnerPricing();
            pricingHint.textContent = pricing.name
                ? `Liste de prix ${partnerKind} active : ${pricing.name}. Les montants se proposent automatiquement tant que tu ne saisis pas une valeur manuelle.`
                : defaultPricingText;
        };

        const markManual = (amountInput) => {
            if (!amountInput || amountInput.dataset.autoFilling === '1') {
                return;
            }

            amountInput.dataset.manual = amountInput.value ? '1' : '0';
        };

        const primeRow = (row) => {
            const productSelect = row.querySelector('.line-product');
            const amountInput = row.querySelector(`.${amountClass}`);
            if (!productSelect || !amountInput) {
                return;
            }

            const selectedOption = productSelect.options[productSelect.selectedIndex];
            const fallbackValue = Number(selectedOption?.dataset?.[priceDataKey] || 0);
            const currentValue = amountInput.value === '' ? NaN : Number(amountInput.value);

            amountInput.dataset.manual = !Number.isNaN(currentValue) && Math.abs(currentValue - fallbackValue) > 0.0001 ? '1' : '0';
        };

        const applyAutoAmount = (row, forceReset = false) => {
            const productSelect = row.querySelector('.line-product');
            const qtyInput = row.querySelector('.line-qty');
            const amountInput = row.querySelector(`.${amountClass}`);
            if (!productSelect || !qtyInput || !amountInput) {
                return;
            }

            const selectedOption = productSelect.options[productSelect.selectedIndex];
            if (!selectedOption || !selectedOption.value) {
                if (forceReset && amountInput.dataset.manual !== '1') {
                    amountInput.value = '';
                    amountInput.dataset.autoValue = '';
                }

                return;
            }

            if (forceReset) {
                amountInput.dataset.manual = '0';
            }

            const qty = Math.max(Number(qtyInput.value || 0), 1);
            const fallbackValue = Number(selectedOption.dataset?.[priceDataKey] || 0);
            const resolvedValue = resolveAmount(String(selectedOption.value), qty, fallbackValue);
            const previousAuto = amountInput.dataset.autoValue === '' || typeof amountInput.dataset.autoValue === 'undefined'
                ? NaN
                : Number(amountInput.dataset.autoValue);
            const currentValue = amountInput.value === '' ? NaN : Number(amountInput.value);
            const isSameAsPreviousAuto = !Number.isNaN(previousAuto) && !Number.isNaN(currentValue) && Math.abs(currentValue - previousAuto) < 0.0001;
            const shouldAutoFill = amountInput.value === '' || amountInput.dataset.manual !== '1' || isSameAsPreviousAuto;

            amountInput.dataset.autoValue = String(resolvedValue);

            if (!shouldAutoFill) {
                return;
            }

            amountInput.dataset.autoFilling = '1';
            amountInput.value = resolvedValue.toFixed(2);
            amountInput.dataset.manual = '0';
            amountInput.dispatchEvent(new Event('input', { bubbles: true }));
            amountInput.dataset.autoFilling = '0';
        };

        rows.forEach((row) => {
            const productSelect = row.querySelector('.line-product');
            const qtyInput = row.querySelector('.line-qty');
            const amountInput = row.querySelector(`.${amountClass}`);

            primeRow(row);
            applyAutoAmount(row);

            productSelect?.addEventListener('change', function () {
                applyAutoAmount(row, true);
            });

            qtyInput?.addEventListener('input', function () {
                applyAutoAmount(row);
            });

            qtyInput?.addEventListener('change', function () {
                applyAutoAmount(row);
            });

            amountInput?.addEventListener('input', function () {
                markManual(amountInput);
            });

            amountInput?.addEventListener('change', function () {
                markManual(amountInput);
            });
        });

        partnerSelect?.addEventListener('change', function () {
            refreshPricingHint();
            rows.forEach((row) => applyAutoAmount(row));
        });

        refreshPricingHint();
    });
</script>
