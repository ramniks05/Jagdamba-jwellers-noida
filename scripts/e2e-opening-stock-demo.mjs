/**
 * Headed demo: Stock and purchase → Change opening.
 * Run: node scripts/e2e-opening-stock-demo.mjs
 * Keeps the browser open ~2 minutes at the end so you can look around.
 */
import { chromium } from 'playwright';

const base = process.env.APP_URL || 'http://127.0.0.1:8000';
const email = process.env.E2E_EMAIL || 'owner@jagdamba.test';
const password = process.env.E2E_PASSWORD || 'ChangeMe@123';
const pauseMs = Number(process.env.E2E_PAUSE_MS || 120_000);

async function login(page) {
    await page.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('#email', email);
    await page.fill('#password', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 15_000 });
}

const channels = ['msedge', 'chrome', undefined];
let browser;
for (const channel of channels) {
    try {
        browser = await chromium.launch({
            headless: false,
            slowMo: 350,
            ...(channel ? { channel } : {}),
        });
        break;
    } catch {
        // try next
    }
}
if (!browser) {
    console.error('Could not launch a visible browser (install Edge/Chrome or run: npx playwright install chromium).');
    process.exit(1);
}

const page = await browser.newPage();
page.setDefaultTimeout(20_000);

try {
    console.log('1/5 Login…');
    await login(page);

    console.log('2/5 Open Stock and purchase…');
    await page.goto(`${base}/reports/stock`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('.stock-board, .card', { timeout: 15_000 });

    const changeLink = page.getByRole('link', { name: 'Change opening' }).first();
    const hasChange = await changeLink.isVisible().catch(() => false);

    if (!hasChange) {
        console.log('   No opening row yet — adding sample opening (22K, 100 g)…');
        await page.selectOption('#stock-kind', 'opening');
        await page.fill('#stock-grams', '100');
        await page.getByRole('button', { name: 'Add to stock' }).click();
        await page.waitForURL(/\/reports\/stock/, { timeout: 15_000 });
        await changeLink.waitFor({ state: 'visible', timeout: 10_000 });
    }

    console.log('3/5 Click Change opening…');
    await changeLink.click();
    await page.waitForURL(/edit=/, { timeout: 10_000 });
    await page.getByText('Change opening stock').waitFor({ state: 'visible' });

    console.log('4/5 Edit grams → Save opening…');
    const grams = page.locator('#stock-grams');
    const before = (await grams.inputValue()).trim();
    const next = before === '99' ? '100' : '99';
    await grams.fill(next);
    await page.getByRole('button', { name: 'Save opening' }).click();
    await page.waitForURL((url) => !url.search.includes('edit='), { timeout: 15_000 });

    console.log('5/5 Done — opening updated', before, '→', next);
    console.log(`Browser stays open ${Math.round(pauseMs / 1000)}s. Close the window anytime.`);
    await page.waitForTimeout(pauseMs);
} catch (error) {
    console.error('Demo failed:', error.message || error);
    console.log('Browser left open 60s for debugging.');
    await page.waitForTimeout(60_000);
    process.exitCode = 1;
} finally {
    await browser.close();
}
