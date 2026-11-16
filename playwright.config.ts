import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/browser',
    fullyParallel: false,
    workers: 1,
    use: {
        baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8012',
        browserName: 'chromium',
        viewport: { width: 1440, height: 1000 },
    },
    reporter: 'list',
});
