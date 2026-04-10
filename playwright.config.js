import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.NEMA_E2E_BASE_URL || 'http://localhost:8000';

export default defineConfig({
    testDir: './e2e',
    timeout: 60_000,
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
            name: 'edge',
            use: {
                ...devices['Desktop Edge'],
                browserName: 'chromium',
                channel: 'msedge',
            },
        },
    ],
});
