import { test, expect } from '@playwright/test';

const signatureDataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4////fwAJ+wP9KobjigAAAABJRU5ErkJggg==';

async function loginAsManager(page) {
    await page.goto('/login');

    await page.getByLabel('Adresse e-mail').fill('manager@nema-erp.test');
    await page.getByLabel('Mot de passe').fill('password');
    await page.getByRole('button', { name: 'Se connecter' }).click();
}

test('manager can share a quote portal and customer can accept it online', async ({ browser, page }) => {
    const quoteNote = `Smoke portail devis ${Date.now()}`;
    const signerName = 'Fatou Diallo';
    const depositReference = `PORTAIL-${Date.now()}`;

    await loginAsManager(page);
    await page.goto('/devis/creer');

    await page.locator('#customer_id').selectOption({ index: 1 });
    await page.locator('#valid_until').fill('2030-12-31');
    await page.locator('#notes').fill(quoteNote);
    await page.locator('select[name="items[0][product_id]"]').selectOption({ index: 1 });
    await page.locator('input[name="items[0][qty]"]').fill('3');
    await page.locator('input[name="items[0][unit_price]"]').fill('500');

    await page.getByRole('button', { name: 'Enregistrer le devis' }).click();

    await expect(page).toHaveURL(/\/devis\/\d+$/);
    await expect(page.getByText(quoteNote)).toBeVisible();

    const quoteNumber = (await page.locator('.page-head h2').textContent())?.trim();
    expect(quoteNumber).toBeTruthy();

    await page.getByRole('button', { name: 'Marquer comme envoye' }).click();
    await expect(page.locator('.alert.alert-success')).toContainText('Devis marque comme envoye.');
    await expect(page.locator('.badge').filter({ hasText: /^Envoye$/ })).toBeVisible();

    const portalUrl = await page.locator('#quote_portal_url').inputValue();
    expect(portalUrl).toContain('/portail/devis/');

    const portalContext = await browser.newContext();

    try {
        const portalPage = await portalContext.newPage();
        await portalPage.goto(portalUrl);

        await expect(portalPage.getByRole('heading', { name: quoteNumber })).toBeVisible();
        await expect(portalPage.getByRole('button', { name: 'Signer et confirmer ce devis' })).toBeVisible();

        await portalPage.getByLabel('Nom du signataire').fill(signerName);
        await portalPage.getByLabel('Telephone').fill('+22370000000');
        await portalPage.getByLabel('Fonction').fill('Gerante');
        await portalPage.getByLabel('Societe / structure').fill('Client Test SARL');
        await portalPage.getByLabel('Commentaire client').fill('Validation commerciale smoke test.');
        await portalPage.locator('#deposit_amount').fill('500');
        await portalPage.locator('#deposit_method').selectOption('wave');
        await portalPage.locator('#deposit_reference').fill(depositReference);
        await portalPage.locator('#deposit_note').fill('Acompte annonce via le portail client.');
        await portalPage.locator('#deposit_expected_at').fill('2030-12-31');
        await portalPage.locator('input[name="signature_data_url"]').evaluate((input, value) => {
            input.value = value;
        }, signatureDataUrl);
        await portalPage.getByRole('checkbox').check();

        await portalPage.getByRole('button', { name: 'Signer et confirmer ce devis' }).click();

        await expect(portalPage.getByText('Le devis a ete confirme et signe avec succes.')).toBeVisible();
        await expect(portalPage.getByText('Devis deja confirme')).toBeVisible();
        await expect(portalPage.getByText(signerName)).toBeVisible();
        await expect(portalPage.getByText(depositReference)).toBeVisible();
    } finally {
        await portalContext.close();
    }

    await page.reload();

    await expect(page.getByText('Signature client portail')).toBeVisible();
    await expect(page.getByText(signerName)).toBeVisible();
    await expect(page.getByText(depositReference)).toBeVisible();
    await expect(page.locator('.badge').filter({ hasText: /^Accepte$/ })).toBeVisible();
});
