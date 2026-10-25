/**
 * Capture every page of the Shooting Sports web preview at iPhone 14
 * dimensions. Run against a live `npx expo start --web --port 8099` and
 * a running `php artisan serve` on 127.0.0.1:8000. Output lands in
 * ../docs/screenshots/*.png.
 */
const path = require('path');
const { chromium } = require('playwright');

const OUT = path.join(__dirname, '..', 'docs', 'screenshots');
const BASE = 'http://localhost:8099';
const VIEWPORT = { width: 390, height: 844 };

/**
 * One screenshot step. `prep` runs after navigation but before
 * screenshotting — useful for opening the filter sheet, tapping a
 * mode chip, etc. `wait` is extra idle time after navigation.
 */
const STEPS = [
  {
    file: '01-home.png',
    path: '/',
    wait: 4000,
    // Home does a cold /events?limit=... fetch and the first paint is
    // the loading spinner. Wait for the "This weekend" / "Next 30 days"
    // headings so the capture gets real cards, not the shimmer.
    prep: async (page) => {
      await page
        .getByText(/This weekend|Next 30 days/i)
        .first()
        .waitFor({ timeout: 8000 })
        .catch(() => {});
      await page.waitForTimeout(400);
    },
  },
  { file: '02-calendar.png', path: '/calendar', wait: 4500 },
  {
    file: '03-calendar-filters-all.png',
    path: '/calendar',
    wait: 4000,
    prep: async (page) => {
      await page.getByRole('button', { name: /filter/i }).first().click({ timeout: 5000 }).catch(() => {});
      await page.waitForTimeout(800);
    },
  },
  {
    file: '04-calendar-filters-handgun.png',
    path: '/calendar',
    wait: 4000,
    prep: async (page) => {
      await page.getByRole('button', { name: /filter/i }).first().click({ timeout: 5000 }).catch(() => {});
      await page.waitForTimeout(600);
      await page.getByText(/^Handgun$/i).first().click({ timeout: 5000 }).catch(() => {});
      await page.waitForTimeout(500);
    },
  },
  {
    file: '05-calendar-filtered-handgun.png',
    path: '/calendar',
    wait: 4000,
    prep: async (page) => {
      await page.getByRole('button', { name: /filter/i }).first().click({ timeout: 5000 }).catch(() => {});
      await page.waitForTimeout(500);
      await page.getByText(/^Handgun$/i).first().click({ timeout: 5000 }).catch(() => {});
      await page.waitForTimeout(400);
      // Close the sheet by tapping "Done" or scrim.
      await page
        .getByRole('button', { name: /done|apply|close/i })
        .first()
        .click({ timeout: 3000 })
        .catch(() => {});
      await page.waitForTimeout(600);
    },
  },
  {
    file: '06-find-sports.png',
    path: '/find',
    wait: 4500,
    // Sports is the default mode on the web Find page.
  },
  {
    file: '07-find-clubs.png',
    path: '/find',
    wait: 4500,
    prep: async (page) => {
      await page.getByText(/^Clubs$/i).first().click({ timeout: 5000 }).catch(() => {});
      await page.waitForTimeout(1500);
    },
  },
  {
    file: '08-find-ranges.png',
    path: '/find',
    wait: 4500,
    prep: async (page) => {
      await page.getByText(/^Ranges$/i).first().click({ timeout: 5000 }).catch(() => {});
      await page.waitForTimeout(1500);
    },
  },
  {
    file: '09-find-shops.png',
    path: '/find',
    wait: 4500,
    prep: async (page) => {
      await page.getByText(/^Shops$/i).first().click({ timeout: 5000 }).catch(() => {});
      await page.waitForTimeout(1500);
    },
  },
  { file: '10-me-unauth.png', path: '/me', wait: 3500 },
  { file: '11-login.png', path: '/login', wait: 3500 },
  { file: '12-match-detail-top.png', path: '/matches/saprf-prs-wc-2-day-national-2026', wait: 4500 },
  {
    file: '13-match-detail-full.png',
    path: '/matches/saprf-prs-wc-2-day-national-2026',
    wait: 4500,
    fullPage: true,
  },
  {
    file: '14-match-detail-with-links.png',
    path: '/matches/impala-haelgeweer-skietdag-2026',
    wait: 4500,
    fullPage: true,
  },
  {
    // Item 4 — tap the hero poster to open the full-screen viewer.
    // Impala Haelgeweer has a banner_url so the Pressable is wired up.
    // The overlay portals to document.body on web (see PosterViewer),
    // so we have to force a viewport-clip screenshot — Playwright's
    // default capture grabs the scrolled ScrollView region beneath,
    // not the fixed overlay above it.
    file: '15-match-detail-image-viewer.png',
    path: '/matches/impala-haelgeweer-skietdag-2026',
    wait: 4500,
    clip: { x: 0, y: 0, width: 390, height: 844 },
    prep: async (page) => {
      // Pressable → role=imagebutton on native; on web react-native-web
      // renders a `div[aria-label]`. Target the aria-label directly so
      // the click doesn't rely on Playwright's role resolution.
      await page
        .locator('[aria-label*="poster"]')
        .first()
        .click({ timeout: 5000 })
        .catch(() => {});
      // Wait until the solid-black fixed-position backdrop is in the DOM
      // so the subsequent screenshot captures the overlay, not the
      // mid-animation state.
      await page.waitForFunction(() => {
        const divs = [...document.querySelectorAll('div')];
        return divs.some((n) => {
          const cs = getComputedStyle(n);
          return cs.backgroundColor === 'rgb(0, 0, 0)' && cs.position === 'fixed';
        });
      }, { timeout: 5000 }).catch(() => {});
      await page.waitForTimeout(500);
    },
  },
  {
    // Item 6 — cold deep-link entry (no `from` param). The custom
    // headerLeft must still render a back chevron + "Back" label so
    // the user is never stranded. Shot focuses on the header area.
    file: '16-match-detail-deeplink-back.png',
    path: '/matches/saprf-prs-wc-2-day-national-2026',
    wait: 4000,
  },
];

(async () => {
  const browser = await chromium.launch({ headless: true });

  async function makePage(height) {
    const context = await browser.newContext({
      viewport: { width: VIEWPORT.width, height },
      deviceScaleFactor: 2,
      isMobile: true,
      hasTouch: true,
    });
    return { context, page: await context.newPage() };
  }

  // Default pager for standard-height shots.
  let { context, page } = await makePage(VIEWPORT.height);

  for (const step of STEPS) {
    const url = BASE + step.path;
    process.stdout.write(`→ ${step.file} (${url})… `);
    try {
      // "fullPage" shots use a tall viewport so the entire match
      // detail renders inside the viewport (the inner ScrollView
      // never activates), then screenshot the whole viewport.
      if (step.fullPage) {
        await context.close();
        ({ context, page } = await makePage(2000));
      }

      await page.goto(url, { waitUntil: 'load', timeout: 20000 });
      await page.waitForTimeout(step.wait || 3000);
      if (typeof step.prep === 'function') {
        await step.prep(page);
      }
      await page.screenshot({
        path: path.join(OUT, step.file),
        fullPage: false,
        type: 'png',
        ...(step.clip ? { clip: step.clip } : {}),
      });
      console.log('ok');

      if (step.fullPage) {
        await context.close();
        ({ context, page } = await makePage(VIEWPORT.height));
      }
    } catch (err) {
      console.log(`FAILED: ${err.message}`);
    }
  }

  await browser.close();
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
