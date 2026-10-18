import { expect, test, type Page } from '@playwright/test';

async function loginAsSecretary(page: Page): Promise<void> {
    // Hit Filament's panel login directly so AuthenticateSession
    // records the password hash under /desk's cookie from the start.
    //
    // The email + password inputs use wire:model.blur (iOS Password
    // AutoFill workaround in App\Filament\Pages\Auth\Login), so a bare
    // fill() does not push the value into Livewire. We type, blur with
    // Tab, give Livewire a tick to sync, then submit.
    await page.goto('/desk/login');

    const email = page.getByRole('textbox', { name: /Email address/ });
    await email.click();
    await email.pressSequentially('playwright-secretary@shootingsports.test', { delay: 10 });
    await page.keyboard.press('Tab');

    const password = page.locator('input[type="password"]');
    await password.click();
    await password.pressSequentially('password', { delay: 10 });
    await page.keyboard.press('Tab');

    // Give Livewire a beat to flush the blur-synced state before the
    // submit so the POST carries both credentials.
    await page.waitForTimeout(250);

    await Promise.all([
        page.waitForURL((url) => url.pathname === '/desk' || url.pathname === '/desk/', { timeout: 20_000 }),
        page.getByRole('button', { name: 'Sign in' }).click(),
    ]);
}

test('Desk home shows the membership snapshot for an admin', async ({ page }) => {
    await loginAsSecretary(page);

    const card = page.locator('[data-test="membership-card"]');
    await expect(card).toBeVisible();

    // Alice, Edward and Patricia are all Active; Lisa is Lapsed. Edward
    // expires in 10 days so he's the one in the expiring bucket.
    await expect(card.locator('[data-test="members-active"]')).toContainText('3');
    await expect(card.locator('[data-test="members-expiring"]')).toContainText('1');
});

test('Secretary can filter to expiring members and bulk renew to year-end', async ({ page }) => {
    await loginAsSecretary(page);

    // Open members register. Filament resource lives under /desk/club-members.
    await page.goto('/desk/club-members');
    await expect(page.getByRole('heading', { name: /Members/ })).toBeVisible();
    await expect(page.getByText('Edward Expiring')).toBeVisible();

    // Pick Edward, run the renew bulk action.
    const edwardRow = page.getByRole('row', { name: /Edward Expiring/ });
    await edwardRow.getByRole('checkbox').check();

    // Filament 5 renders the bulk-action group members as plain
    // <button>s inside a popover; getByRole('menuitem') does not
    // match them.
    await page.getByRole('button', { name: /Bulk actions/ }).click();
    await page.getByRole('button', { name: /Renew to year-end/ }).click();

    // Dialog confirms, which triggers the renew sweep for the row.
    await page.getByRole('button', { name: /^Confirm$/ }).click();

    // Expiry column now reads the configured year-end (31 Dec of this
    // year, unless we're past it — the seeder keeps us well before).
    const now = new Date();
    const yearEndYear = now.getMonth() === 11 && now.getDate() === 31 ? now.getFullYear() + 1 : now.getFullYear();
    await expect(edwardRow).toContainText(String(yearEndYear), { timeout: 10_000 });
});

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

test('Linked shooter can self-service renew by notifying EFT paid', async ({ page }) => {
    await loginAsShooter(page);

    await page.goto('/my-memberships');
    await expect(page.getByRole('heading', { name: /My memberships/ })).toBeVisible();
    await expect(page.getByText('Alice Active')).toBeVisible();

    await page.getByRole('link', { name: 'Open' }).first().click();
    await expect(page.getByRole('heading', { name: /Playwright Rifle Club/ })).toBeVisible();

    // Open invoice list shows the single pre-seeded invoice.
    const openInvoice = page.locator('[data-test="open-invoice"]').first();
    await expect(openInvoice).toBeVisible();

    await openInvoice.getByRole('button', { name: /I've paid by EFT/ }).click();

    // Flash message confirms the nudge went through; the invoice row
    // flips to show "Awaiting secretary confirmation".
    await expect(page.locator('[data-test="flash"]')).toBeVisible();
    await expect(page.locator('[data-test="pending-confirmation"]')).toBeVisible();
});

test('Secretary can convert a guest-day visit into an Applied member', async ({ page }) => {
    await loginAsSecretary(page);

    await page.goto('/desk/club-guest-visits');
    await expect(page.getByRole('heading', { name: /guest visits/i })).toBeVisible();

    const guestRow = page.getByRole('row', { name: /Grace Guest/ });
    await expect(guestRow).toBeVisible();

    await guestRow.getByRole('button', { name: /Convert to application/ }).scrollIntoViewIfNeeded();
    await guestRow.getByRole('button', { name: /Convert to application/ }).click();
    const submit = page.getByRole('button', { name: /^Submit$/ });
    await expect(submit).toBeVisible({ timeout: 10000 });
    await submit.click();
    await expect(submit).toBeHidden({ timeout: 10000 });

    // Members register now carries a new Applied row for Grace.
    // Filter the member register directly to Applied via a URL param so
    // pagination can never hide our brand new row.
    await page.goto('/desk/club-members?tableFilters[status][value]=applied');
    const memberRow = page.getByRole('row', { name: /Grace Guest/ });
    await expect(memberRow).toBeVisible();
    await expect(memberRow).toContainText(/Applied/i);
});

test('Membership reports page surfaces revenue, open invoices and status counts', async ({ page }) => {
    await loginAsSecretary(page);

    await page.goto('/desk/membership-reports');
    await expect(page.getByRole('heading', { name: /Membership reports/i })).toBeVisible();

    const revenue = page.locator('[data-test="revenue-cents"]');
    await expect(revenue).toBeVisible();
    await expect(revenue).toContainText(/R\s*600\.00/);

    const openInvoices = page.locator('[data-test="open-invoices"]');
    await expect(openInvoices).toBeVisible();
    await expect(openInvoices).not.toContainText(/^0$/);

    await expect(page.locator('[data-test="status-breakdown"]')).toContainText(/active/i);
    await expect(page.locator('[data-test="active-members"]')).toBeVisible();

    // CSV download smoke — click the button and wait for the download event.
    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('button', { name: /Download revenue CSV/i }).click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toMatch(/membership-revenue-\d{4}\.csv/);
});

test('Secretary can mark an open membership invoice paid and the member row shows renewed', async ({ page }) => {
    await loginAsSecretary(page);

    await page.goto('/desk/membership-invoices');
    await expect(page.getByRole('heading', { name: /Invoices/ })).toBeVisible();

    // Edward is the only pre-seeded open invoice (Alice is active with
    // no invoice, Lisa is lapsed with no invoice).
    const edwardRow = page.getByRole('row', { name: /Edward Expiring/ });
    await expect(edwardRow).toBeVisible();

    // The Mark paid row-action opens a schema-backed modal (method +
    // reference). Filament 5 names the submit button "Submit", not
    // "Confirm", for schema actions.
    await edwardRow.getByRole('button', { name: /Mark paid/ }).click();
    await page.getByRole('dialog', { name: /Mark paid/ }).getByRole('button', { name: /^Submit$/ }).click();

    // Row flips to Paid.
    await expect(edwardRow).toContainText(/Paid/i, { timeout: 10_000 });

    // Member register now shows Edward's expiry as the invoice
    // period_end (31 Dec of the current year unless we're past it).
    await page.goto('/desk/club-members');
    const memberRow = page.getByRole('row', { name: /Edward Expiring/ });
    const now = new Date();
    const yearEndYear = now.getMonth() === 11 && now.getDate() === 31 ? now.getFullYear() + 1 : now.getFullYear();
    await expect(memberRow).toContainText(String(yearEndYear), { timeout: 10_000 });
});
