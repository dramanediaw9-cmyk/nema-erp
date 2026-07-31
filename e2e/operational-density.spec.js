import { expect, test } from '@playwright/test';

const workPages = [
    { url: '/produits', dataSelector: '.catalog-table', headerSelector: '.erp-work-toolbar' },
    { url: '/stock', dataSelector: '.inventory-table', headerSelector: '.erp-work-toolbar' },
    { url: '/ventes', dataSelector: '.records-table', headerSelector: '.erp-work-toolbar' },
    { url: '/achats', dataSelector: '.records-table', headerSelector: '.erp-work-toolbar' },
    { url: '/recherche?q=PRD', dataSelector: '.search-grid', headerSelector: '.search-workbar' },
    { url: '/utilisateurs', dataSelector: '.table-wrap', headerSelector: '.page-head' },
    { url: '/roles', dataSelector: '.table-wrap', headerSelector: '.page-head' },
    { url: '/comptabilite/balance', dataSelector: '.table-wrap', headerSelector: '.erp-work-toolbar' },
    { url: '/comptabilite/grand-livre', dataSelector: '.table-wrap', headerSelector: '.erp-work-toolbar' },
    { url: '/comptabilite/compte-resultat', dataSelector: '.table-wrap', headerSelector: '.erp-work-toolbar' },
    { url: '/comptabilite/bilan', dataSelector: '.table-wrap', headerSelector: '.erp-work-toolbar' },
    { url: '/comptabilite/fiscalite', dataSelector: '.table-wrap', headerSelector: '.erp-work-toolbar' },
    { url: '/comptabilite/journaux', dataSelector: '.table-wrap', headerSelector: '.page-head' },
];

async function login(page) {
    await page.goto('/login');
    await page.getByLabel('Adresse e-mail').fill('manager@nema-erp.test');
    await page.getByLabel('Mot de passe').fill('password');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(/dashboard|onboarding|workspace|select-company/);
}

async function expectNoPageOverflow(page) {
    const dimensions = await page.evaluate(() => ({
        viewportWidth: window.innerWidth,
        documentWidth: document.documentElement.scrollWidth,
        bodyWidth: document.body.scrollWidth,
    }));

    expect(dimensions.documentWidth).toBeLessThanOrEqual(dimensions.viewportWidth + 1);
    expect(dimensions.bodyWidth).toBeLessThanOrEqual(dimensions.viewportWidth + 1);
}

test('core work pages keep operational data high on desktop', async ({ page }) => {
    await login(page);

    for (const { url, dataSelector, headerSelector } of workPages) {
        await page.goto(url);

        await expect(page.locator('.erp-module-bar')).toBeVisible();
        await expect(page.locator('.module-favorite-button')).toHaveCount(0);
        await expect(page.locator(headerSelector)).toBeVisible();

        const dataRegion = page.locator(dataSelector).first();
        await expect(dataRegion).toBeVisible();

        const dataBox = await dataRegion.boundingBox();
        const viewport = page.viewportSize();

        expect(dataBox.y).toBeLessThan(viewport.height * 0.65);
        expect(await page.locator('.premium-hero, .sales-hero, .purchases-hero, .search-hero').count()).toBe(0);
    }

    await page.screenshot({ path: 'logs/operational-density-desktop.png', fullPage: false });
});

test.describe('mobile work layout', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('core work pages stay compact without horizontal page overflow', async ({ page }) => {
        await login(page);

        for (const { url, dataSelector, headerSelector } of workPages) {
            await page.goto(url);

            await expect(page.locator('.mobile-menu-button')).toBeVisible();
            await expect(page.locator(headerSelector)).toBeVisible();
            await expect(page.locator(dataSelector).first()).toBeVisible();

            const mainBox = await page.locator('.main').boundingBox();
            expect(mainBox.width).toBeGreaterThan(350);
            await expectNoPageOverflow(page);
        }

        await page.screenshot({ path: 'logs/operational-density-mobile.png', fullPage: false });

        await page.locator('[data-sidebar-toggle]').click();
        await expect(page.locator('[data-layout-shell]')).toHaveAttribute('data-sidebar-open', 'true');
    });
});

test.describe('tablet work layout', () => {
    test.use({ viewport: { width: 768, height: 1024 } });

    test('core work pages keep filters and data reachable without page overflow', async ({ page }) => {
        await login(page);

        for (const { url, dataSelector, headerSelector } of workPages) {
            await page.goto(url);

            await expect(page.locator(headerSelector)).toBeVisible();
            await expect(page.locator(dataSelector).first()).toBeVisible();
            await expectNoPageOverflow(page);
        }
    });
});
