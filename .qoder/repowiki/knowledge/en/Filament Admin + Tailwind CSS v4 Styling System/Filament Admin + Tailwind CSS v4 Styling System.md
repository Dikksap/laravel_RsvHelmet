---
kind: frontend_style
name: Filament Admin + Tailwind CSS v4 Styling System
category: frontend_style
scope:
    - '**'
source_files:
    - resources/css/app.css
    - resources/css/filament/admin/theme.css
    - vite.config.js
    - package.json
    - app/Filament/Auth/Login.php
    - app/Providers/Filament/AdminPanelProvider.php
    - resources/views/filament/partials/auth-brand-panel.blade.php
    - resources/views/filament/partials/auth-lockup.blade.php
---

## What system/approach is used

The frontend is a **Laravel Filament admin panel** (PHP-based) styled with **Tailwind CSS v4** and built through **Vite**. There is no separate SPA framework; the UI is composed of:
- Filament resource pages, widgets, forms, tables, and custom Blade views under `app/Filament/` and `resources/views/filament/`.
- A single Livewire component (`app/Livewire/ItemRequestForm.php`) plus its Blade template for the public-facing item request form.
- PDF generation via Blade templates rendered by DomPDF (not part of this card).

CSS tooling: Tailwind CSS v4 (`@tailwindcss/vite` plugin), Vite as the bundler, and the Filament theme entry point. No SCSS, Sass, or PostCSS plugins are configured beyond what Tailwind v4 brings.

## Key files and packages

- `resources/css/app.css` — Tailwind v4 entry; imports Tailwind, registers Blade view sources, and defines the app-wide `--font-sans` token (`Instrument Sans`).
- `resources/css/filament/admin/theme.css` — Filament theme override file; imported from `vendor/filament/filament/resources/css/theme.css`, declares `@source` paths for Filament components and custom Blade views, and contains all visual overrides.
- `vite.config.js` — Registers both CSS entries (`resources/css/app.css` and `resources/css/filament/admin/theme.css`), pulls in `laravel-vite-plugin/fonts` to fetch `Instrument Sans` weights 400/500/600 via Bunny Fonts.
- `package.json` — Declares `tailwindcss ^4.0.0`, `@tailwindcss/vite ^4.0.0`, `vite ^8.0.0`, and `laravel-vite-plugin ^3.1`.
- `public/build/`, `public/css/filament/filament/app.css`, `public/js/filament/*` — Vite build outputs (compiled assets served at runtime).
- `resources/views/filament/pages/*.blade.php` — Custom Filament page views that extend the admin shell.
- `resources/views/filament/partials/auth-brand-panel.blade.php`, `auth-lockup.blade.php` — Branding partials referenced by the auth layout.
- `app/Filament/Auth/Login.php` — Filament login page class.
- `app/Providers/Filament/AdminPanelProvider.php` — Where the Filament admin panel is registered (theme integration point).

## Architecture and conventions

### Theme layering
1. Base Filament stylesheet is pulled in via `@import '../../../../vendor/filament/filament/resources/css/theme.css';` at the top of `theme.css`.
2. Tailwind's `@source` directives scan both `app/Filament/**/*` and `resources/views/filament/**/*` so utility classes written inline in Filament PHP classes and Blade views are included in the build.
3. All custom styling lives in `theme.css`; there is no other application-level CSS besides the minimal `app.css` which only sets the font token.

### Design tokens and color palette
- The primary accent color is amber/gold (`#f59e0b`, `#fbbf24`, `#fcd34d`) used for active sidebar indicators, logo gradients, button shadows, and the auth lockup mark.
- Neutral palette follows Tailwind's slate scale (`#111827`, `#0f172a`, `#1f2937`, `#334155`, `#475569`, `#64748b`, `#94a3b8`, `#cbd5e1`, `#e2e8f0`, `#f1f5f9`, `#f8fafc`).
- Dark mode is supported via `.dark` selectors throughout `theme.css`; light/dark variants are explicitly declared for sidebar, topbar, auth background, inputs, and buttons.
- Typography token: `--font-sans: 'Instrument Sans', ui-sans-serif, system-ui, ...` defined in `app.css` and loaded at runtime via Vite fonts.

### Visual identity
- Sidebar uses a dark gradient (`#111827` → `#0f172a`) with an amber left-border indicator (`inset 3px 0 0 #f59e0b`) on the active item.
- Auth/login screen uses a two-column grid layout at ≥1024px: a branded left panel (`.rsv-auth-brand`) with radial gradients and a subtle dot-grid mask, and a right-side card (`.fi-simple-main`) with rounded corners, shadow, and a top amber gradient bar.
- The brand mark is a gold-gradient rounded square with embossed inset shadow and uppercase lettering.

### Responsive strategy
- Breakpoints are applied via standard CSS `@media (min-width: 1024px)` rules in `theme.css` (no Tailwind config is present, so Tailwind v4 defaults are used).
- Fluid typography uses `clamp()` for the auth brand title (`clamp(1.75rem, 2.2vw, 2.5rem)`).

### Component library usage
- Filament provides the base UI (sidebar, topbar, tables, forms, modals, notifications). Customization is done by targeting Filament's internal class names (prefixed with `.fi-`) directly in `theme.css` rather than extending via a Filament theme configuration file.
- One third-party Filament package is used: `mrpunyapal/filament-infinite-select` (custom JS under `public/js/mrpunyapal/filament-infinite-select/components/infinite-select.js` and a custom Filament Form component `app/Filament/Forms/Components/InfiniteSelect.php`).

### Build pipeline
- Two CSS entry points are compiled by Vite: `resources/css/app.css` (Tailwind utilities + font token) and `resources/css/filament/admin/theme.css` (Filament overrides).
- Font assets are fetched at build time via `laravel-vite-plugin/fonts` using Bunny Fonts for `Instrument Sans` (weights 400, 500, 600).
- Development server runs on port `5174` with HMR enabled and ignores `storage/framework/views/**` from watching.

## Conventions and constraints

- **All custom styles go in `resources/css/filament/admin/theme.css`** — this is the single source of truth for the admin UI appearance; no other CSS files contain application-specific rules.
- **Filament component classes are targeted directly** (e.g., `.fi-sidebar`, `.fi-topbar`, `.fi-simple-layout`, `.fi-btn`) rather than being redefined through a Filament theme config; overrides are scoped with `.dark .fi-*` variants for dark mode.
- **Brand colors are hard-coded hex values** (`#f59e0b`, `#fbbf24`, `#fcd34d`) rather than being expressed as CSS variables or Tailwind design tokens; there is no centralized design-token file.
- **Dark mode is opt-in per rule** using the `.dark` selector prefix; it is not driven by a global theme toggle in the codebase observed here.
- **Responsive breakpoints use raw CSS `@media` queries** against pixel widths (e.g., `1024px`) instead of Tailwind's responsive prefixes, since no `tailwind.config.*` file exists.
- **Fonts are declared once** in `app.css` via the Tailwind v4 `@theme { --font-sans: ... }` block and loaded through Vite's Bunny Fonts integration; no other font families are introduced.
- **Blade views are scanned automatically** via `@source '../views'` in `app.css` and `@source` directives in `theme.css`, so utility classes written inline in Blade templates are included without explicit imports.