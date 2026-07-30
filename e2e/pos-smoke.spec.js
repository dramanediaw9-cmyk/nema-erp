import { test, expect } from '@playwright/test';

test('cashier can open POS session, validate a sale, print the thermal ticket, and return to the cashier screen', async ({ page }) => {
    await page.goto('/login');

    await page.getByLabel('Adresse e-mail').fill('caissier@nema-erp.test');
    await page.getByLabel('Mot de passe').fill('password');
    await page.getByRole('button', { name: 'Se connecter' }).click();

    await page.goto('/point-de-vente');

    const openCashButton = page.getByRole('button', { name: 'Ouvrir la caisse' });

    if (await openCashButton.isVisible()) {
        await page.locator('#cash_account_id').selectOption({ index: 1 });
        await page.locator('#warehouse_id').selectOption({ index: 1 });
        await page.locator('#opening_amount').fill('0');
        await openCashButton.click();
        await expect(page).toHaveURL(/\/point-de-vente\/sessions\/\d+/);
    }

    const saleLink = page.locator('a[href*="/point-de-vente/vente"]');
    await expect(saleLink.first()).toBeVisible();
    await saleLink.first().click();

    await expect(page).toHaveURL(/\/point-de-vente\/vente/);
    const availableProduct = page.locator('.pos-product[data-product-id]:not(:disabled)').first();
    await expect(availableProduct).toBeVisible();
    await expect(availableProduct).toBeEnabled();

    const productName = (await availableProduct.locator('strong').innerText()).trim();
    const productSearch = page.locator('#pos-search');
    await productSearch.fill(productName);
    await expect(page.locator('.pos-product[data-product-id]:not(:disabled)').first()).toBeVisible();
    await page.locator('.pos-product[data-product-id]:not(:disabled)').first().click();
    await expect(productSearch).toHaveValue(productName);
    await expect(page.locator('[data-line-card]').first()).toBeVisible();
    await page.locator('#cash_received_amount').fill('999999');

    const submitButton = page.getByRole('button', { name: 'Valider et encaisser' });
    await expect(submitButton).toHaveAttribute('aria-disabled', 'false');
    const printThermalInput = page.locator('input[name="print_thermal"]');
    await printThermalInput.evaluate((input) => {
        input.value = '0';
        input.setAttribute('value', '0');
    });
    await expect(printThermalInput).toHaveValue('0');

    const saleResponsePromise = page.waitForResponse((response) => {
        const url = new URL(response.url());

        return response.request().method() === 'POST' && url.pathname === '/point-de-vente/vente';
    });
    await submitButton.click({ noWaitAfter: true });
    const saleResponse = await saleResponsePromise;
    expect([302, 303]).toContain(saleResponse.status());

    const receiptUrl = saleResponse.headers().location;
    expect(receiptUrl).toMatch(/\/point-de-vente\/tickets\/\d+$/);
    await page.goto(receiptUrl);
    await expect(page).toHaveURL(/\/point-de-vente\/tickets\/\d+$/);

    await page.getByRole('link', { name: 'Version thermique' }).click();
    await expect(page).toHaveURL(/\/point-de-vente\/tickets\/\d+\/thermique$/);
    await expect(page.getByText('Ticket de caisse')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Ticket detaille' })).toBeVisible();

    await page.getByRole('link', { name: 'Retour' }).click();
    await page.waitForURL(/\/point-de-vente\/vente/);
    await expect(page.getByRole('button', { name: 'Valider et encaisser' })).toBeVisible();
});
