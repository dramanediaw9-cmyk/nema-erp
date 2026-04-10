import { test, expect } from '@playwright/test';

const signatureDataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==';

async function loginAsManager(page) {
    await page.goto('/login');

    await page.getByLabel('Adresse e-mail').fill('manager@nema-erp.test');
    await page.getByLabel('Mot de passe').fill('password');
    await page.getByRole('button', { name: 'Se connecter' }).click();
}

test('manager can share an order portal and customer can confirm it online', async ({ browser, page }) => {
    const orderNote = `Smoke portail commande ${Date.now()}`;
    const signerName = 'Moussa Traore';
    const depositReference = `VIR-${Date.now()}`;

    await loginAsManager(page);
    await page.goto('/commandes-clients/creer');

    await page.locator('#customer_id').selectOption({ index: 1 });
    await page.locator('#requested_delivery_date').fill('2030-12-31');
    await page.locator('#commitment_date').fill('2030-12-31');
    await page.locator('#notes').fill(orderNote);
    await page.locator('#customer_reference').fill(`BC-${Date.now()}`);
    await page.locator('#source_document').fill('Smoke portail commande');
    await page.locator('#delivery_instruction').fill('Livraison matin uniquement.');
    await page.locator('select[name="items[0][product_id]"]').selectOption({ index: 1 });
    await page.locator('input[name="items[0][qty]"]').fill('4');
    await page.locator('input[name="items[0][unit_price]"]').fill('550');

    await page.getByRole('button', { name: 'Enregistrer la commande' }).click();

    await expect(page).toHaveURL(/\/commandes-clients\/\d+$/);
    await expect(page.locator('#order_portal_url')).toBeVisible();

    const orderNumber = (await page.locator('.premium-detail-hero__copy h2').textContent())?.split(' · ')[0]?.trim();
    expect(orderNumber).toBeTruthy();

    const portalUrl = await page.locator('#order_portal_url').inputValue();
    expect(portalUrl).toContain('/portail/commandes/');

    const portalContext = await browser.newContext();

    try {
        const portalPage = await portalContext.newPage();
        await portalPage.goto(portalUrl);

        await expect(portalPage.getByRole('heading', { name: orderNumber })).toBeVisible();
        await expect(portalPage.getByRole('button', { name: 'Signer et confirmer cette commande' })).toBeVisible();

        await portalPage.getByLabel('Nom du signataire').fill(signerName);
        await portalPage.getByLabel('Telephone').fill('+22371000000');
        await portalPage.getByLabel('Fonction').fill('Responsable achats');
        await portalPage.getByLabel('Societe / structure').fill('Client Test SARL');
        await portalPage.getByLabel('Commentaire client').fill('Confirmation commerciale depuis le portail.');
        await portalPage.locator('#deposit_amount').fill('650');
        await portalPage.locator('#deposit_method').selectOption('bank_transfer');
        await portalPage.locator('#deposit_reference').fill(depositReference);
        await portalPage.locator('#deposit_expected_at').fill('2030-12-31');
        await portalPage.locator('input[name="signature_data_url"]').evaluate((input, value) => {
            input.value = value;
        }, signatureDataUrl);
        await portalPage.getByRole('checkbox').check();

        await portalPage.getByRole('button', { name: 'Signer et confirmer cette commande' }).click();

        await expect(portalPage.getByText('La commande a ete confirmee et signee avec succes.')).toBeVisible();
        await expect(portalPage.getByText('Commande deja confirmee')).toBeVisible();
        await expect(portalPage.getByText(signerName)).toBeVisible();
        await expect(portalPage.getByText(depositReference)).toBeVisible();
    } finally {
        await portalContext.close();
    }

    await page.reload();

    await expect(page.getByText('Signature client portail')).toBeVisible();
    await expect(page.getByText(signerName)).toBeVisible();
    await expect(page.getByText(depositReference)).toBeVisible();
    await expect(page.locator('.badge').filter({ hasText: /^Confirmee$/ }).first()).toBeVisible();
});
