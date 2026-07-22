import { test, expect } from '@playwright/test';

const signatureDataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==';

async function loginAsManager(page) {
    await page.goto('/login');

    await page.getByLabel('Adresse e-mail').fill('manager@nema-erp.test');
    await page.getByLabel('Mot de passe').fill('password');
    await page.getByRole('button', { name: 'Se connecter' }).click();
}

test('manager can collect a portal payment notice and open the prefilled receipt form', async ({ browser, page }) => {
    const invoiceNote = `Smoke portail reglement ${Date.now()}`;
    const signerName = 'Aissata Coulibaly';
    const depositReference = `OM-PORTAIL-${Date.now()}`;

    await loginAsManager(page);
    await page.goto('/ventes/creer');

    await page.locator('#customer_id').selectOption({ index: 1 });
    await page.locator('#due_date').fill('2030-12-31');
    await page.locator('#notes').fill(invoiceNote);
    await page.locator('select[name="items[0][product_id]"]').selectOption({ index: 1 });
    await page.locator('input[name="items[0][qty]"]').fill('2');
    await page.locator('input[name="items[0][unit_price]"]').fill('900');

    await page.getByRole('button', { name: 'Enregistrer', exact: true }).click();

    await expect(page).toHaveURL(/\/ventes\/\d+$/);
    await expect(page.getByText('Portail de reglement client')).toBeVisible();
    await expect(page.locator('#invoice_payment_portal_url')).toBeVisible();

    const invoiceIdMatch = page.url().match(/\/ventes\/(\d+)$/);
    expect(invoiceIdMatch).toBeTruthy();
    const invoiceId = invoiceIdMatch?.[1] ?? '';

    const invoiceHeading = (await page.locator('.premium-detail-hero__copy h2').textContent())?.trim();
    const invoiceNumber = invoiceHeading?.split(' · ')[0]?.trim() ?? '';
    expect(invoiceNumber).not.toBe('');

    const portalUrl = await page.locator('#invoice_payment_portal_url').inputValue();
    expect(portalUrl).toContain('/portail/factures/');

    const portalContext = await browser.newContext();

    try {
        const portalPage = await portalContext.newPage();
        await portalPage.goto(portalUrl);

        await expect(portalPage.getByRole('heading', { name: invoiceNumber })).toBeVisible();
        await expect(portalPage.getByRole('button', { name: 'Transmettre l avis de reglement' })).toBeVisible();

        await portalPage.getByLabel('Nom du declarant').fill(signerName);
        await portalPage.getByLabel('Telephone').fill('+22372000000');
        await portalPage.getByLabel('Fonction').fill('Comptable');
        await portalPage.getByLabel('Societe / structure').fill('Client Test SARL');
        await portalPage.locator('input[name="signature_data_url"]').evaluate((input, value) => {
            input.value = value;
        }, signatureDataUrl);
        await portalPage.locator('#deposit_amount').fill('750');
        await portalPage.locator('#deposit_method').selectOption('orange_money');
        await portalPage.locator('#deposit_reference').fill(depositReference);
        await portalPage.locator('#deposit_expected_at').fill('2030-12-31');
        await portalPage.locator('#deposit_note').fill('Reglement annonce depuis le portail client.');
        await portalPage.getByLabel('Commentaire complementaire').fill('Merci de confirmer la reception.');
        await portalPage.getByRole('checkbox').check();

        await portalPage.getByRole('button', { name: 'Transmettre l avis de reglement' }).click();

        await expect(portalPage.getByText('L avis de reglement a ete transmis a l equipe et sera rapproche dans l ERP.')).toBeVisible();
        await expect(portalPage.getByText('Dernier avis de reglement')).toBeVisible();
        await expect(portalPage.getByText(signerName)).toBeVisible();
        await expect(portalPage.getByText(depositReference)).toBeVisible();
    } finally {
        await portalContext.close();
    }

    await page.reload();

    await expect(page.getByText('Dernier avis de reglement client')).toBeVisible();
    await expect(page.getByText(signerName)).toBeVisible();
    await expect(page.getByText(depositReference)).toBeVisible();

    await page.getByRole('link', { name: 'Creer l encaissement pre-rempli' }).click();

    await expect(page).toHaveURL(/\/paiements\/creer/);
    await expect(page.getByText('Pre-remplissage depuis le portail client')).toBeVisible();
    await expect(page.locator('#invoice_id')).toHaveValue(invoiceId);
    await expect(page.locator('#amount')).toHaveValue(/750/);
    await expect(page.locator('#method')).toHaveValue('orange_money');
    await expect(page.locator('#reference')).toHaveValue(depositReference);
    await expect(page.locator('#notes')).toContainText(`Avis portail ${invoiceNumber}`);
});
