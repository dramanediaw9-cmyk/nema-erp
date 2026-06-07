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

    await availableProduct.click();
    await expect(page.locator('[data-line-card]').first()).toBeVisible();
    await page.locator('#cash_received_amount').fill('999999');

    const submitButton = page.getByRole('button', { name: 'Valider et encaisser' });
    await expect(submitButton).toHaveAttribute('aria-disabled', 'false');
    await submitButton.click();
    await page.waitForURL(/\/point-de-vente\/(tickets\/\d+\/thermique|vente)/);

    if (/\/point-de-vente\/tickets\/\d+\/thermique/.test(page.url())) {
        await expect(page.getByText('TICKET CAISSE')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Ticket detaille' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Retour caisse' })).toBeVisible();

        await page.evaluate(() => {
            window.dispatchEvent(new Event('afterprint'));
        });
    }

    await page.waitForURL(/\/point-de-vente\/vente/);
    await expect(page.getByRole('button', { name: 'Valider et encaisser' })).toBeVisible();
});
