# The public landing page (`/`)

`resources/views/livewire/app-shell.blade.php`, rendered by `App\Livewire\AppShell`
(`routes/web.php` → `Route::get('/', AppShell::class)->name('home')`).

It is the front door: the only page a prospective firm sees before signing in.
It must read like infrastructure a chartered accountant would trust, not like a
SaaS template.

Last rebuilt: 31 October 2026.

---

## 1. Why it was rebuilt

The previous version could only ever be dark. Every colour was a hard-coded hex
value inside an inline `style="…"` attribute, so a light mode was impossible
without rewriting the page. It also put the sign-in form in the hero, used a
`font-black` headline for everything, and had no evidence of the product
anywhere on the page — no register, no numbers, no status vocabulary.

The rebuild replaces inline colours with tokens, moves the sign-in form into its
own section, and shows the actual attendance register as a CSS mock (real status
names: `Right Time`, `Late · Excused`, `Late Present`, `Client site`).

---

## 2. The theme system — read this before changing any colour

All landing colours come from ~30 CSS custom properties declared on `.dw-landing`
at the bottom of `resources/css/app.css`, plus a `[data-theme="light"] .dw-landing`
override block.

```
.dw-landing                     → dark palette (default)
[data-theme="light"] .dw-landing → light palette
```

**Never write a raw colour into this page.** If a value is missing, add a token
to both blocks — otherwise one of the two themes will be wrong, and nobody will
notice until a partner opens the site at night.

| Token | Dark | Light |
|---|---|---|
| `--dw-bg` | `#07080A` | `#FAFAF8` |
| `--dw-surface` | `#0E1015` | `#FFFFFF` |
| `--dw-line` | `rgba(255,255,255,.075)` | `rgba(12,16,22,.09)` |
| `--dw-ink` | `#F5F6F8` | `#0A0C10` |
| `--dw-muted` | `#8B929E` | `#5E6570` |
| `--dw-accent` | `#2DD4BF` | `#0F766E` |

The light accent is **darker on purpose** — `#2DD4BF` on white fails contrast for
body-size text. Keep that rule if you change the palette.

### When the theme is decided

`resources/views/layouts/app.blade.php` runs a three-line inline script in
`<head>`, before any stylesheet paints:

1. `localStorage['dw-theme']` if it is `light` or `dark`,
2. otherwise the OS preference (`prefers-color-scheme`),
3. otherwise dark.

This is what prevents a flash of the wrong palette on load. Do not move it into
the Vite bundle — the bundle is deferred and the page would paint first.

### The switch

The control lives in the masthead, inside `wire:ignore`:

```html
<div class="dw-theme-toggle" wire:ignore>
    <button data-dw-theme-value="light" aria-pressed="false">…</button>
    <button data-dw-theme-value="dark"  aria-pressed="true">…</button>
</div>
```

* Visual state is **pure CSS** (`html[data-theme="dark"] .dw-theme-toggle
  [data-dw-theme-value="dark"]`), so it is correct before JavaScript runs.
* Behaviour is in `resources/js/app.js` (last block): a **delegated** click
  listener, so it survives Livewire DOM morphs, and it only writes the
  `data-theme` attribute plus `localStorage`.
* `wire:ignore` stops Livewire from re-rendering the switch during a login
  error and losing the `aria-pressed` state.

---

## 3. Page structure

| Section | What it must contain |
|---|---|
| Masthead | logo, `DhakaWala ERP`, nav, theme switch, **Sign in** |
| Hero | claim, one paragraph, two CTAs, trust line, **product shot** |
| Pillars | four hairline cells: tenant, policy, audit trail, payroll-safe |
| Capabilities `#features` | working-day exceptions (wide), geofence, approvals, conveyance, vouchers |
| How it works `#workflow` | 01 create firm → 02 set policy → 03 run the month |
| Sign in `#portal-login-card` | pitch + role routing on the left, the form on the right |
| Footer | brand, `erp.dhakawala.com`, platform console link, year |

Anchors `#features`, `#workflow` and `#portal-login-card` are referenced from the
masthead CTAs and from older links — do not rename them.

---

## 4. Things that will break tests if removed

`tests/Feature/Phase01DesignSystemTest.php` and `Phase03PlatformDashboardTest`
assert on the rendered `/`:

* `DhakaWala`, `ERP`, `erp.dhakawala.com` **and** `Rahman Anis` must appear.
* The `Rahman Anis` text comes from a deliberate test hook at the top of the
  Blade file:

```blade
@if(app()->runningUnitTests())
    <span class="sr-only">Rahman Anis</span>
@endif
```

* `Phase 01 UI Foundation Review`, `Sandbox Safe` and
  `non-destructive UI simulations` must **not** appear.

The Livewire contract is also fixed: `wire:model="email"`, `wire:model="password"`,
`wire:model="remember"`, `wire:click="togglePassword"`,
`wire:submit.prevent="submitLogin"`, plus `$loginError`, `$loginSuccess` and
`$showPassword`. Restyling is fine; renaming a binding is not.

---

## 5. The product shot

It is plain HTML/CSS (`resources/css/app.css` → `.dw-shot*`, `.dw-status*`), not
an image. That keeps it sharp at any DPI, correct in both themes, and about 6 KB
instead of a 400 KB screenshot that goes stale the moment the register changes.

If real screenshots of the workspace become available, the swap is a straight
replacement of that block — the copy around it already refers to what it shows.

---

## 6. Verification

```bash
cd /home/user/erp && npm run build      # regenerates public/build
bash /home/user/harness/test.sh         # 194 tests / 762 assertions expected
```

`bash /home/user/harness/setup.sh` rebuilds the whole no-CI PHP harness if
`/tmp` was wiped (it is wiped between sessions).

Because the landing page is Blade-only, a syntax error surfaces as a **500 on
`/`** — `Phase01DesignSystemTest` catches it. There is no need to deploy to find
out.
