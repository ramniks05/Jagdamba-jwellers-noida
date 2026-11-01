/**
 * Shows where payment date appears: change Bill date to a past day → Payment section.
 * node scripts/e2e-old-billing-payment-demo.mjs
 */
import { chromium } from 'playwright';

const base = process.env.APP_URL || 'http://127.0.0.1:8000';
const email = process.env.E2E_EMAIL || 'owner@jagdamba.test';
const password = process.env.E2E_PASSWORD || 'ChangeMe@123';
const pauseMs = Number(process.env.E2E_PAUSE_MS || 180_000);

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
        browser = await chromium.launch({ headless: false, slowMo: 400, ...(channel ? { channel } : {}) });
        break;
    } catch {
        //
    }
}
if (!browser) {
    console.error('Need Edge or Chrome for visible demo.');
    process.exit(1);
}

const page = await browser.newPage();
try {
    console.log('Login → New bill…');
    await login(page);
    await page.goto(`${base}/sales/create`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#sold-at', { timeout: 15_000 });

    const dateVisible = await page.locator('.bill-pay-date-wrap').first().isVisible();
    console.log('Payment date visible before change:', dateVisible);

    await page.locator('#sold-at').scrollIntoViewIfNeeded();
    await page.waitForTimeout(600);

    const soldAt = page.locator('#sold-at');
    const current = await soldAt.inputValue();
    const past = new Date();
    past.setDate(past.getDate() - 3);
    const y = past.getFullYear();
    const m = String(past.getMonth() + 1).padStart(2, '0');
    const d = String(past.getDate()).padStart(2, '0');
    const backdated = `${y}-${m}-${d}T10:30`;

    console.log('Set Bill date to past day (old bill)…', backdated);
    await soldAt.fill(backdated);
    await soldAt.dispatchEvent('change');
    await page.waitForTimeout(1200);

    await page.locator('.bill-pay-date').first().waitFor({ state: 'visible', timeout: 8000 });

    await page.evaluate(() => {
        document.querySelector('.bill-pay-date')?.scrollIntoView({ block: 'center' });
    });
    await page.waitForTimeout(1500);

    const payDate = page.locator('.bill-pay-date').first();
    await payDate.hover();
    await page.waitForTimeout(800);

    console.log('');
    console.log('=== WHERE TO ENTER PAYMENT DATE ===');
    console.log('1. Right column → Payment card');
    console.log('2. First change Bill date & time (above) to any day BEFORE today');
    console.log('3. Hover (i) next to Payment for old-bill help');
    console.log('4. Each payment row gets payment date — between Amount and Reference');
    console.log('5. "Add payment" for more rows');
    console.log('');
    console.log('Bill date after save → 12:00 on that day. Edit payment date before Save invoice.');
    console.log(`Browser open ${Math.round(pauseMs / 1000)}s — look at Payment card on the right.`);
    await page.waitForTimeout(pauseMs);
} catch (error) {
    console.error('Demo failed:', error.message || error);
    await page.waitForTimeout(90_000);
    process.exitCode = 1;
} finally {
    await browser.close();
}
