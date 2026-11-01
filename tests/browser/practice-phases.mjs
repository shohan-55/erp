import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import { chromium } from 'playwright';

const baseUrl = process.env.PREVIEW_URL || 'http://127.0.0.1:8000';
const outputDir = process.env.SCREENSHOT_DIR || 'screenshots/practice-phases';
await fs.mkdir(outputDir, { recursive: true });

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const errors = [];
page.on('pageerror', error => errors.push(error.message));

const routes = [
    ['/c/rac/preview/partner/dashboard', 'Overview', '01-partner-overview-desktop.png'],
    ['/c/rac/preview/partner/attendance', 'Attendance', '02-partner-attendance-desktop.png'],
    ['/c/rac/preview/partner/leave', 'Leave Management', '03-partner-leave-desktop.png'],
    ['/c/rac/preview/partner/conveyance', 'Conveyance Claims', '04-partner-conveyance-desktop.png'],
    ['/c/rac/preview/partner/payroll', 'Monthly Allowance & Payroll', '05-partner-payroll-desktop.png'],
    ['/c/rac/preview/partner/policies', 'Policy & Governance', '06-partner-governance-desktop.png'],
    ['/c/rac/preview/partner/settings', 'Workspace Settings', '07-partner-settings-desktop.png'],
    ['/c/rac/preview/manager/dashboard', 'Overview', '08-manager-overview-desktop.png'],
    ['/c/rac/preview/student/dashboard', 'Overview', '09-student-overview-desktop.png'],
    ['/c/rac/preview/student/attendance', 'Attendance', '10-student-attendance-desktop.png'],
    ['/c/rac/preview/student/notifications', 'Notifications', '11-student-notifications-desktop.png'],
];

try {
    for (const [path, heading, screenshot] of routes) {
        const response = await page.goto(baseUrl + path, { waitUntil: 'networkidle' });
        assert.equal(response.status(), 200, `${path} should return HTTP 200`);
        await page.getByRole('heading', { name: heading, exact: true }).first().waitFor();
        await page.getByText('Powered by DhakaWala ERP', { exact: false }).first().waitFor();
        await page.screenshot({ path: `${outputDir}/${screenshot}`, fullPage: true });
    }

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(baseUrl + '/c/rac/preview/student/dashboard', { waitUntil: 'networkidle' });
    await page.getByRole('heading', { name: 'Overview', exact: true }).first().waitFor();
    await page.screenshot({ path: `${outputDir}/12-student-mobile.png`, fullPage: true });
    assert.equal(
        await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2),
        false,
        '390px practice viewport should not overflow horizontally'
    );

    await page.getByRole('button', { name: 'Open navigation' }).click();
    await page.waitForFunction(() => {
        const sidebar = document.querySelector('aside[aria-label="Practice navigation"]');
        return sidebar && sidebar.getBoundingClientRect().left >= -1;
    }, null, { timeout: 5000 });
    await page.screenshot({ path: `${outputDir}/13-student-mobile-navigation.png`, fullPage: false });

    assert.deepEqual(errors, [], 'Practice preview should have no uncaught JavaScript errors');
    console.log('PASS practice browser smoke test: partner, manager and student routes, core modules, and mobile navigation.');
} finally {
    await browser.close();
}
