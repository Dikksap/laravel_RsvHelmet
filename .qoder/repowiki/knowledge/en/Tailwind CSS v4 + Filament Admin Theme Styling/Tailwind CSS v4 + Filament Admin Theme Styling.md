---
kind: frontend_style
name: Tailwind CSS v4 + Filament Admin Theme Styling
category: frontend_style
scope:
    - '**'
source_files:
    - resources/css/app.css
    - resources/css/filament/admin/theme.css
    - vite.config.js
    - package.json
---

## Approach

The RSV Helmet application uses **Tailwind CSS v4** (via `@tailwindcss/vite` plugin) as its sole styling framework, with the **Filament admin panel** providing the bulk of the UI. There is no separate design system or component library beyond Filament's own components; all custom styling is done by overriding Filament's internal CSS classes and extending Tailwind's theme.

## Key Files

- `resources/css/app.css` — Application entry point: imports Tailwind, declares `@source` paths for Blade views, and sets the `--font-sans` theme variable to `Instrument Sans`.
- `resources/css/filament/admin/theme.css` — The central Filament theme override file. It imports Filament's base theme (`vendor/filament/filament/resources/css/theme.css`) and then redefines sidebar, topbar, auth layout, and brand assets using raw CSS selectors (`.fi-*`, `.rsv-auth-*`).
- `vite.config.js` — Vite build config that registers both CSS entries (`resources/css/app.css` and `resources/css/filament/admin/theme.css`) plus `resources/js/app.js`, and pulls in the `Instrument Sans` font via `laravel-vite-plugin/fonts` with weights `[400, 500, 600]`.
- `package.json` — Declares `tailwindcss ^4.0.0`, `@tailwindcss/vite ^4.0.0`, `vite ^8.0.0`, and `laravel-vite-plugin ^3.1`. No PostCSS, Sass, or other preprocessors are present.
- `public/build/`, `public/css/filament/filament/app.css`, `public/js/filament/...` — Compiled/bundled output served at runtime.

## Architecture & Conventions

1. **Two-entry CSS pipeline.** Vite compiles two independent CSS bundles: the app-level Tailwind stylesheet and the Filament admin theme overrides. Both are emitted into `public/build/` and referenced from Blade layouts.
2. **Tailwind v4 configuration-less setup.** There is no `tailwind.config.js`; Tailwind v4 reads configuration directly from CSS (`@import 'tailwindcss'`, `@theme { --font-sans: ... }`) and discovers utility classes through `@source` directives pointing at Blade views and generated view cache.
3. **Filament class-name targeting.** Custom styles target Filament's internal DOM class names (e.g., `.fi-sidebar`, `.fi-topbar`, `.fi-simple-layout`, `.fi-simple-main`, `.fi-input-wrp`, `.fi-btn`). This is the primary mechanism for applying the RSV brand identity (dark sidebar, amber accent `#f59e0b` / `#fbbf24`, rounded inputs/buttons).
4. **Brand tokens live in CSS, not Tailwind variables.** Colors like `#f59e0b` (amber), `#111827` (slate-900), `#f8fafc` (slate-50), `#94a3b8` (slate-400) are hard-coded throughout `theme.css` rather than being declared as Tailwind theme extensions. The only Tailwind theme extension is the font family.
5. **Dark mode via `.dark` selector.** Every overridden rule has a matching `.dark .fi-*` variant, relying on Filament's built-in dark-mode toggle rather than Tailwind's `dark:` modifier.
6. **Responsive breakpoints are native CSS media queries.** Breakpoints such as `min-width: 1024px` are used directly inside `theme.css` instead of Tailwind's responsive prefixes.
7. **Auth page branding is isolated under `.rsv-auth-*` classes.** The login/reset screens get a split-panel layout (brand panel + form card) with a radial gradient background, an amber top-bar accent on the card, and a decorative grid overlay — all scoped to these custom class names so they do not leak into the rest of the admin panel.
8. **Blade partials carry the visual structure.** Branding markup lives in `resources/views/filament/partials/auth-brand.blade.php` and `auth-lockup.blade.php`, while table column overrides live in `resources/views/filament/tables/columns/progress-bar.blade.php`.

## Conventions Observed

- All custom CSS goes into `resources/css/filament/admin/theme.css`; there are no additional `.css` files under `resources/css/` besides the app entry.
- Filament component classes are targeted by their full class name (e.g., `.fi-sidebar .fi-sidebar-item.fi-active > .fi-sidebar-item-btn`) rather than creating new wrapper classes around Filament output.
- Amber (`#f59e0b`, `#fbbf24`, `#fcd34d`) is the single accent color used for active states, icons, shadows, and the auth card top-bar gradient.
- Slate tones (`#111827`, `#0f172a`, `#94a3b8`, `#cbd5e1`, `#e5e7eb`) are used for backgrounds, text, and borders across light and dark modes.
- Rounded corners consistently use `0.75rem` (12px) for buttons/inputs and `1.5rem` (24px) for the auth card.
- Transitions are uniform: `background-color 150ms ease, color 150ms ease` (sometimes with `box-shadow 150ms ease`).
- Font loading is delegated to `laravel-vite-plugin/fonts` with the Bunny CDN; the app never references a local font file.

## Rules Enforced by Tooling

- **Vite input is fixed to three entries** (`resources/css/app.css`, `resources/css/filament/admin/theme.css`, `resources/js/app.js`) in `vite.config.js`; adding a new CSS bundle requires editing this array.
- **Tailwind source scanning** is explicitly limited to `../views`, Laravel pagination views, and compiled view cache via `@source` directives in `app.css`; utilities written outside those paths will be purged.
- **Font weights are restricted to `[400, 500, 600]`** by the `bunny()` call in `vite.config.js`; requesting other weights would require updating the config.