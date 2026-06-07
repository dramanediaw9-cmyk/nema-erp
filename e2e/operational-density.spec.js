import { expect, test } from '@playwright/test';

const workPages = [
    ['/produits', '.catalog-table'],
    ['/stock', '.inventory-table'],
    ['/ventes', '.records-table'],
    ['/achats', '.records-table'],
    ['/recherche?q=PRD', '.search-grid'],
];

async function login(page) {
    await page.goto('/login');
    await page.getByLabel('Adresse e-mail').fill('manager@nema-erp.test');
    await page.getByLabel('Mot de passe').fill('password');
    await page.getByRole('button', { name: 'Se connecter' }).click();
    await expect(page).toHaveURL(/dashboard|onboarding|workspace|select-company/);
}

test('core work pages keep operational data high on desktop', async ({ page }) => {
    await login(page);

    for (const [url, dataSelector] of workPages) {
        await page.goto(url);

        await expect(page.locator('.erp-module-bar')).toBeVisible();
        await expect(page.locator('.module-favorite-button')).toHaveCount(0);
        await expect(page.locator('.erp-work-toolbar, .search-workbar')).toBeVisible();

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

    test('stock page stays compact without horizontal page overflow', async ({ page }) => {
        await login(page);
        await page.goto('/stock');

        await expect(page.locator('.mobile-menu-button')).toBeVisible();
        await expect(page.locator('.erp-work-toolbar')).toBeVisible();
        await expect(page.locator('.erp-kpi-strip')).toBeVisible();
        await expect(page.locator('.erp-filter-panel')).toBeVisible();

        const mainBox = await page.locator('.main').boundingBox();
        expect(mainBox.width).toBeGreaterThan(350);

        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
        expect(overflow).toBe(false);

        await page.screenshot({ path: 'logs/operational-density-mobile.png', fullPage: false });

        await page.locator('[data-sidebar-toggle]').click();
        await expect(page.locator('[data-layout-shell]')).toHaveAttribute('data-sidebar-open', 'true');
    });
});
