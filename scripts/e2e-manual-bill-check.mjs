/**
 * Manual-style check: login, new bill, old date, payment date, add payment row.
 */
import { chromium } from 'playwright';

const base = process.env.APP_URL || 'http://127.0.0.1:8000';
const email = process.env.E2E_EMAIL || 'owner@jagdamba.test';
const password = process.env.E2E_PASSWORD || 'ChangeMe@123';
const pauseMs = Number(process.env.E2E_PAUSE_MS || 120_000);

const errors = [];

async function login(page) {
    await page.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('#email', email);
    await page.fill('#password', password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 15_000 });
}

let browser;
for (const channel of ['msedge', 'chrome', undefined]) {
    try {
        browser = await chromium.launch({ headless: false, slowMo: 350, ...(channel ? { channel } : {}) });
        break;
    } catch {
        //
    }
}
if (!browser) {
    console.error('FAIL: no visible browser');
    process.exit(1);
}

const page = await browser.newPage();
try {
    await login(page);
    await page.goto(`${base}/sales/create`, { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('#bill-workspace', { timeout: 15_000 });

    if (!await page.locator('[data-bill-panel="weigh"]').isVisible()) errors.push('Weigh tab missing');
    if (await page.locator('#bill-panel-weigh').isVisible()) {
        // ok default weigh
    } else if (await page.locator('#bill-panel-weigh').evaluate((el) => el.classList.contains('d-none'))) {
        // hidden until... weigh should be visible as default
        const weighVisible = await page.locator('#bill-panel-weigh').isVisible();
        if (!weighVisible) errors.push('Weigh panel not open by default');
    }

    await page.locator('#sold-at').scrollIntoViewIfNeeded();
    const past = new Date();
    past.setDate(past.getDate() - 2);
    const back = `${past.getFullYear()}-${String(past.getMonth() + 1).padStart(2, '0')}-${String(past.getDate()).padStart(2, '0')}T14:00`;
    await page.locator('#sold-at').fill(back);
    await page.locator('#sold-at').dispatchEvent('change');
    await page.waitForTimeout(800);

    const helpTip = page.locator('.card-header', { hasText: 'Payment' }).locator('[data-bs-toggle="tooltip"]');
    if (await helpTip.count() === 0) {
        errors.push('Payment help tooltip icon missing');
    }
    const payDate = page.locator('.bill-pay-date').first();
    if (!await payDate.isVisible()) {
        errors.push('Payment date field not visible for old bill');
    } else {
        const manual = `${past.getFullYear()}-${String(past.getMonth() + 1).padStart(2, '0')}-${String(past.getDate() + 1).padStart(2, '0')}T15:30`;
        const today = new Date();
        const payManual = today.getDate() === past.getDate() + 1
            ? `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}T15:30`
            : `${past.getFullYear()}-${String(past.getMonth() + 1).padStart(2, '0')}-${String(Math.min(past.getDate() + 1, 28)).padStart(2, '0')}T15:30`;
        await payDate.fill(payManual);
        const soldVal = await page.locator('#sold-at').inputValue();
        if (!soldVal.endsWith('T12:00')) {
            errors.push(`Bill date should snap to 12:00, got ${soldVal}`);
        }
    }

    await page.locator('#bill-payment-add').click();
    await page.waitForTimeout(500);
    const rows = await page.locator('[data-payment-row]').count();
    if (rows < 2) errors.push('Add payment did not add second row');

    await page.locator('[data-bill-panel="scan"]').click();
    await page.waitForTimeout(400);
    if (!await page.locator('#bill-panel-scan').isVisible()) errors.push('Scan panel did not open');

    if (errors.length) {
        console.error('Manual checks FAILED:\n- ' + errors.join('\n- '));
    } else {
        console.log('Manual checks PASSED: weigh workspace, payment date on old bill, add payment, scan tab.');
    }
    console.log(`Browser open ${Math.round(pauseMs / 1000)}s for you to look.`);
    await page.waitForTimeout(pauseMs);
    process.exitCode = errors.length ? 1 : 0;
} catch (e) {
    console.error('Error:', e.message || e);
    await page.waitForTimeout(60_000);
    process.exitCode = 1;
} finally {
    await browser.close();
}
