import { createServer, type Server } from 'node:http';
import { expect, test, type Page } from '@playwright/test';
import { paystackStubPort } from './global-setup';

/**
 * Platform billing end-to-end flows. DB side-effects are covered by the
 * Pest suite; these specs assert the user-visible state only.
 *
 * Paystack is called server-side by PHP, which page.route() cannot
 * intercept, so a local stub server answers on PAYSTACK_BASE_URL (see
 * global-setup.ts). Browser-side hops to checkout.paystack.com are
 * fulfilled with page.route().
 */

const paystackRequests: string[] = [];
let paystackStub: Server;

test.beforeAll(async () => {
    paystackStub = createServer((request, response) => {
        paystackRequests.push(`${request.method} ${request.url}`);
        response.setHeader('Content-Type', 'application/json');

        if (request.method === 'POST' && request.url === '/subaccount') {
            response.end(JSON.stringify({ status: true, data: { subaccount_code: 'ACCT_fresh', is_verified: false } }));

            return;
        }

        if (request.method === 'POST' && request.url === '/transaction/initialize') {
            response.end(JSON.stringify({
                status: true,
                data: {
                    authorization_url: 'https://checkout.paystack.com/pw',
                    access_code: 'ac',
                    reference: 'ref_pw',
                },
            }));

            return;
        }

        response.statusCode = 404;
        response.end(JSON.stringify({ status: false, message: 'Not stubbed' }));
    });

    await new Promise<void>((resolve) => paystackStub.listen(paystackStubPort, '127.0.0.1', resolve));
});

test.afterAll(async () => {
    await new Promise<void>((resolve) => paystackStub.close(() => resolve()));
});

async function loginAsDeskAdmin(page: Page, email: string): Promise<void> {
    // Hit Filament's panel login directly so AuthenticateSession
    // records the password hash under /desk's cookie from the start.
    //
    // The email + password inputs use wire:model.blur (iOS Password
    // AutoFill workaround in App\Filament\Pages\Auth\Login), so a bare
    // fill() does not push the value into Livewire. We type, blur with
    // Tab, give Livewire a tick to sync, then submit.
    await page.goto('/desk/login');

    const emailInput = page.getByRole('textbox', { name: /Email address/ });
    await emailInput.click();
    await emailInput.pressSequentially(email, { delay: 10 });
    await page.keyboard.press('Tab');

    const password = page.locator('input[type="password"]');
    await password.click();
    await password.pressSequentially('password', { delay: 10 });
    await page.keyboard.press('Tab');

    await page.waitForTimeout(250);

    await Promise.all([
        page.waitForURL((url) => url.pathname === '/desk' || url.pathname === '/desk/', { timeout: 20_000 }),
        page.getByRole('button', { name: 'Sign in' }).click(),
    ]);
}

async function loginAsShooter(page: Page): Promise<void> {
    // Public login works for pages that live in the plain web
    // middleware stack (no AuthenticateSession rewrites), which is
    // where /my-memberships sits.
    await page.goto('/login');
    await page.getByLabel('Email').fill('playwright@shootingsports.test');
    await page.getByLabel('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL('**/my-calendar');
}

test('Trial club admin sees the trial status and end date on the billing dashboard', async ({ page }) => {
    await loginAsDeskAdmin(page, 'trial-admin@shootingsports.test');

    // The trial notice lives on the billing dashboard, not the /desk home.
    await page.goto('/desk/billing');

    const dashboard = page.locator('[data-test="billing-dashboard"]');
    await expect(dashboard).toContainText('Trial Club');
    await expect(dashboard).toContainText(/Trial/);
    await expect(dashboard).toContainText('Trial ends');
    await expect(dashboard).toContainText(/\d{1,2} [A-Z][a-z]{2} \d{4}/);
});

test('Paystack subaccount wizard requires the agreement, then shows a pending subaccount', async ({ page }) => {
    await loginAsDeskAdmin(page, 'nosubaccount-admin@shootingsports.test');

    await page.goto('/desk/billing-paystack');
    await expect(page.getByText('Review the agreement')).toBeVisible();

    // The bank inputs carry `required`, so fill them first; the
    // agreement checkbox is the only thing left blocking the submit.
    await page.getByLabel(/Settlement bank/).fill('058');
    await page.getByLabel(/Account number/).fill('0123456789');
    await page.getByLabel(/Business name/).fill('Fresh Club');

    const submit = page.getByRole('button', { name: 'Create subaccount' });
    await submit.click();
    await expect(page.getByText('You must accept the Collection Agent Agreement')).toBeVisible();

    await page.getByLabel(/I accept the Collection Agent Agreement/).check();
    await submit.click();

    await expect(page.getByText('Paystack subaccount created')).toBeVisible({ timeout: 15_000 });
    expect(paystackRequests).toContain('POST /subaccount');

    await page.reload();
    await expect(page.getByText('ACCT_fresh')).toBeVisible();
    await expect(page.getByText('(pending)')).toBeVisible();
});

test('Linked shooter sees the three-line checkout breakdown for a subaccount club', async ({ page }) => {
    await loginAsShooter(page);

    await page.goto('/my-memberships');
    await expect(page.getByRole('heading', { name: /My memberships/ })).toBeVisible();

    const row = page.locator('[data-test="membership-row"]', { hasText: 'Subaccount Club' });
    await expect(row).toBeVisible();
    await row.getByRole('link', { name: 'Open' }).click();

    const breakdown = page.locator('[data-test="checkout-breakdown"]');
    await expect(breakdown).toBeVisible();
    await expect(breakdown).toContainText(/Fee to Subaccount Club/);
    await expect(breakdown).toContainText(/R586\.20/);
    await expect(breakdown).toContainText(/R13\.80/);
    await expect(breakdown).toContainText(/R600\.00/);
});

// Skipped: Task 15 only shipped the `platform:batch-club-payouts` CLI
// command. There is no /desk/billing/payouts Livewire UI or "Request
// payout" action yet; un-skip once that UI lands.
test.skip('Bank-transfer club admin can request a payout', async () => {});

// Skipped: the "Change tier" button on BillingDashboard is a stub that
// only emits an info notification ("coming soon"); there is no tier
// change flow or prorated preview to drive. Un-skip with the real flow.
test.skip('Admin can change tier from Micro to Standard and sees a prorated preview', async () => {});

test('Overage club admin sees the tier overage banner on the desk home', async ({ page }) => {
    await loginAsDeskAdmin(page, 'overage-admin@shootingsports.test');

    await page.goto('/desk');

    await expect(page.getByText('Overage Club').first()).toBeVisible();
    await expect(page.getByText(/exceeded/i).first()).toBeVisible();
    await expect(page.getByText(/\d+ days/).first()).toBeVisible();
});

test('/organise single-event signup redirects to Paystack checkout', async ({ page }) => {
    // Never let the browser reach the real checkout page.
    await page.route('https://checkout.paystack.com/**', (route) =>
        route.fulfill({ status: 200, contentType: 'text/html', body: '<html><body>Paystack checkout stub</body></html>' }),
    );

    const eventDate = new Date();
    eventDate.setDate(eventDate.getDate() + 30);
    const isoDate = eventDate.toISOString().slice(0, 10);

    await page.goto('/organise');
    await expect(page.getByRole('heading', { name: /List a single event/ })).toBeVisible();

    await page.locator('input[name="name"]').fill('Playwright Organiser');
    await page.locator('input[name="email"]').fill('organiser@playwright.test');
    await page.locator('input[name="password"]').fill('password');
    await page.locator('input[name="event_name"]').fill('Playwright One-Off');
    await page.locator('input[name="event_date"]').fill(isoDate);
    await page.locator('input[name="venue_city"]').fill('Pretoria');

    await Promise.all([
        page.waitForURL(/https:\/\/checkout\.paystack\.com\/pw/, { timeout: 20_000 }),
        page.getByRole('button', { name: 'Continue to payment' }).click(),
    ]);

    expect(paystackRequests).toContain('POST /transaction/initialize');
});
