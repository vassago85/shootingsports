import { execSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');

export const playwrightDatabase = path.join(root, 'database', 'playwright.sqlite').replaceAll('\\', '/');

export function playwrightEnv(): NodeJS.ProcessEnv {
    return {
        ...process.env,
        APP_ENV: 'local',
        APP_URL: 'http://127.0.0.1:8124',
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: playwrightDatabase,
        DB_URL: '',
        COMING_SOON: '0',
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
