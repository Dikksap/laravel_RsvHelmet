---
kind: frontend_style
name: Tailwind CSS v4 + Filament Admin Theme Customization
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

The application uses **Tailwind CSS v4** (via `@tailwindcss/vite` plugin) as the styling engine, with a **Filament admin panel** whose default theme is heavily customized. There is no SCSS preprocessor, no component library beyond Filament's built-in components, and no design-token system beyond Tailwind's native CSS variables.

## Key Files

- `resources/css/app.css` — project-level Tailwind entry point; imports Tailwind via `@import 'tailwindcss'`, declares `@source` directives for Blade views, and sets the `--font-sans` font stack to `'Instrument Sans'`.
- `resources/css/filament/admin/theme.css` — Filament theme override file that imports the vendor theme (`vendor/filament/filament/resources/css/theme.css`) and applies ~530 lines of custom CSS targeting `.fi-*` classes (sidebar, topbar, auth layout, brand lockup).
- `vite.config.js` — Vite build config registering both `resources/css/app.css` and `resources/css/filament/admin/theme.css` as inputs, plus the `@tailwindcss/vite` plugin and Laravel Vite plugin with `bunny('Instrument Sans', { weights: [400, 500, 600] })` for font loading.
- `package.json` — declares `tailwindcss ^4.0.0` and `@tailwindcss/vite ^4.0.0` as devDependencies; no other CSS framework or UI library in dependencies.
- `public/fonts/filament/filament/inter/` — Inter font files vendored by Filament (used by Filament's own defaults).
- `public/css/filament/filament/app.css` — compiled Filament stylesheet output.

## Architecture & Conventions

1. **Two-entry CSS pipeline**: The public app styles live in `resources/css/app.css` (Tailwind-only, minimal), while all Filament admin-panel visual customization lives in `resources/css/filament/admin/theme.css`. Both are registered as separate Vite inputs so they compile independently.
2. **Tailwind v4 syntax**: Uses the new `@import 'tailwindcss'` directive (no `tailwind.config.*` file exists in the repo). Design tokens are defined inline via the `@theme { --font-sans: ... }` block rather than a config object.
3. **Font strategy**: The primary typeface is **Instrument Sans**, loaded at weights 400/500/600 through Vite's Bunny font loader. The `@theme` block registers it as `--font-sans`, replacing the default sans stack.
4. **Filament theme overrides**: Instead of using Filament's PHP-based theme API, the project overrides the default theme entirely via raw CSS selectors on Filament's internal class names (`.fi-sidebar`, `.fi-topbar`, `.fi-simple-layout`, `.fi-simple-main`, etc.). This gives fine-grained control over sidebar colors, active-item accent, auth page layout, and branding.
5. **Brand palette**: A consistent amber/gold accent (`#f59e0b`, `#fbbf24`, `#fcd34d`) is used throughout the Filament theme — active sidebar items, the auth card gradient, the top-of-card gradient bar, and feature-list bullet icons all derive from this amber family. The dark mode palette is based on slate tones (`#111827`, `#0f172a`, `#0b1220`).
6. **Auth screen split layout**: On screens ≥1024px, the login/reset pages use a two-column grid (`.fi-simple-layout`) where the left side contains a branded panel (`.rsv-auth-brand`) with radial gradients and a subtle grid overlay, and the right side holds the form inside a rounded card (`.fi-simple-main`) with a gold top-bar accent.
7. **Responsive strategy**: Pure CSS media queries (`@media (min-width: 1024px)`) are used for the auth split layout and brand panel visibility. No Tailwind breakpoints are configured beyond the defaults.
8. **Blade view scanning**: `@source '../views'` and additional `@source` paths in `app.css` tell Tailwind v4 to scan Blade templates for class names, enabling JIT compilation against the public-facing Blade views under `resources/views/`.

## Conventions Observed

- All Filament-specific styling goes into `resources/css/filament/admin/theme.css`; no inline CSS is added directly in Blade views for the admin panel.
- Branding colors are expressed as explicit hex values (`#f59e0b`, `#fbbf24`, `#fcd34d`) rather than Tailwind utility classes, because the project does not extend Tailwind's color palette via a config file.
- Dark-mode variants are applied using the `.dark .fi-*` selector pattern consistently across sidebar, topbar, and auth components.
- The `@theme` block in `app.css` is the single place where global design tokens (like `--font-sans`) are declared.
- Fonts are loaded declaratively through Vite's Bunny integration rather than `<link>` tags or manual `@font-face` rules.

## Rules Enforced by Tooling

- The Vite build only compiles the three inputs declared in `vite.config.js`: `resources/css/app.css`, `resources/css/filament/admin/theme.css`, and `resources/js/app.js`. Any other CSS placed outside these entries will not be included in the production build.
- Tailwind class discovery is limited to the paths listed in the `@source` directives in `app.css` (`../views`, Laravel pagination views, and compiled view cache); classes written in Blade files outside those paths will be purged.