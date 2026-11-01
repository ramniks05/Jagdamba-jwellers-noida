/**
 * Headed walkthrough: New bill page — tooltips, no long helper paragraphs.
 * node scripts/e2e-sales-ui-demo.mjs
 */
import { chromium } from 'playwright';

const base = process.env.APP_URL || 'http://127.0.0.1:8000';
const email = process.env.E2E_EMAIL || 'owner@jagdamba.test';
const password = process.env.E2E_PASSWORD || 'ChangeMe@123';
const pauseMs = Number(process.env.E2E_PAUSE_MS || 150_000);

const longSnippets = [
    'Type the scale weight',
    'Tick Own when this gold',
    'Murti, gifts, and fixed-price',
    'Choose the customer, then press',
    'Change bill date only when',
    'Net weight on the bill comes off this tray',
];

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
        browser = await chromium.launch({ headless: false, slowMo: 280, ...(channel ? { channel } : {}) });
        break;
    } catch {
        //
    }
}
if (!browser) {
    console.error('Need Edge/Chrome for visible browser.');
    process.exit(1);
}

const page = await browser.newPage();
try {
    console.log('Login → New bill…');
    await login(page);
    await page.goto(`${base}/sales/create`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#bill-form', { timeout: 15_000 });

    const bodyText = await page.locator('body').innerText();
    const visibleLong = longSnippets.filter((s) => bodyText.includes(s));
    if (visibleLong.length) {
        console.warn('Still visible on page (should be tooltip only):', visibleLong);
    } else {
        console.log('OK: long helper sentences not shown as plain page text.');
    }

    const icons = page.locator('[data-bs-toggle="tooltip"]');
    const count = await icons.count();
    console.log('Switch Add pieces tabs: Scan → Weigh → Other items…');
    for (const panel of ['scan', 'retail', 'weigh']) {
        const tab = page.locator(`[data-bill-panel="${panel}"]`);
        if (await tab.count()) {
            await tab.click();
            await page.waitForTimeout(700);
        }
    }

    console.log(`Hover ${count} info icons (Weight, Own, Other items, customer, scan, bill date…).`);

    for (let i = 0; i < Math.min(count, 6); i++) {
        await icons.nth(i).hover();
        await page.waitForTimeout(900);
    }

    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(800);
    await page.evaluate(() => window.scrollTo(0, 0));

    console.log(`Done. Browser open ${Math.round(pauseMs / 1000)}s — hover any (i) icon for help.`);
    await page.waitForTimeout(pauseMs);
} catch (error) {
    console.error(error.message || error);
    await page.waitForTimeout(60_000);
    process.exitCode = 1;
} finally {
    await browser.close();
}
