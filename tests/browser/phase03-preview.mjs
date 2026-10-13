import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import { chromium } from 'playwright';

const baseUrl = process.env.PREVIEW_URL || 'http://127.0.0.1:8000';
const outputDir = process.env.SCREENSHOT_DIR || 'screenshots/phase-03';
await fs.mkdir(outputDir, { recursive: true });

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const errors = [];

page.on('pageerror', error => errors.push(error.message));

try {
    const response = await page.goto(baseUrl + '/platform/dashboard-preview', { waitUntil: 'networkidle' });
    assert.equal(response.status(), 200, 'Dashboard preview should return HTTP 200');
    await page.getByRole('heading', { name: 'One platform. Clear boundaries.' }).waitFor();
    await page.getByText('Design Prototype — No Live Access.', { exact: false }).waitFor();
    await page.screenshot({ path: outputDir + '/01-overview-desktop.png', fullPage: true });

    await page.getByRole('button', { name: /New Organization/ }).click();
    await page.getByRole('dialog', { name: 'Create an organization' }).waitFor();
    await page.getByRole('button', { name: 'Acknowledge Preview' }).click();
    await page.getByText('No organization was created or provisioned.').waitFor();

    await page.getByRole('button', { name: 'Organizations' }).click();
    await page.getByPlaceholder('Search firm name or slug...').waitFor();
    await page.getByPlaceholder('Search firm name or slug...').fill('alpha');
    await page.waitForFunction(() => {
        const rows = [...document.querySelectorAll('tbody tr')];
        return rows.length === 1 && rows[0].textContent.includes('Sample Practice Alpha');
    });
    await page.screenshot({ path: outputDir + '/02-organizations-filtered-desktop.png', fullPage: true });

    await page.getByRole('button', { name: 'Support Access' }).click();
    await page.getByRole('heading', { name: 'Access requires permission.' }).waitFor();
    await page.screenshot({ path: outputDir + '/03-support-governance-desktop.png', fullPage: true });

    await page.getByRole('button', { name: /Request Access/ }).click();
    await page.getByRole('dialog', { name: 'Read-only support request' }).waitFor();
    await page.getByRole('button', { name: 'Acknowledge Preview' }).click();
    await page.getByText('No support access was granted, requested, or logged.').waitFor();

    await page.getByRole('button', { name: 'Platform Status' }).click();
    await page.getByRole('heading', { name: 'Platform Status', exact: true }).last().waitFor();
    await page.screenshot({ path: outputDir + '/04-platform-status-desktop.png', fullPage: true });

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(baseUrl + '/platform/dashboard-preview', { waitUntil: 'networkidle' });
    await page.screenshot({ path: outputDir + '/05-overview-mobile.png', fullPage: true });

    const horizontalOverflow = await page.evaluate(() =>
        document.documentElement.scrollWidth > window.innerWidth + 2
    );
    assert.equal(horizontalOverflow, false, 'Mobile viewport should not have horizontal page overflow');

    await page.getByRole('button', { name: 'Open navigation' }).click();
    await page.waitForFunction(() => {
        const drawer = document.querySelector('aside[aria-label="Platform preview navigation"]');
        return drawer && drawer.getBoundingClientRect().left >= -1;
    }, null, { timeout: 5000 });
    await page.waitForTimeout(250);
    await page.screenshot({ path: outputDir + '/06-mobile-navigation.png', fullPage: false });

    await page.getByRole('button', { name: 'Organizations' }).click();
    await page.getByPlaceholder('Search firm name or slug...').waitFor();
    await page.screenshot({ path: outputDir + '/07-organizations-mobile.png', fullPage: true });

    assert.deepEqual(errors, [], 'Browser should have no uncaught JavaScript errors');
    console.log('PASS Phase 03 browser smoke test: overview, filters, modals, privacy previews, mobile and navigation.');
} finally {
    await browser.close();
}
