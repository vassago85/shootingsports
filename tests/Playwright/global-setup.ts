import { execSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

export const playwrightDatabase = path.join(root, 'database', 'playwright.sqlite').replaceAll('\\', '/');

/**
 * Paystack is called server-side by PHP, so Playwright's page.route()
 * cannot intercept it. Specs that exercise Paystack start a tiny stub
 * server on this port (see platform-billing.spec.ts) and the PHP app
 * is pointed at it. Nothing in the suite can reach the live API.
 */
export const paystackStubPort = 8125;

export function playwrightEnv(): NodeJS.ProcessEnv {
    return {
        ...process.env,
        PAYSTACK_BASE_URL: `http://127.0.0.1:${paystackStubPort}`,
        PAYSTACK_SECRET_KEY: 'sk_test_playwright',
        APP_ENV: 'local',
        APP_URL: 'http://127.0.0.1:8124',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: playwrightDatabase,
        DB_URL: '',
        COMING_SOON: '0',
        // Filament throttles panel logins (5/min per IP) through the
        // cache. The suite signs in more often than that from one IP,
        // so keep the throttle counters per-request with the array store.
        CACHE_STORE: 'array',
        // The .env ships with SESSION_DOMAIN=.shootingsports.test for
        // local Laragon work. Playwright drives http://127.0.0.1 so
        // the browser rejects any cookie whose Domain does not match
        // and no session ever sticks — Filament's /desk redirects
        // back to /desk/login after a "successful" POST because the
        // session cookie never came back on the follow-up request.
        // 'null' (not '') because Windows drops empty env vars, which
        // would let the .env value leak back in; Laravel parses the
        // string 'null' as null.
        SESSION_DOMAIN: 'null',
    };
}

export default function globalSetup(): void {
    if (!existsSync(path.join(root, 'public', 'build', 'manifest.json'))) {
        execSync('npm run build', { cwd: root, stdio: 'inherit' });
    }

    execSync('php artisan migrate:fresh --force --seed --seeder=PlaywrightSeeder', {
        cwd: root,
        env: playwrightEnv(),
        stdio: 'inherit',
    });
}
