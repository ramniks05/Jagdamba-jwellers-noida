import { chromium } from 'playwright';
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const php = process.env.PHP_BIN || 'C:\\Users\\ganes\\tools\\php83\\php.exe';
const base = process.env.APP_URL || 'http://127.0.0.1:8000';

const config = JSON.parse(
    execSync(`"${php}" "${path.join(root, 'scripts', 'prepare-e2e-bills.php')}"`, { encoding: 'utf8' }),
);

const errors = [];

function assert(condition, message) {
    if (!condition) errors.push(message);
}

async function login(page) {
    await page.goto(`${base}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('#email', config.email);
    await page.fill('#password', config.password);
    await page.click('button[type="submit"]');
    await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 15000 });
}

async function pickCustomer(page) {
    const needle = config.customer_mobile || config.customer_name?.slice(0, 4) || '9';
    await page.fill('#customer-search', needle);
    await page.waitForSelector('#customer-results button', { timeout: 10000 });
    await page.locator('#customer-results button').first().click();
    await page.waitForSelector('#customer-chosen:not(.d-none)', { timeout: 5000 });
}

async function readTotal(page) {
    const text = await page.locator('#bill-total').innerText();
    return Number(String(text).replace(/[^\d.]/g, '')) || 0;
}

async function closePreviewModal(page) {
    const modal = page.locator('#bill-preview-modal');
    if (await modal.evaluate((el) => el.classList.contains('show')).catch(() => false)) {
        await page.locator('#bill-preview-modal [data-bs-dismiss="modal"]').first().click();
        await modal.waitFor({ state: 'hidden', timeout: 5000 }).catch(() => {});
    }
}

async function waitForPreviewBody(page) {
    await page.waitForFunction(() => {
        const body = document.querySelector('#bill-preview-body');
        const text = body?.textContent?.trim() || '';
        return text.length > 0 && !text.includes('Making the preview');
    }, undefined, { timeout: 20000 });
    const preview = await page.locator('#bill-preview-body').innerText();
    if (/Fix these before saving|could not be made/i.test(preview)) {
        throw new Error(`Preview failed: ${preview.slice(0, 400)}`);
    }
    return preview;
}

async function testJewelleryBill(page) {
    await page.goto(`${base}/sales/create`, { waitUntil: 'domcontentloaded' });
    await pickCustomer(page);

    if (!config.scan_code) {
        errors.push('Jewellery bill: no available tagged piece in stock (scan code missing).');
        return;
    }

    await page.fill('#scan-code', config.scan_code);
    await page.press('#scan-code', 'Enter');

    try {
        await page.waitForFunction(() => {
            const total = Number(String(document.querySelector('#bill-total')?.textContent || '').replace(/[^\d.]/g, '')) || 0;
            return total > 0;
        }, undefined, { timeout: 20000 });
    } catch {
        const scanNote = await page.locator('#scan-status').innerText().catch(() => '');
        errors.push(`Jewellery bill: scan "${config.scan_code}" did not add a line. Scan message: ${scanNote || '(empty)'}`);
        return;
    }

    const total = await readTotal(page);
    assert(total > 0, `Jewellery bill: total should be > 0 after scan, got ${total}`);

    await page.click('#bill-preview');
    await page.waitForSelector('#bill-preview-modal.show', { timeout: 10000 });
    const preview = await waitForPreviewBody(page);
    assert(
        preview.length > 150 || /invoice|total|gst/i.test(preview),
        `Jewellery bill: preview should show invoice content (got ${preview.length} chars).`,
    );
    await closePreviewModal(page);
}

async function testOtherItemBill(page) {
    if (!config.has_retail_tables) {
        errors.push('Other item bill: retail tables missing — run php artisan migrate.');
        return;
    }

    await page.goto(`${base}/sales/create`, { waitUntil: 'domcontentloaded' });
    await pickCustomer(page);

    const retailAdd = page.locator('#retail-add');
    if (!(await retailAdd.count())) {
        errors.push('Other item bill: #retail-add not on page (retail disabled or cached view).');
        return;
    }

    await page.locator('#retail-name').scrollIntoViewIfNeeded();
    await page.fill('#retail-name', 'E2E murti');
    await page.fill('#retail-price', '750');
    await page.locator('#retail-price').dispatchEvent('input');
    await retailAdd.click();

    try {
        await page.waitForFunction(() => {
            const total = Number(String(document.querySelector('#bill-total')?.textContent || '').replace(/[^\d.]/g, '')) || 0;
            return total >= 750;
        }, undefined, { timeout: 15000 });
    } catch {
        const retailErr = await page.locator('#retail-error').innerText().catch(() => '');
        const lines = await page.locator('#bill-lines').innerText().catch(() => '');
        const totalText = await page.locator('#bill-total').innerText().catch(() => '');
        errors.push(
            `Other item bill: add did not update total (total="${totalText}", retail-error="${retailErr.trim()}", lines="${lines.slice(0, 120)}").`,
        );
        return;
    }

    const total = await readTotal(page);
    assert(total >= 750, `Other item bill: total should be at least 750, got ${total}`);

    const retailRow = await page.locator('#bill-retail-row').isVisible();
    assert(retailRow, 'Other item bill: billing details should show Other items row.');

    await page.click('#bill-preview');
    await page.waitForSelector('#bill-preview-modal.show', { timeout: 10000 });
    const preview = await waitForPreviewBody(page);
    assert(/murti/i.test(preview) && /750/.test(preview), 'Other item bill: preview should show item and amount.');
    await closePreviewModal(page);
}

let browser;
for (const channel of ['msedge', 'chrome', undefined]) {
    try {
        browser = await chromium.launch(channel ? { headless: true, channel } : { headless: true });
        break;
    } catch {
        // try next browser
    }
}
if (!browser) {
    console.error('E2E bill tests FAILED: no Chromium/Edge/Chrome available for Playwright.');
    process.exit(1);
}
const context = await browser.newContext();
const page = await context.newPage();

try {
    await login(page);
    await testJewelleryBill(page);
    await testOtherItemBill(page);
} catch (error) {
    errors.push(String(error.message || error));
} finally {
    await browser.close();
}

if (errors.length) {
    console.error('E2E bill tests FAILED:\n- ' + errors.join('\n- '));
    process.exit(1);
}

console.log('E2E bill tests passed: jewellery (scan) bill and other-item bill.');
