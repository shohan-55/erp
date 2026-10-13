import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import { chromium } from 'playwright';

const baseUrl = process.env.PREVIEW_URL || 'http://127.0.0.1:8000';
const outputDir = process.env.SCREENSHOT_DIR || 'screenshots/practice-phases';
await fs.mkdir(outputDir, { recursive: true });

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
const errors = [];

page.on('pageerror', err => errors.push(err.message));

async function visit(path) {
    const result = await page.goto(baseUrl + path, { waitUntil: 'networkidle' });
    assert.equal(result.status(), 200, path + ' should return HTTP 200');
    await page.getByText('Design Prototype · No Live Data.', { exact: false }).waitFor();
}

try {
    await visit('/c/rac/preview/partner/dashboard');
    await page.getByRole('heading', { name: 'Your practice, in focus.' }).waitFor();
    await page.screenshot({ path: outputDir + '/01-partner-desktop.png', fullPage: true });

    await page.getByRole('button', { name: 'Attendance', exact: true }).click();
    await page.getByRole('heading', { name: 'Office & Client Attendance' }).waitFor();
    await page.getByRole('button', { name: /Correction Review/ }).click();
    await page.getByRole('dialog', { name: 'Attendance Correction Review' }).waitFor();
    await page.getByRole('button', { name: 'Acknowledge Preview' }).click();
    await page.getByText('No attendance record was changed.').waitFor();

    await page.getByRole('button', { name: 'Leave Requests' }).click();
    await page.getByRole('heading', { name: 'Leave Requests' }).waitFor();
    await page.getByRole('button', { name: /Review Leave/ }).click();
    await page.getByRole('dialog', { name: 'Leave Application Review' }).waitFor();
    await page.getByRole('button', { name: 'Acknowledge Preview' }).click();

    await page.getByRole('button', { name: 'Conveyance Claims' }).click();
    await page.getByRole('heading', { name: 'Travel & Conveyance' }).waitFor();
    await page.screenshot({ path: outputDir + '/02-conveyance-partner-desktop.png', fullPage: true });

    await page.getByRole('button', { name: 'Payroll & Allowance' }).click();
    await page.getByRole('heading', { name: 'Payroll Workspace' }).waitFor();
    await page.getByText('NOT A PAYSLIP').waitFor();
    await page.screenshot({ path: outputDir + '/03-payroll-desktop.png', fullPage: true });

    await page.getByRole('button', { name: 'Policy & Governance' }).click();
    await page.getByText('All 3 Partners Vote').waitFor();
    await page.screenshot({ path: outputDir + '/04-governance-desktop.png', fullPage: true });
    await page.getByRole('button', { name: /Change Proposal/ }).click();
    await page.getByRole('dialog', { name: 'Company Policy Proposal' }).waitFor();
    await page.getByRole('button', { name: 'Acknowledge Preview' }).click();

    await visit('/c/rac/preview/manager/dashboard');
    await page.getByRole('heading', { name: 'Your team, aligned.' }).waitFor();
    await page.getByText('Sample Student 01').waitFor();
    assert.equal(await page.getByText('Sample Student 03').count(), 0, 'Manager must not see other demo manager team');
    await page.screenshot({ path: outputDir + '/05-manager-desktop.png', fullPage: true });

    await visit('/c/rac/preview/student/dashboard');
    await page.getByRole('heading', { name: 'Your day, organized.' }).waitFor();
    await page.screenshot({ path: outputDir + '/06-student-desktop.png', fullPage: true });

    await page.getByRole('button', { name: 'Attendance', exact: true }).click();
    await page.getByRole('heading', { name: "Today's Check-in Preview" }).waitFor();
    await page.getByRole('button', { name: 'Simulate Check-in' }).click();
    await page.getByText('No timestamp, geolocation or attendance record was saved.').waitFor();
    await page.getByRole('button', { name: 'Simulate Check-out' }).click();

    await page.getByRole('button', { name: 'Leave Requests' }).click();
    await page.getByRole('heading', { name: 'New Leave Application' }).waitFor();
    await page.getByRole('button', { name: 'Validate Sample Application' }).click();
    await page.getByText('Enter a leave type, valid start and end dates').waitFor();

    await page.getByRole('button', { name: 'Conveyance Claims' }).click();
    await page.getByRole('heading', { name: 'New Conveyance Claim' }).waitFor();
    await page.getByRole('button', { name: 'Validate Demo Claim' }).click();
    await page.getByText('Enter a valid date, amount').waitFor();

    await page.getByRole('button', { name: 'Notifications', exact: true }).click();
    await page.getByRole('heading', { name: 'Notification Inbox' }).waitFor();
    await page.getByRole('button', { name: 'Mark Read' }).first().click();
    await page.getByText('Read status updated in this temporary browser preview only.').waitFor();

    await page.setViewportSize({ width: 390, height: 844 });
    await visit('/c/rac/preview/student/dashboard');
    await page.screenshot({ path: outputDir + '/07-student-mobile.png', fullPage: true });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2), false, '390px horizontal overflow');

    await page.getByRole('button', { name: 'Open navigation' }).click();
    await page.waitForFunction(() => {
        const el = document.querySelector('aside[aria-label="RA&C practice navigation"]');
        return el && el.getBoundingClientRect().left >= -1;
    }, null, {timeout: 5000});
    await page.waitForTimeout(250);
    await page.screenshot({ path: outputDir + '/08-student-mobile-menu.png', fullPage: false });
    await page.getByRole('button', { name: 'Attendance', exact: true }).click();
    await page.getByRole('heading', { name: 'Office & Client Attendance' }).waitFor();
    await page.screenshot({ path: outputDir + '/09-attendance-mobile.png', fullPage: true });

    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto(baseUrl + '/c/rac/preview/student/payroll', {waitUntil: 'networkidle'});
    await page.getByRole('heading', { name: 'Payroll Workspace' }).waitFor();
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2), false, '375px horizontal overflow');
    await page.screenshot({ path: outputDir + '/10-payroll-small-mobile.png', fullPage: true });

    assert.deepEqual(errors, [], 'Uncaught JS browser errors');
    console.log('PASS Practice suite browser QA: roles 04-06, modules 07-10, dialogs, validations, notifications, and 390/375px mobile.');
} finally {
    await browser.close();
}