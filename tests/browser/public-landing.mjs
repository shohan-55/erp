import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import { chromium } from 'playwright';

const file=process.env.LANDING_RENDERED_HTML || '/tmp/erp-public-landing-preview.html';
const html=await fs.readFile(file,'utf8');
assert.ok(html.includes('Less admin.'),'Landing must render from Laravel Blade');
const out='screenshots/public-landing';
await fs.mkdir(out,{recursive:true});

const browser=await chromium.launch({headless:true});
const errors=[];
try{
  const page=await browser.newPage({viewport:{width:1440,height:900},deviceScaleFactor:1});
  page.on('pageerror',e=>errors.push(e.message));
  await page.setContent(html,{waitUntil:'load'});
  await page.getByRole('heading',{name:/Less admin.*More momentum.*Every day/s}).waitFor({state:'visible'});
  assert.equal(await page.locator('.feature-card').count(),6,'Six core feature cards must render');
  assert.equal(await page.locator('.industry-card').count(),4,'Four distinct industry cards must render');
  assert.equal(await page.getByRole('heading',{name:/Built for the way your organization works/}).count(),1,'Industry section must be discoverable');
  const desktopIndustries=await page.locator('.industry-grid').evaluate(el=>getComputedStyle(el).gridTemplateColumns.trim().split(/\\s+/).length);
  assert.equal(desktopIndustries,4,'Desktop presents four distinct industry cards');
  assert.equal(await page.locator('.industry-visual[role="img"]').count(),4,'Industry visuals need accessible text');
  assert.equal(await page.locator('.preview-window').count(),1,'Desktop dashboard preview');
  assert.equal(await page.getByRole('navigation',{name:'Main navigation'}).isVisible(),true);
  const wide=await page.locator('.hero-grid').evaluate(el=>getComputedStyle(el).gridTemplateColumns.trim().split(/\s+/).length);
  assert.equal(wide,2,'Desktop hero must have two aligned columns');
  await page.screenshot({path:out+'/desktop.png',fullPage:true,animations:'disabled'});

  await page.setViewportSize({width:390,height:844});
  await page.waitForTimeout(200);
  const narrow=await page.locator('.hero-grid').evaluate(el=>getComputedStyle(el).gridTemplateColumns.trim().split(/\s+/).length);
  assert.equal(narrow,1,'Mobile hero must stack into one column');
  const mobileIndustries=await page.locator('.industry-grid').evaluate(el=>getComputedStyle(el).gridTemplateColumns.trim().split(/\\s+/).length);
  assert.equal(mobileIndustries,2,'390px mobile viewport must show two premium industry cards per row');
  assert.equal(await page.locator('.mobile-menu').isVisible(),true);
  assert.equal(await page.locator('.desktop-links').isVisible(),false);
  const noOverflow=await page.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+2);
  assert.equal(noOverflow,true,'Mobile landing must not overflow horizontally');
  await page.locator('.mobile-menu summary').click();
  await page.getByRole('navigation',{name:'Mobile navigation'}).getByText('How it works').waitFor();
  await page.locator('.mobile-menu summary').click();
  await page.screenshot({path:out+'/mobile.png',fullPage:true,animations:'disabled'});
  await page.setViewportSize({width:360,height:780});
  const compactIndustries=await page.locator('.industry-grid').evaluate(el=>getComputedStyle(el).gridTemplateColumns.trim().split(/\\s+/).length);
  assert.equal(compactIndustries,1,'Very narrow phones must use readable full-width cards');
  await page.locator('.faq-list details').first().locator('summary').click();
  assert.equal(await page.locator('.faq-list details').first().getAttribute('open'),'');
  assert.deepEqual(errors,[],'No JavaScript runtime errors');
  console.log('PASS: premium landing desktop/mobile visuals, navigation, accessibility anchors and responsive QA');
}finally{
  await browser.close();
}
