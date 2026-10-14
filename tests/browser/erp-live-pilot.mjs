import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import { chromium } from 'playwright';

const baseUrl = process.env.PREVIEW_URL || 'http://127.0.0.1:8000';
const password = process.env.ERP_E2E_PASSWORD;
assert.ok(password && password.length >= 16, 'The CI test-only password must be set.');
const output = process.env.SCREENSHOT_DIR || 'screenshots/erp-live-pilot';
await fs.mkdir(output, {recursive:true});
const browser = await chromium.launch({headless:true});
const page = await browser.newPage({viewport:{width:1440,height:900}});
const errors=[];
page.on('pageerror', err=>errors.push(err.message));

async function login(email, platform=false) {
    const path=platform?'/platform/app/login':'/c/rac/app/login';
    const response=await page.goto(baseUrl+path,{waitUntil:'networkidle'});
    assert.equal(response.status(),200);
    await page.locator('input[name=email]').fill(email);
    await page.locator('input[name=password]').fill(password);
    await page.locator('button[type=submit]').click();
    await page.waitForURL(platform?'**/platform/app':'**/c/rac/app');
}

async function nav(text) {
    await page.getByRole('link',{name:text,exact:true}).click();
}

try {
    // This browser suite uses an isolated temporary database seeded with fake QA users.
    await login('e2e.student@example.test');
    await page.getByRole('heading',{name:'Workspace Dashboard'}).waitFor();
    await page.screenshot({path:output+'/01-student-dashboard.png',fullPage:true});

    await nav('Attendance');
    await page.getByRole('button',{name:'Check In',exact:true}).click();
    await page.getByText('Check-in saved using the server clock.').waitFor();
    await page.screenshot({path:output+'/02-student-attendance.png',fullPage:true});
    await page.getByRole('button',{name:'Check Out',exact:true}).click();
    await page.getByText('Check-out recorded.').waitFor();

    await nav('Leave Requests');
    await page.locator('select[name=leave_type]').selectOption('annual');
    await page.locator('input[name=date_from]').fill('2026-11-18');
    await page.locator('input[name=date_to]').fill('2026-11-19');
    await page.locator('textarea[name=reason]').fill('Sample QA leave application only');
    await page.getByRole('button',{name:'Submit Leave Request'}).click();
    await page.getByText('Leave request submitted for review.').waitFor();
    await page.screenshot({path:output+'/03-student-leave.png',fullPage:true});

    await page.getByRole('button',{name:'Sign Out'}).click();
    await login('e2e.partner@example.test');
    await page.getByRole('heading',{name:'Workspace Dashboard'}).waitFor();
    await page.screenshot({path:output+'/04-partner-dashboard.png',fullPage:true});

    await nav('Leave Requests');
    await page.locator('button[name=decision][value=approve]').first().click();
    await page.getByText('Leave review recorded.').waitFor();

    await nav('Team & Accounts');
    await page.getByText('QA Manager', {exact:true}).first().waitFor();
    await page.screenshot({path:output+'/05-team-management.png',fullPage:true});

    await nav('Policy & Governance');
    await page.getByRole('heading',{name:'Versioned Policies & Approvals'}).waitFor();
    await page.screenshot({path:output+'/06-policy-governance.png',fullPage:true});

    await nav('Payroll');
    const month=new Date().toISOString().slice(0,7);
    await page.locator('input[name=month]').fill(month);
    await page.getByRole('button',{name:'Generate Draft'}).click();
    await page.getByText('Payroll draft generated. Review amounts before approval.').waitFor();
    await page.screenshot({path:output+'/07-payroll-draft.png',fullPage:true});

    await page.getByRole('button',{name:'Sign Out'}).click();

    await login('e2e.platform@example.test',true);
    await page.getByRole('heading',{name:'Organization Management'}).waitFor();
    await page.getByText('Rahman Anis & Co.').waitFor();
    await page.screenshot({path:output+'/08-platform-admin.png',fullPage:true});

    // Mobile QA for the ACTUAL authenticated student interface, not the old fake preview.
    await page.getByRole('button',{name:'Sign Out'}).click();
    await page.setViewportSize({width:390,height:844});
    await login('e2e.student@example.test');
    await page.screenshot({path:output+'/09-student-mobile.png',fullPage:true});
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+2),false,'390px should not overflow');

    await page.getByRole('button',{name:'Open navigation'}).click();
    await page.waitForFunction(()=>{
        const el=document.querySelector('aside');
        return el && el.getBoundingClientRect().left>=-1;
    },null,{timeout:5000});
    await page.waitForTimeout(250);
    await page.screenshot({path:output+'/10-student-mobile-menu.png',fullPage:false});

    await page.setViewportSize({width:375,height:667});
    await page.goto(baseUrl+'/c/rac/app/payroll',{waitUntil:'networkidle'});
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+2),false,'375px should not overflow');
    await page.screenshot({path:output+'/11-payroll-mobile.png',fullPage:true});
    assert.deepEqual(errors,[],'Uncaught browser JS errors should be empty');
    console.log('PASS Real ERP authenticated browser QA: login, student attendance, leave, partner review, team, policies, payroll, platform operator, mobile.');
} finally {
    await browser.close();
}