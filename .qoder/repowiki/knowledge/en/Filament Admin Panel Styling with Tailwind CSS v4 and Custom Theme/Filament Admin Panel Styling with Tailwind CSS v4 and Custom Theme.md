---
kind: frontend_style
name: Filament Admin Panel Styling with Tailwind CSS v4 and Custom Theme
category: frontend_style
scope:
    - '**'
source_files:
    - resources/css/filament/admin/theme.css
    - resources/css/app.css
    - app/Providers/Filament/AdminPanelProvider.php
    - vite.config.js
    - package.json
---

## Approach

The application's frontend styling is centered on **Laravel Filament** as the admin UI framework, with **Tailwind CSS v4** (via `@tailwindcss/vite`) handling utility classes. The visual identity of the admin panel is implemented through a custom Filament theme that imports Filament's base theme and overrides component styles via CSS variables and class selectors.

## Key Files and Packages

- `resources/css/app.css` — Application stylesheet; imports Tailwind v4 (`@import 'tailwindcss'`) and declares the `--font-sans` theme token using `Instrument Sans`.
- `resources/css/filament/admin/theme.css` — Filament admin theme override file (~535 lines); imported by Vite and registered via `->viteTheme()` in `AdminPanelProvider.php`. It imports Filament's base theme (`vendor/filament/filament/resources/css/theme.css`) and uses `@source` directives to scan `app/Filament/**/*` and `resources/views/filament/**/*` for dynamic class names.
- `app/Providers/Filament/AdminPanelProvider.php` — Registers the Filament panel: sets primary color to `Color::Amber`, points to the custom theme CSS, configures sidebar width (`16rem`), navigation groups, render hooks, and SPA mode.
- `vite.config.js` — Vite build configuration; compiles both `resources/css/app.css` and `resources/css/filament/admin/theme.css`, plus `resources/js/app.js`; uses `laravel-vite-plugin/fonts` with `bunny('Instrument Sans', { weights: [400, 500, 600] })` to fetch the font at runtime.
- `package.json` — Declares `tailwindcss ^4.0.0`, `@tailwindcss/vite ^4.0.0`, `vite ^8.0.0`, and `laravel-vite-plugin ^3.1`.
- `public/build/`, `public/css/filament/filament/app.css`, `public/fonts/filament/filament/inter/` — Compiled assets and bundled Inter font files served to the browser.

## Architecture and Conventions

### Filament theme layering

The theme follows a two-layer approach:
1. Base Filament theme is pulled in via `@import '../../../../vendor/filament/filament/resources/css/theme.css';`.
2. Overrides are written as plain CSS targeting Filament's internal class names (e.g., `.fi-sidebar`, `.fi-topbar`, `.fi-simple-layout`, `.fi-simple-main`, `.fi-btn`).

### Color system

The design uses an **amber/gold accent palette** against a dark slate sidebar:
- Primary accent: amber tones (`#f59e0b`, `#fbbf24`, `#fcd34d`) used for active states, gradients, shadows, and the top-bar gradient line.
- Sidebar background: dark slate gradient (`#111827` → `#0f172a`).
- Text colors follow a light/dark pair pattern throughout (e.g., `#dbe3ee` / `#cbd5e1` for labels).

### Dark mode

Dark-mode variants are applied using the `.dark` prefix on selectors (e.g., `.dark .fi-sidebar`, `.dark .fi-simple-layout`, `.dark .rsv-auth-brand`). This relies on Filament's built-in dark mode toggle.

### Auth page branding

A custom split-screen login layout is implemented via render hooks in `AdminPanelProvider.php`:
- `PanelsRenderHook::SIMPLE_LAYOUT_START` injects `filament.partials.auth-brand-panel`.
- `PanelsRenderHook::SIMPLE_PAGE_START` injects `filament.partials.auth-lockup`.
- The left panel (desktop only, `@media (min-width: 1024px)`) shows a branded hero section with radial gradients, grid overlay, and feature list items.

### Typography

- Font family: `Instrument Sans` (loaded via Bunny Fonts CDN at runtime through Vite plugin).
- Font sizes use Tailwind-style numeric scale (e.g., `0.625rem`, `0.8125rem`, `0.875rem`, `1rem`, `1.5rem`).
- Letter-spacing is tuned per element (e.g., `-0.025em` for headings, `0.08em` for uppercase labels).

### Responsive strategy

Responsive behavior is handled via standard CSS `@media (min-width: 1024px)` breakpoints — notably for the auth page's two-column grid layout and the brand panel visibility.

### Blade view scanning

The theme CSS uses `@source` directives so Tailwind can extract class names from Filament resources and custom Blade views under `resources/views/filament/**/*`.

## Conventions and Constraints

- **All Filament visual customization goes through `resources/css/filament/admin/theme.css`**, which is the single entry point registered via `->viteTheme()` in `AdminPanelProvider.php`. No other CSS file targets Filament internals.
- **Primary brand color is set declaratively** via `->colors(['primary' => Color::Amber])` in `AdminPanelProvider.php`; the CSS then hard-codes matching amber hex values rather than referencing CSS variables, making the theme tightly coupled to this specific shade.
- **Dark mode is opt-in via the `.dark` selector prefix** — every dark-mode variant must be explicitly declared alongside its light counterpart.
- **Sidebar width is fixed at `16rem`** via `->sidebarWidth('16rem')` in the provider; the CSS mirrors this with matching padding/margins.
- **Navigation groups are defined centrally** in `AdminPanelProvider.php` under five categories: `Gudang`, `Permintaan Barang`, `Produksi`, `Integrasi Eksternal`, `Data Master`.
- **Auth branding is injected exclusively through Filament render hooks** (`SIMPLE_LAYOUT_START`, `SIMPLE_PAGE_START`, `BODY_END`); no direct template overrides are used for the auth flow.
- **Fonts are loaded at runtime** via `laravel-vite-plugin/fonts` with `bunny()`, not pre-bundled into the asset pipeline.
- **Tailwind v4 syntax** is used (`@import 'tailwindcss'` instead of `@tailwind/base`, `@tailwind/components`, `@tailwind/utilities`; `@theme` block for tokens).