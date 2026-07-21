import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.NEMA_E2E_BASE_URL || 'http://localhost:8000';

export default defineConfig({
    testDir: './e2e',
    timeout: 60_000,
    // The smoke scenarios intentionally exercise one shared seeded database.
    // Keep CI serial so concurrent sales and portal writes cannot contend for
    // SQLite locks and turn a product failure into a flaky browser timeout.
    workers: process.env.CI ? 1 : undefined,
    expect: {
        timeout: 10_000,
    },
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'off',
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                browserName: 'chromium',
            },
        },
        {
            name: 'edge',
            use: {
                ...devices['Desktop Edge'],
                browserName: 'chromium',
                channel: 'msedge',
            },
        },
    ],
});
