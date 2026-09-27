import { expect, test } from '@playwright/test';

test('home points a new shooter at find your discipline', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByRole('heading', { name: /Find your event/ })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Find your discipline' })).toBeVisible();
});

test('the questionnaire recommends a pistol sport', async ({ page }) => {
    await page.goto('/find');

    await page.getByRole('button', { name: 'Pistol' }).click();
    await page.getByRole('button', { name: 'Speed' }).click();
    await page.getByRole('button', { name: 'Outdoor' }).click();
    await page.getByRole('button', { name: 'Up close' }).click();
    await page.getByRole('button', { name: 'On my own' }).click();
    await page.getByRole('button', { name: 'A moderate start' }).click();

    await expect(page.getByRole('link', { name: 'IPSC' })).toBeVisible();
});

test('the calendar can show only training events', async ({ page }) => {
    await page.goto('/calendar?kind=training');

    await expect(page.getByText('Playwright Training Day')).toBeVisible();
    await expect(page.getByText('Playwright Club Match')).toHaveCount(0);
});

test('old match urls redirect to events', async ({ page }) => {
    await page.goto('/matches/playwright-club-match');

    await expect(page).toHaveURL(/\/events\/playwright-club-match$/);
    await expect(page.getByRole('heading', { name: 'Playwright Club Match' })).toBeVisible();
});

test('the activity feed shows a published article', async ({ page }) => {
    await page.goto('/feed');

    await expect(page.getByRole('heading', { name: 'Playwright range report' })).toBeVisible();
});

test('a shooter can record an entry without paying on the site', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email').fill('playwright@shootingsports.test');
    await page.getByLabel('Password').fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL('**/my-calendar');

    await page.goto('/events/playwright-club-match');
    await page.getByRole('button', { name: 'Enter this event' }).click();

    await expect(page.getByText('You are entered')).toBeVisible();
    await expect(page.getByText('Pay the entry fee to the organiser.')).toBeVisible();
});
