import { defineConfig, devices } from '@playwright/test';
import { playwrightEnv } from './tests/Playwright/global-setup';

export default defineConfig({
    testDir: './tests/Playwright',
    testMatch: '**/*.spec.ts',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: 0,
    workers: 1,
    reporter: 'list',
    globalSetup: './tests/Playwright/global-setup.ts',
    use: {
        baseURL: 'http://127.0.0.1:8124',
        trace: 'on-first-retry',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8124',
        url: 'http://127.0.0.1:8124',
        reuseExistingServer: false,
        timeout: 120_000,
        env: playwrightEnv(),
    },
});
