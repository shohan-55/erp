import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import { createHmac } from 'node:crypto';
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

function authenticatorCode(base32Secret) {
    const chars='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits='';
    for(const symbol of base32Secret.replace(/\\s/g,'').toUpperCase()) {
        const index=chars.indexOf(symbol);
        assert.ok(index>=0,'Invalid test authenticator key');
        bits+=index.toString(2).padStart(5,'0');
    }
    const bytes=[];
    for(let i=0;i+8<=bits.length;i+=8) bytes.push(parseInt(bits.slice(i,i+8),2));
    const counter=Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(Date.now()/30000)));
    const hmac=createHmac('sha1',Buffer.from(bytes)).update(counter).digest();
    const offset=hmac[19]&15;
    const value=(hmac.readUInt32BE(offset)&0x7fffffff)%1000000;
    return String(value).padStart(6,'0');
}

async function login(email, platform=false) {
    const path=platform?'/platform/app/login':'/c/rac/app/login';
    const response=await page.goto(baseUrl+path,{waitUntil:'networkidle'});
    assert.equal(response.status(),200);
    await page.locator('input[name=email]').fill(email);
    await page.locator('input[name=password]').fill(password);
    await page.locator('button[type=submit]').click();
    await page.waitForLoadState('networkidle');

    if (new URL(page.url()).pathname === '/security/mfa/setup') {
        // The real partner/platform login now requires authenticator MFA.
        // Only fictional CI QA users are enrolled, in an isolated database.
        await page.locator('#mfa-setup-qr svg').waitFor({state:'visible',timeout:8000});
        const qrData=await page.locator('#mfa-setup-qr').getAttribute('data-otp-uri');
        assert.ok(qrData?.startsWith('otpauth://totp/') && qrData.includes('secret='), 'Authenticator QR must encode a TOTP provisioning URI.');
        const secret=(await page.locator('p.select-all').textContent()).trim();
        await page.locator('input[name=password]').fill(password);
        await page.locator('input[name=code]').fill(authenticatorCode(secret));
        await page.getByRole('button',{name:'Enable two-step verification'}).click();
        await page.waitForURL('**/security/mfa/recovery');
        await page.getByRole('button',{name:'I saved my recovery codes'}).click();
    }
    await page.waitForURL(platform?'**/platform/app':'**/c/rac/app');
}

async function nav(text) {
    await page.getByRole('link',{name:text,exact:true}).click();
}

try {
    // This browser suite uses an isolated temporary database seeded with fake QA users.
    await login('e2e.student@example.test');
    await page.getByRole('heading',{name:'Workspace Dashboard'}).waitFor();
    const attendance = page.locator('.attendance-spotlight');
    await attendance.waitFor({state:'visible'});
    assert.equal(await attendance.evaluate(el => getComputedStyle(el).backgroundImage.includes('gradient')), true, 'Premium attendance gradients must be applied, not unstyled HTML');
    const liveClock = page.locator('[data-attendance-clock]');
    await liveClock.waitFor();
    await page.waitForFunction(() => {
        const clock = document.querySelector('[data-attendance-clock]');
        const status = clock?.querySelector('[data-attendance-clock-status]')?.textContent;
        return clock?.dataset.attendanceStage && status && status !== 'Schedule loading';
    });
    const initialTime = await page.locator('[data-attendance-clock-time]').textContent();
    assert.match(initialTime, /^\d{2}:\d{2}:\d{2}$/);
    // Browser CI can be CPU-throttled. Wait for a real time change instead
    // of assuming one will occur in exactly 1.1 seconds.
    await page.waitForFunction(previous =>
        document.querySelector('[data-attendance-clock-time]')?.textContent !== previous,
        initialTime,{timeout:7000});
    assert.notEqual(await page.locator('[data-attendance-clock-time]').textContent(),
        initialTime,'Digital clock should tick');
    const clockResponse = await page.request.get(baseUrl+'/c/rac/app/attendance/clock');
    assert.equal(clockResponse.status(),200,'Authenticated clock sync must return the server schedule');
    assert.equal((await clockResponse.json()).clock.timezone,'Asia/Dhaka');

    // Personalized work pulse and saved shortcuts remain within the student's
    // existing authorized routes; no secret or salary data is stored in browser.
    const pulse = page.locator('.erp-work-pulse');
    await pulse.waitFor();
    await pulse.getByText('My leave requests').waitFor();
    const finder = page.locator('[data-corporate-action-center]');
    await finder.waitFor();
    const quickSearch = finder.locator('[data-corporate-action-search]');
    await quickSearch.fill('leave');
    const filteredCards = finder.locator('[data-corporate-action]:visible');
    assert.equal(await filteredCards.count() >= 1, true, 'Search should display matching authorized actions');
    await filteredCards.first().locator('[data-corporate-pin]').click();
    await finder.getByRole('button',{name:'Pinned',exact:true}).click();
    assert.equal(await filteredCards.count(),1,'Pinned shortcuts must be available in one click');
    await finder.getByRole('button',{name:'All',exact:true}).click();
    await quickSearch.fill('');
    assert.equal(await filteredCards.count()>=4,true,'Clearing search must restore authorized shortcuts');
    const allMonths = page.getByRole('link',{name:'Explore all months ↗'});
    await allMonths.click();
    await page.getByRole('heading',{name:'Workforce Calendar'}).waitFor();
    await page.getByText('Jump to month').waitFor();
    const csv = await page.request.get(page.url().replace('/calendar','/calendar/export'));
    assert.equal(csv.status(),200,'Authorized personal calendar CSV must download');
    assert.match(csv.headers()['content-type']||'',/text\/csv/);
    await page.goBack({waitUntil:'networkidle'});

    // Dynamic watermark must be behind readable day cells; clicking a date opens
    // a read-only, keyboard-accessible detail dialog.
    const calendar=page.locator('.erp-month-calendar');
    await calendar.waitFor({state:'visible'});
    assert.equal(await calendar.locator('.erp-month-calendar__day').count()>=28,true,'Current month day cells must render');
    assert.equal(await calendar.locator('.erp-month-calendar__watermark').count(),1,'Company watermark should render');
    assert.equal(await calendar.evaluate(el=>getComputedStyle(el).backgroundImage.includes('gradient')),true,'Calendar should be premium-styled');
    await calendar.locator('[data-calendar-day]').first().click();
    await page.locator('[data-calendar-dialog][open]').waitFor();
    assert.match(await page.locator('[data-calendar-modal-date]').textContent(),/^\d{4}-\d{2}-\d{2}$/);
    await page.getByRole('button',{name:'Done',exact:true}).click();
    assert.equal(await page.locator('[data-calendar-dialog][open]').count(),0,'Calendar dialog must close');


    assert.equal(await page.evaluate(() =>
        document.querySelector('.attendance-spotlight').getBoundingClientRect().top <
        document.querySelector('section[aria-label="Quick management actions"]').getBoundingClientRect().top
    ), true, 'Attendance must appear above the long action grid');
    await page.screenshot({path:output+'/01-student-dashboard.png',fullPage:true});

    // The premium Dashboard button must NOT submit before explicit confirmation.
    // A Cancel must not cause a POST to reach the attendance endpoint.
    let checkInSubmissions = 0;
    page.on('request', request => {
        if (request.method() === 'POST' && new URL(request.url()).pathname.endsWith('/attendance/check-in')) {
            checkInSubmissions++;
        }
    });
    await page.getByRole('button',{name:'Check In Now'}).click();
    await page.getByRole('dialog',{name:'Ready to check in?'}).waitFor();
    assert.equal(checkInSubmissions, 0, 'Opening confirmation must not record attendance');
    await page.getByRole('button',{name:'Cancel'}).click();
    await page.waitForTimeout(80);
    assert.equal(checkInSubmissions, 0, 'Cancelling attendance must not submit a POST');
    await page.getByRole('button',{name:'Check In Now'}).click();
    const checkInResponse = page.waitForResponse(response =>
        response.request().method()==='POST'
        && new URL(response.url()).pathname.endsWith('/attendance/check-in'),
        {timeout:15000});
    await page.getByRole('button',{name:'OK, Check In →'}).click();
    const checkInResult=await checkInResponse;
    assert.ok(checkInResult.status()<400,
        'Attendance check-in request must succeed; HTTP '+checkInResult.status());
    await page.getByRole('alertdialog').waitFor({timeout:15000});
    assert.equal(checkInSubmissions, 1, 'Exactly one check-in POST after OK');
    await page.getByRole('button',{name:/Continue to dashboard/}).click();
    await page.getByRole('button',{name:'Check Out Now'}).click();
    await page.getByRole('dialog',{name:'Finish your work session?'}).waitFor();
    await page.getByRole('button',{name:'OK, Check Out →'}).click();
    await page.getByRole('alertdialog').waitFor();
    await page.getByRole('button',{name:/Continue to dashboard/}).click();
    await nav('Attendance');
    await page.getByText('Attendance History (last 100 sessions)').waitFor();
    await page.screenshot({path:output+'/02-student-attendance.png',fullPage:true});

    await nav('Leave Requests');
    await page.locator('select[name=leave_type]').selectOption('annual');
    await page.locator('input[name=date_from]').fill('2026-11-18');
    await page.locator('input[name=date_to]').fill('2026-11-19');
    await page.locator('textarea[name=reason]').fill('Sample QA leave application only');
    await page.getByRole('button',{name:'Submit Leave Request'}).click();
    await page.getByText('Leave request submitted for review.').waitFor();
    const leaveFilter = page.locator('[data-corporate-history-toolbar][data-history-target="leave"]');
    await leaveFilter.waitFor();
    await leaveFilter.locator('[data-history-search]').fill('Sample QA leave application only');
    assert.equal(await page.locator('[data-corporate-history-group="leave"] [data-corporate-history-item]:visible').count(),1,'Student can find their own submitted leave');
    await leaveFilter.locator('[data-history-status]').selectOption('approved');
    assert.equal(await page.locator('[data-corporate-history-group="leave"] [data-corporate-history-item]:visible').count(),0,'Status filter hides non-matching records');
    await leaveFilter.locator('[data-history-reset]').click();
    assert.equal(await page.locator('[data-corporate-history-group="leave"] [data-corporate-history-item]:visible').count()>=1,true,'Reset restores records');
    await page.screenshot({path:output+'/03-student-leave.png',fullPage:true});

    await page.getByRole('button',{name:'Sign Out'}).click();

    // Assigned students now require Manager review before final Partner approval.
    await login('e2e.manager@example.test');
    await page.getByRole('button',{name:'Check In Now'}).click();
    await page.getByRole('dialog',{name:'Ready to check in?'}).waitFor();
    await page.getByRole('button',{name:'OK, Check In →'}).click();
    await page.getByRole('alertdialog').waitFor();
    await page.getByRole('button',{name:/Continue to dashboard/}).click();
    await page.getByRole('button',{name:'Check Out Now'}).click();
    await page.getByRole('button',{name:'OK, Check Out →'}).click();
    await page.getByRole('alertdialog').waitFor();
    await page.getByRole('button',{name:/Continue to dashboard/}).click();
    await nav('Leave Requests');
    let leaveDecisionSubmissions=0;
    page.on('request',r=>{if(r.method()==='POST'&&/\/leave\/\d+\/decision$/.test(new URL(r.url()).pathname))leaveDecisionSubmissions++;});
    await page.getByRole('button',{name:'Review',exact:true}).first().click();
    await page.locator('[data-corporate-confirm-dialog][open]').waitFor();
    assert.equal(leaveDecisionSubmissions,0,'Manager review must await explicit confirmation');
    await page.locator('[data-corporate-cancel]').click();
    assert.equal(leaveDecisionSubmissions,0,'Cancelling review sends no approval POST');
    await page.getByRole('button',{name:'Review',exact:true}).first().click();
    await page.locator('[data-corporate-confirm-go]').click();
    await page.getByText('Leave review recorded.').waitFor();
    assert.equal(leaveDecisionSubmissions,1,'Manager review submits exactly once after confirmation');
    await page.getByRole('button',{name:'Sign Out'}).click();

    await login('e2e.partner@example.test');
    await page.getByRole('heading',{name:'Workspace Dashboard'}).waitFor();
    await page.screenshot({path:output+'/04-partner-dashboard.png',fullPage:true});

    // Workspace MFA is opt-in; Security Settings must offer a private, local QR
    // enrollment without blocking a password-only Partner from normal work.
    await nav('Settings / Security');
    await page.getByRole('heading',{name:'Security Settings'}).waitFor();
    await page.getByText('Recommended · Off').waitFor();
    await page.getByRole('link',{name:'Enable Two-Step Verification'}).click();
    await page.locator('#mfa-setup-qr svg').waitFor({state:'visible'});
    await page.getByRole('link',{name:/Not now/}).click();
    await page.getByRole('heading',{name:'Security Settings'}).waitFor();
    await nav('Dashboard');

    await nav('Leave Requests');
    await page.locator('button[name=decision][value=approve]').first().click();
    await page.locator('[data-corporate-confirm-dialog][open]').waitFor();
    await page.locator('[data-corporate-confirm-go]').click();
    await page.getByText('Leave review recorded.').waitFor();

    // Confirm that Partner-approved future student leave is highlighted on the
    // actual selected month and contains the authorized letter link.
    await page.goto(baseUrl+'/c/rac/app/calendar?month=2026-11',{waitUntil:'networkidle'});
    await page.locator('.erp-month-calendar__title').getByText('November 2026').waitFor();
    assert.equal(await page.locator('[data-calendar-day]').count(),30,
        'November must show 30 days, not the current October month length');
    const approvedTeamDay=page.locator('[data-calendar-day][data-date="2026-11-18"]');
    assert.match(await approvedTeamDay.getAttribute('class'),/team_leave/,
        'Final Partner approval must show approved team leave on future date');
    await approvedTeamDay.click();
    await page.locator('[data-calendar-dialog][open]').waitFor();
    await page.locator('[data-calendar-modal-leaves]').getByText('Approved',{exact:true}).waitFor();
    await page.locator('[data-calendar-modal-leaves]').getByRole('link',{name:/View approved letter/}).waitFor();
    await page.getByRole('button',{name:'Done',exact:true}).click();
    await page.goto(baseUrl+'/c/rac/app',{waitUntil:'networkidle'});

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
    await page.waitForFunction(() => {
        const drawer = document.querySelector('aside');
        return drawer && drawer.getBoundingClientRect().right <= 1;
    }, null, {timeout:5000});
    await page.screenshot({path:output+'/09-student-mobile.png',fullPage:true});
    assert.equal(await page.locator('.attendance-spotlight').evaluate(el => getComputedStyle(el).borderRadius !== '0px'), true, 'Premium card must retain mobile styling');
    await page.locator('[data-attendance-clock-time]').waitFor();
    assert.equal(await page.locator('[data-attendance-clock]').evaluate(el=>el.getBoundingClientRect().width <= window.innerWidth),true,'Mobile digital clock must not overflow');
    assert.equal(await page.locator('.erp-month-calendar').evaluate(el=>el.getBoundingClientRect().width<=window.innerWidth),true,'Premium calendar must fit mobile');

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