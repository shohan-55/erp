# DhakaWala ERP — Premium Public Landing

The public root landing is redesigned in resources/views/erp/welcome.blade.php. No changes to controllers, routes, tenant domain security, database schema, API, payroll, billing, or cPanel public_html.

## Visual design
- DhakaWala platform identity: premium sea-green/teal (#20bea9), midnight navy (#1d2850 to #086e72 gradient) and off-white. RA&C yellow/gold is **tenant-specific branding**, not the public SaaS platform brand. Responsive hero and mock preview remain clearly labelled illustrative.
- Six existing workflow highlights, independent Partner access, three-step experience, company access guidance, native collapsible FAQ and direct email CTA; copy makes it explicit that DhakaWala ERP is an independent product licensed to separate companies with monthly or yearly subscription options (without unverified published prices)
- Native details-based mobile nav, keyboard focus, reduced-motion support, no JavaScript, tracker or external fonts. The industry showcase uses four Unsplash-hosted photographs (external image requests) and gradient fallbacks if images cannot load.
- Real platform admin login URL generated from the Laravel named route, with no public self-registration

## Industry showcase (2026-10-21)
- Four responsive industry cards appear between core features and the security/tenant controls section: CA & Audit Firms, Corporate Companies, NGOs & Nonprofits and Consultancy Firms.
- Each card carries a photographic backdrop, clear industry-specific description and chips referencing platform workflows. Photos are illustrative, not client testimonials, and do not imply sector-specific modules are fully released.
- Source: Unsplash images. License: https://unsplash.com/license (free commercial use). Photographs are served over HTTPS by `images.unsplash.com`; no visitor-tracking scripts are added, but image delivery uses a third-party host. Before enforcing a first-party-only CSP or strict no-third-party traffic policy, locally host the licensed resized image assets and replace the URLs; the CSS gradient fallback remains usable without images.
- Mobile layout uses two columns on 390px devices, one column for very narrow devices, and four columns on desktop. Contact CTA opens a real support email (no fake signup route).
- Do not replace RA&C tenant logos or theme, and do not modify subscription, permissions, or production data.

## Simple cPanel landing-only rollout
The landing CSS and preview are embedded in the Blade template. Vite, npm, public_html asset publishing, database migrations and Composer are NOT necessary for this update. Four external images currently load from Unsplash where third-party images are allowed by browser and CSP.
After merge and CI verification, the owner can use one command in the existing live ERP Git worktree:

    cd /home/erpdhakawala/erp-app && git pull --ff-only origin security/production-readiness-gates && php artisan view:clear

Only run once the expected merge SHA is confirmed; do not modify .env or delete demo accounts. GitHub merge does not deploy to cPanel.

## Verification
- Feature tests render the canonical public host directly; assert responsive layout, login guard and no sensitive information.
- Playwright QA renders compiled Blade, tests desktop and mobile layouts, menu, no overflow and FAQ. Desktop/mobile screenshot artifacts are attached to CI.
- Full PHPUnit, MySQL 8, authenticated browser, Vite and review ZIP CI run unchanged.
- Manually smoke-test https://erp.dhakawala.com/, https://erp.dhakawala.com/rac, and platform login after separately authorized deployment.

No claims are made about ISO/SOC, 200GB backup capability, uptime SLA, or public registration. Preview dashboard data is illustrative only.

## Multi-tenant branding separation
Public `erp.dhakawala.com` is branded DhakaWala ERP in sea-green/navy. Firms such as RA&C retain their separate yellow/gold workspace branding where previously configured; do not globally replace tenant color tokens in resources/css/app.css or the per-tenant themes. The 2026-10-21 navy-teal refinement updates only the landing Blade template and its frontend smoke assertions. It has no backend, auth, tenant or asset pipeline changes. This landing-only color update does not change subscriptions, pricing tables, tax logic, tenant logo files, organization role permissions or the authentication system.
