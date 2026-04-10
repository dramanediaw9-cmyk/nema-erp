import { test, expect } from '@playwright/test';

test('cashier can open POS session, validate a sale, and open the thermal ticket', async ({ page }) => {
    await page.goto('/login');

    await page.getByLabel('Adresse e-mail').fill('caissier@nema-erp.test');
    await page.getByLabel('Mot de passe').fill('password');
    await page.getByRole('button', { name: 'Se connecter' }).click();

    await page.goto('/point-de-vente');

    const currentSessionLink = page.getByRole('link', { name: 'Nouvelle vente comptoir' });
    const openCashButton = page.getByRole('button', { name: 'Ouvrir la caisse' });

    if (await openCashButton.isVisible()) {
        await page.locator('#cash_account_id').selectOption({ index: 1 });
        await page.locator('#warehouse_id').selectOption({ index: 1 });
        await page.locator('#opening_amount').fill('0');
        await openCashButton.click();
        await expect(page).toHaveURL(/\/point-de-vente\/sessions\/\d+/);
    }

    if (await currentSessionLink.isVisible()) {
        await currentSessionLink.click();
    } else {
        await page.getByRole('link', { name: 'Nouvelle vente comptoir' }).click();
    }

    await expect(page).toHaveURL(/\/point-de-vente\/vente/);
    await expect(page.locator('[data-product-id]').first()).toBeVisible();

    await page.locator('[data-product-id]').first().click();
    await page.locator('#cash_received_amount').fill('999999');

    await Promise.all([
        page.waitForURL(/\/point-de-vente\/tickets\/\d+\/thermique\?auto_print=1/),
        page.getByRole('button', { name: 'Valider et encaisser' }).click(),
    ]);

    await expect(page.getByText('TICKET CAISSE')).toBeVisible();
    await expect(page.getByText('Si l\'impression ne se lance pas sur cet appareil')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Ticket detaille' })).toBeVisible();
});
