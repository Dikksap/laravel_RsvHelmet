---
kind: frontend_style
name: Filament Admin Theme with Tailwind v4 and Custom Amber Branding
category: frontend_style
scope:
    - '**'
source_files:
    - resources/css/app.css
    - resources/css/filament/admin/theme.css
    - app/Providers/Filament/AdminPanelProvider.php
    - vite.config.js
    - package.json
    - resources/views/filament/partials/auth-brand-panel.blade.php
    - resources/views/filament/partials/auth-lockup.blade.php
---

## Approach

The frontend styling is built around **Filament Panels** (Laravel admin UI framework) with a custom theme layered on top of **Tailwind CSS v4** via `@tailwindcss/vite`. The public-facing pages use standard Blade views plus Livewire components; the admin panel is entirely Filament-driven.

- **CSS engine**: Tailwind CSS v4 (`tailwindcss` ^4.0 + `@tailwindcss/vite`), configured through Vite in `vite.config.js`.
- **Admin UI**: Filament Panels, registered in `app/Providers/Filament/AdminPanelProvider.php` as the default `admin` panel at path `/admin`.
- **Build tool**: Vite (laravel-vite-plugin), serving assets from `resources/css/app.css` and `resources/css/filament/admin/theme.css`.
- **Font**: Instrument Sans loaded via `bunny('Instrument Sans', { weights: [400, 500, 600] })` in Vite config; the font family is declared as `--font-sans` in `resources/css/app.css`.

## Key files

- `resources/css/app.css` — global Tailwind import, `@source` directives for Blade views and pagination, and the `--font-sans` design token.
- `resources/css/filament/admin/theme.css` — the complete Filament theme override (sidebar, topbar, auth layout, brand lockup).
- `app/Providers/Filament/AdminPanelProvider.php` — registers the panel, sets primary color to `Color::Amber`, wires the Vite theme file, navigation groups, render hooks, and widgets.
- `vite.config.js` — declares the two CSS entry points and the Instrument Sans font bundle.
- `package.json` — lists Tailwind v4 and Vite as dev dependencies.
- `public/css/filament/filament/app.css` — compiled output (not edited by hand).
- `resources/views/filament/partials/auth-brand-panel.blade.php` and `auth-lockup.blade.php` — Blade partials injected via `PanelsRenderHook::SIMPLE_LAYOUT_START` / `SIMPLE_PAGE_START`.

## Architecture and conventions

1. **Theme layering**
   - `theme.css` starts with `@import '../../../../vendor/filament/filament/resources/css/theme.css';` so it extends Filament's base theme rather than replacing it.
   - `@source` directives point at `app/Filament/**/*` and `resources/views/filament/**/*` so Tailwind can scan Filament component classes for utility generation.

2. **Design tokens**
   - Primary brand color is set declaratively in `AdminPanelProvider.php` via `->colors(['primary' => Color::Amber])`.
   - The amber palette (`#f59e0b`, `#fbbf24`, `#fcd34d`) is reused throughout `theme.css` for active sidebar states, the login page accent bar, and the brand mark gradient.
   - Typography token `--font-sans` is defined in `app.css` pointing to `'Instrument Sans'`.

3. **Sidebar and topbar customization**
   - Sidebar background is a dark slate gradient (`#111827` → `#0f172a`) with white/gray text colors (`#dbe3ee`, `#94a3b8`).
   - Active items get an amber left border (`inset 3px 0 0 #f59e0b`) and amber icon/text.
   - Topbar uses a semi-transparent white background with `backdrop-filter: blur(12px)` in light mode and a matching dark variant.

4. **Auth layout split-screen**
   - On screens ≥1024px, `.fi-simple-layout` switches to a two-column grid (`minmax(0, 1.05fr) minmax(0, 1fr)`).
   - Left column is a branded panel (`.rsv-auth-brand`) with a dark gradient, radial glow accents, and a subtle dot-grid overlay.
   - Right column holds the Filament simple form inside a rounded card (`.fi-simple-main`) with an amber top accent line.
   - The branded side is hidden on mobile and shown only via `@media (min-width: 1024px)`.

5. **Dark mode support**
   - Every major style block has a corresponding `.dark .fi-*` or `.dark .rsv-*` variant, covering sidebar, topbar, auth card, inputs, buttons, and headings.

6. **Responsive strategy**
   - Breakpoints are used sparingly: primarily `@media (min-width: 1024px)` for the auth split-screen and the radial-gradient background on the main content area.
   - Fluid typography uses `clamp()` for the brand title (`clamp(1.75rem, 2.2vw, 2.5rem)`) and padding (`clamp(2.5rem, 4vw, 4rem)`).

7. **Blade view integration**
   - Public pages live under `resources/views/` (including `layouts/public.blade.php`, `livewire/`, `pdf/`, `item-requests/`).
   - Filament-specific Blade templates live under `resources/views/filament/pages/` and are referenced by Filament Pages classes.
   - Global Tailwind scanning includes `../views` and Laravel's pagination views via `@source`.

## Conventions and constraints

- Filament theme overrides go exclusively in `resources/css/filament/admin/theme.css`; this file imports the vendor theme first and then applies selectors prefixed with `.fi-*` (Filament's class namespace).
- The panel's primary color is centralized in `AdminPanelProvider.php` via `Color::Amber`; visual elements in `theme.css` hardcode the resulting amber hex values (`#f59e0b`, `#fbbf24`, `#fcd34d`) rather than referencing CSS variables, so changing the brand requires updating both places.
- Dark-mode variants are consistently applied using the `.dark` prefix alongside each `.fi-*` rule pair.
- The auth split-screen layout is gated behind `min-width: 1024px` media queries; below that breakpoint the branded panel is hidden and the form fills the viewport.
- Fonts are bundled through Vite's `bunny` plugin with explicit weights `[400, 500, 600]`; no other font families are declared.
- Tailwind v4 is used without a separate `tailwind.config.js`; configuration lives inline in `app.css` via `@theme` and in `vite.config.js`.