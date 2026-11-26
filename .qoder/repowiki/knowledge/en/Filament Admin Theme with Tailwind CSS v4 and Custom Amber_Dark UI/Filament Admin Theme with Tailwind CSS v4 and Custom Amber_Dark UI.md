---
kind: frontend_style
name: Filament Admin Theme with Tailwind CSS v4 and Custom Amber/Dark UI
category: frontend_style
scope:
    - '**'
source_files:
    - resources/css/app.css
    - resources/css/filament/admin/theme.css
    - vite.config.js
    - package.json
    - app/Providers/Filament/AdminPanelProvider.php
    - resources/views/filament/partials/auth-brand-panel.blade.php
    - resources/views/filament/partials/auth-lockup.blade.php
---

## Approach

The application's frontend style is centered on **Filament Admin** as the UI framework, with a custom theme built on **Tailwind CSS v4** (via `@tailwindcss/vite` plugin) and compiled through **Vite**. The admin panel is registered in `app/Providers/Filament/AdminPanelProvider.php`, which wires the Vite theme entry point at `resources/css/filament/admin/theme.css`. Public-facing Blade views use Tailwind utility classes directly.

There is no separate design-token system beyond Filament's built-in color tokens; visual identity is expressed through hard-coded hex values and Tailwind utilities in the theme file.

## Key Files

- `resources/css/app.css` — public app stylesheet; imports Tailwind v4 via `@import 'tailwindcss'`, declares `@source` for Blade views and pagination templates, and sets the `--font-sans` token to `'Instrument Sans'`.
- `resources/css/filament/admin/theme.css` — the Filament theme override file (~738 lines); imports Filament's base theme via `@import '../../../../vendor/filament/filament/resources/css/theme.css'` and registers source paths for Filament components and Blade views under `app/Filament/**/*` and `resources/views/filament/**/*`.
- `vite.config.js` — Vite config that compiles both `resources/css/app.css` and `resources/css/filament/admin/theme.css`, plus `resources/js/app.js`; uses `laravel-vite-plugin/fonts` with `bunny('Instrument Sans', { weights: [400, 500, 600] })` to fetch the font from Google Fonts.
- `package.json` — dev dependencies are `tailwindcss ^4.0.0`, `@tailwindcss/vite ^4.0.0`, `vite ^8.0.0`, and `laravel-vite-plugin ^3.1`.
- `app/Providers/Filament/AdminPanelProvider.php` — registers the panel (`id('admin')`, `path('admin')`), sets `->colors(['primary' => Color::Amber])`, points to the Vite theme via `->viteTheme('resources/css/filament/admin/theme.css')`, enables SPA mode, fixes sidebar width to `16rem`, defines navigation groups (`Gudang`, `Permintaan Barang`, `Produksi`, `Integrasi Eksternal`, `Data Master`), and injects Blade partials via `PanelsRenderHook` (`SIMPLE_LAYOUT_START`, `SIMPLE_PAGE_START`, `BODY_END`).
- `resources/views/filament/partials/auth-brand-panel.blade.php` and `auth-lockup.blade.php` — Blade partials injected into the auth layout hooks.
- `public/build/`, `public/css/filament/filament/app.css`, `public/fonts/filament/filament/inter/` — compiled assets and bundled fonts.

## Architecture and Conventions

### Two-entry CSS pipeline

Vite builds two CSS entries:
1. `resources/css/app.css` — Tailwind utilities for Blade views and Livewire pages.
2. `resources/css/filament/admin/theme.css` — Filament theme overrides for the admin panel.

Both are declared as inputs in `vite.config.js` so they are compiled independently.

### Filament theme customization strategy

The theme file does not extend Filament's CSS variables; it directly targets Filament's internal class names (prefixed with `.fi-`) such as `.fi-sidebar`, `.fi-topbar`, `.fi-ta-ctn`, `.fi-simple-layout`, `.fi-simple-main`, etc. This means the styling approach is **CSS override of Filament internals** rather than configuration-driven theming.

### Design tokens and palette

- Primary accent is amber/gold: `#f59e0b`, `#fbbf24`, `#fcd34d`, `#d97706`, `#b45309` — used for active sidebar items, selection highlights, gradient accents, and the brand mark.
- Neutral palette follows Tailwind's slate scale: `#0f172a`, `#111827`, `#16202f`, `#1f2937`, `#334155`, `#475569`, `#64748b`, `#94a3b8`, `#cbd5e1`, `#dbe3ee`, `#e2e8f0`, `#e5e7eb`, `#f1f5f9`, `#f8fafc`, `#f4f6fb`, `#ffffff`.
- Dark-mode variants are applied via the `.dark` selector alongside each light-mode rule; there is no dark-mode toggle in the codebase — both light and dark rules coexist.
- Typography: `--font-sans` is set to `'Instrument Sans'` in `app.css`; Filament's default Inter font remains bundled under `public/fonts/filament/filament/inter/` but is not referenced by the app's own styles.
- Spacing and sizing are expressed in Tailwind-style units (`0.625rem`, `0.75rem`, `1rem`, `1.25rem`, `1.5rem`, `2rem`, `3rem`, `clamp(2.5rem, 4vw, 4rem)`).

### Layout conventions

- Sidebar: fixed width `16rem` (configured in `AdminPanelProvider`), fully collapsible on desktop, dark background (`#111827` → `#0f172a` gradient), active item highlighted with a left border accent `inset 3px 0 0 #f59e0b` and amber icon/text.
- Topbar: translucent white (`rgb(255 255 255 / 0.96)`) with `backdrop-filter: blur(12px)` in light mode; dark variant uses `rgb(17 24 39 / 0.96)`.
- Tables: sticky headers (`position: sticky; top: 0; z-index: 2`), uppercase small labels (`0.6875rem`, `letter-spacing: 0.06em`), compact row padding, hover rows on `#f8fafc` (light) / `rgb(255 255 255 / 0.035)` (dark), selected rows tinted with amber at low opacity.
- Auth/simple layout: split-screen grid at `min-width: 1024px` (`grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr)`), branded left panel (`rsv-auth-brand`) with radial gradients and a subtle dot-grid overlay, right panel card with rounded corners (`border-radius: 1.5rem`) and an amber top-border gradient.

### Responsive strategy

Responsive behavior is defined inline via CSS `@media` queries inside the theme file rather than Tailwind breakpoints:
- `@media (min-width: 1024px)` controls the split auth layout and the branded side panel visibility.
- `@media (hover: hover)` gates hover-only table row backgrounds.

No Tailwind responsive prefixes (e.g., `md:`, `lg:`) appear in the custom CSS; Tailwind utilities are used only in Blade/Livewire views.

### Blade view styling

Public Blade views under `resources/views/` (including `layouts/public.blade.php`, `item-requests/document.blade.php`, `pdf/item-request.blade.php`, and Livewire views) consume Tailwind utilities directly through the `app.css` entry. Filament-specific Blade views live under `resources/views/filament/` (pages, partials, tables/columns) and are scanned by the theme's `@source` directive.

## Conventions and Constraints

- Filament theme overrides target Filament's internal `.fi-*` class selectors directly in `resources/css/filament/admin/theme.css` rather than using Filament's CSS variable API; this is the established pattern throughout the ~738-line file.
- Both light and dark variants of every overridden rule are present using the `.dark` selector prefix — the project ships dual-theme CSS even though no runtime dark-mode toggle exists.
- The primary brand color is consistently the amber family (`#f59e0b` / `#fbbf24` / `#fcd34d`); it is also configured as Filament's primary color via `Color::Amber` in `AdminPanelProvider.php`.
- Font loading is centralized in `vite.config.js` using `bunny('Instrument Sans', { weights: [400, 500, 600] })`; the same font family name is assigned to `--font-sans` in `resources/css/app.css`.
- Navigation is organized into five collapsible groups (`Gudang`, `Permintaan Barang`, `Produksi`, `Integrasi Eksternal`, `Data Master`) defined in `AdminPanelProvider.php`; resources and pages are auto-discovered under `app/Filament/Resources` and `app/Filament/Pages`.
- The build pipeline is strictly Vite + Tailwind v4: `package.json` pins `tailwindcss ^4.0.0` and `@tailwindcss/vite ^4.0.0`, and `vite.config.js` uses the new `@import 'tailwindcss'` syntax (no `tailwind.config.js`).
- Assets are emitted to `public/build/` and `public/css/filament/filament/app.css`; pre-bundled Filament JS/CSS and Inter fonts remain under `public/vendor/` and `public/fonts/filament/filament/inter/` respectively.