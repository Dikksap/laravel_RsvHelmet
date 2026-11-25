# Layout System

<cite>
**Referenced Files in This Document**
- [public.blade.php](file://resources/views/layouts/public.blade.php)
- [welcome.blade.php](file://resources/views/welcome.blade.php)
- [item-request-form.blade.php](file://resources/views/livewire/item-request-form.blade.php)
- [document.blade.php](file://resources/views/item-requests/document.blade.php)
- [app.css](file://resources/css/app.css)
- [app.js](file://resources/js/app.js)
- [vite.config.js](file://vite.config.js)
- [web.php](file://routes/web.php)
- [app.php](file://config/app.php)
</cite>

## Update Summary
**Changes Made**
- Updated Public Layout Template section to reflect simplified header structure
- Revised Navigation Components section to document removed admin panel link and status indicator
- Updated Responsive Design Implementation section with new width constraints
- Modified Footer section to reflect streamlined minimal design
- Updated all relevant diagrams and code references

## Table of Contents
1. [Introduction](#introduction)
2. [Project Structure](#project-structure)
3. [Core Components](#core-components)
4. [Architecture Overview](#architecture-overview)
5. [Detailed Component Analysis](#detailed-component-analysis)
6. [Dependency Analysis](#dependency-analysis)
7. [Performance Considerations](#performance-considerations)
8. [Troubleshooting Guide](#troubleshooting-guide)
9. [Conclusion](#conclusion)
10. [Appendices](#appendices)

## Introduction
This document explains the Blade layout system centered around the public layout template. It covers the main layout structure, meta tags configuration, asset loading patterns for CSS and JavaScript, navigation components, responsive design implementation, accessibility features, and integration with Tailwind CSS classes. It also documents how child views extend this layout using Blade directives and how sections are defined and populated. Finally, it provides guidance on creating new layouts and extending existing ones.

## Project Structure
The layout system is implemented through Blade templates under `resources/views`, with shared styles and scripts managed by Vite and Tailwind CSS. The primary public layout defines the global HTML shell, header navigation, main content area, and footer. Other views either reuse this layout or provide standalone pages such as the welcome page and PDF-oriented document view.

```mermaid
graph TB
subgraph "Views"
L["layouts/public.blade.php"]
W["welcome.blade.php"]
F["livewire/item-request-form.blade.php"]
D["item-requests/document.blade.php"]
end
subgraph "Assets"
C["resources/css/app.css"]
J["resources/js/app.js"]
V["vite.config.js"]
end
R["routes/web.php"] --> W
R --> F
R --> D
L --> C
L --> J
V --> C
V --> J
```

**Diagram sources**
- [public.blade.php:1-52](file://resources/views/layouts/public.blade.php#L1-L52)
- [welcome.blade.php:1-489](file://resources/views/welcome.blade.php#L1-L489)
- [item-request-form.blade.php:1-419](file://resources/views/livewire/item-request-form.blade.php#L1-L419)
- [document.blade.php:1-398](file://resources/views/item-requests/document.blade.php#L1-L398)
- [app.css:1-11](file://resources/css/app.css#L1-L11)
- [app.js:1-70](file://resources/js/app.js#L1-L70)
- [vite.config.js:1-31](file://vite.config.js#L1-L31)
- [web.php:1-18](file://routes/web.php#L1-L18)

**Section sources**
- [public.blade.php:1-52](file://resources/views/layouts/public.blade.php#L1-L52)
- [welcome.blade.php:1-489](file://resources/views/welcome.blade.php#L1-L489)
- [item-request-form.blade.php:1-419](file://resources/views/livewire/item-request-form.blade.php#L1-L419)
- [document.blade.php:1-398](file://resources/views/item-requests/document.blade.php#L1-L398)
- [app.css:1-11](file://resources/css/app.css#L1-L11)
- [app.js:1-70](file://resources/js/app.js#L1-L70)
- [vite.config.js:1-31](file://vite.config.js#L1-L31)
- [web.php:1-18](file://routes/web.php#L1-L18)

## Core Components
- Public layout template: Defines the global HTML structure, head metadata, theme color, title generation, favicon, font injection, Vite asset injection, Livewire styles and scripts, simplified sticky header navigation, main content slot, and minimal footer.
- Welcome page: A standalone landing page that mirrors the layout's visual style but does not use the layout template.
- Item request form (Livewire): A feature-rich form component used by the "Permintaan Barang" route; it uses Tailwind utility classes and integrates with client-side PDF preparation and sharing logic.
- Document view: A print/PDF-oriented template for rendering item requests with dedicated print styles and a preview mode.
- Asset pipeline: Vite configuration compiles Tailwind CSS and application JavaScript, including font injection via Bunny Fonts.
- Routing: Web routes expose the home page, item request form, and signed PDF endpoint.
- Application configuration: Provides app name, locale, and other environment-driven settings used by views.

**Section sources**
- [public.blade.php:1-52](file://resources/views/layouts/public.blade.php#L1-L52)
- [welcome.blade.php:1-489](file://resources/views/welcome.blade.php#L1-L489)
- [item-request-form.blade.php:1-419](file://resources/views/livewire/item-request-form.blade.php#L1-L419)
- [document.blade.php:1-398](file://resources/views/item-requests/document.blade.php#L1-L398)
- [vite.config.js:1-31](file://vite.config.js#L1-L31)
- [web.php:1-18](file://routes/web.php#L1-L18)
- [app.php:1-127](file://config/app.php#L1-L127)

## Architecture Overview
The layout architecture follows a clear separation between shared presentation and feature-specific views:

- Global layout (`public.blade.php`) provides consistent branding, simplified navigation, and content container.
- Feature views like the item request form compose UI using Tailwind utilities and integrate with Livewire for interactivity.
- Print/PDF views isolate print-specific styling and behavior.
- Assets are centrally managed via Vite and Tailwind, ensuring consistent fonts, styles, and scripts across all pages.

```mermaid
graph TB
Browser["Browser"]
Route["Web Routes"]
Controller["Controllers / Livewire Components"]
ViewLayout["Public Layout Template"]
ViewFeature["Feature Views"]
AssetPipeline["Vite + Tailwind"]
Styles["Tailwind CSS"]
Scripts["Application JS"]
Fonts["Injected Fonts"]
Browser --> Route
Route --> Controller
Controller --> ViewLayout
Controller --> ViewFeature
ViewLayout --> AssetPipeline
AssetPipeline --> Styles
AssetPipeline --> Scripts
AssetPipeline --> Fonts
```

[No sources needed since this diagram shows conceptual workflow, not actual code structure]

## Detailed Component Analysis

### Public Layout Template
The public layout template establishes the foundational structure for public-facing pages:

- HTML language and direction are derived from the application locale.
- Meta tags include charset, viewport, and theme color for mobile browsers.
- Title generation supports an optional `$title` variable and falls back to the configured application name.
- Favicon is linked from the public directory.
- Fonts are injected via a Blade directive provided by the Vite plugin.
- CSS and JS assets are loaded through Vite, including both styles and scripts.
- Livewire styles and scripts are included for interactive components.
- Header contains brand identity with simplified navigation (no admin panel link or status indicator).
- Main content area uses a flexible container with responsive padding and optimized width constraints for better readability.
- Footer includes minimal copyright information only.

**Updated** Simplified header structure by removing admin panel link and system status indicator, reduced navigation height to h-14, adjusted main content width to max-w-3xl for improved readability, and streamlined footer to show only copyright notice.

```mermaid
flowchart TD
Start(["Render public.blade.php"]) --> Head["Head Section<br/>Meta tags, title, favicon, fonts, assets"]
Head --> Body["Body Shell<br/>Theme colors, typography, flex layout"]
Body --> Header["Simplified Sticky Header<br/>Brand logo only (no admin/status links)"]
Header --> Main["Main Content Slot<br/>{{ $slot }} - Optimized width max-w-3xl"]
Main --> Footer["Minimal Footer<br/>Copyright only"]
Footer --> End(["Page Ready"])
```

**Diagram sources**
- [public.blade.php:1-52](file://resources/views/layouts/public.blade.php#L1-L52)

**Section sources**
- [public.blade.php:1-52](file://resources/views/layouts/public.blade.php#L1-L52)

### Meta Tags and Title Configuration
- Charset and viewport meta tags ensure correct rendering and responsiveness.
- Theme color meta tag sets the mobile browser toolbar color.
- Title dynamically combines a page-specific title with the application name when available.
- Application name is sourced from configuration, defaulting to a fallback value.

**Section sources**
- [public.blade.php:1-14](file://resources/views/layouts/public.blade.php#L1-L14)
- [app.php:15-16](file://config/app.php#L15-L16)

### Asset Loading Patterns
- CSS and JS are compiled and served via Vite.
- Tailwind CSS is imported and configured with custom font theme.
- Fonts are injected using the Bunny Fonts integration within Vite.
- Livewire styles and scripts are included to support interactive components.

```mermaid
sequenceDiagram
participant Page as "Blade Page"
participant Vite as "Vite Plugin"
participant Tailwind as "Tailwind CSS"
participant Fonts as "Bunny Fonts"
participant Browser as "Browser"
Page->>Vite : @vite(['resources/css/app.css', 'resources/js/app.js'])
Vite->>Tailwind : Compile Tailwind utilities
Vite->>Fonts : Inject selected font weights
Tailwind-->>Browser : CSS bundle
Fonts-->>Browser : Font files
Vite-->>Browser : JS bundle
```

**Diagram sources**
- [public.blade.php:12-16](file://resources/views/layouts/public.blade.php#L12-L16)
- [app.css:1-11](file://resources/css/app.css#L1-L11)
- [vite.config.js:1-31](file://vite.config.js#L1-L31)

**Section sources**
- [public.blade.php:12-16](file://resources/views/layouts/public.blade.php#L12-L16)
- [app.css:1-11](file://resources/css/app.css#L1-L11)
- [vite.config.js:1-31](file://vite.config.js#L1-L31)

### Navigation Components
- Brand logo and tagline are presented in the simplified header with responsive visibility toggles.
- Admin panel link and system status indicator have been removed for a cleaner interface.
- Navigation elements use Tailwind utility classes for spacing, typography, and focus states.
- Header height reduced to h-14 for a more compact appearance.

**Updated** Removed admin panel link and system status indicator from header, reducing visual clutter and focusing on brand identity.

```mermaid
flowchart TD
HeaderStart["Simplified Header Container"] --> Brand["Brand Link<br/>Logo + Tagline Only"]
Brand --> ResponsiveCheck{"Screen Size?"}
ResponsiveCheck --> |Small| HideTagline["Hide Subtitle"]
ResponsiveCheck --> |Large| ShowTagline["Show Subtitle"]
```

**Diagram sources**
- [public.blade.php:19-38](file://resources/views/layouts/public.blade.php#L19-L38)

**Section sources**
- [public.blade.php:19-38](file://resources/views/layouts/public.blade.php#L19-L38)

### Child Views and Sections
- The public layout exposes a `$slot` for child content.
- Child views can extend the layout using Blade's `@extends` directive and populate sections using `@section` and `@endsection`.
- In this repository, the welcome page is a standalone template rather than extending the layout, while the item request form and document view are feature-specific templates that may be rendered by controllers or Livewire components.

```mermaid
flowchart TD
Parent["public.blade.php<br/>Defines $slot"] --> Child["Child View<br/>Extends layout via @extends"]
Child --> Section["Define section via @section('content')"]
Section --> Rendered["Content injected into $slot"]
```

[No sources needed since this diagram shows conceptual workflow, not actual code structure]

**Section sources**
- [public.blade.php:41-43](file://resources/views/layouts/public.blade.php#L41-L43)
- [welcome.blade.php:1-489](file://resources/views/welcome.blade.php#L1-L489)
- [item-request-form.blade.php:1-419](file://resources/views/livewire/item-request-form.blade.php#L1-L419)
- [document.blade.php:1-398](file://resources/views/item-requests/document.blade.php#L1-L398)

### Responsive Design Implementation
- The layout uses Tailwind's responsive prefixes (e.g., `sm:`, `lg:`) to adjust padding, visibility, and layout behavior across screen sizes.
- The header hides certain elements on small screens and reveals them on larger screens.
- Main content container uses optimized width constraints (max-w-3xl) for better readability.
- The item request form uses grid layouts and responsive spacing to accommodate various devices.

**Updated** Adjusted main content width to max-w-3xl for improved readability and reduced navigation height to h-14 for a more compact interface.

```mermaid
flowchart TD
Breakpoints["Tailwind Breakpoints"] --> Small["Small Screens<br/>Simplified Header, Hidden Subtitles"]
Breakpoints --> Medium["Medium Screens<br/>Optimized Padding, Visible Elements"]
Breakpoints --> Large["Large Screens<br/>Compact Navigation, Readable Content Width"]
```

**Diagram sources**
- [public.blade.php:20-38](file://resources/views/layouts/public.blade.php#L20-L38)
- [item-request-form.blade.php:1-419](file://resources/views/livewire/item-request-form.blade.php#L1-L419)

**Section sources**
- [public.blade.php:20-38](file://resources/views/layouts/public.blade.php#L20-L38)
- [item-request-form.blade.php:1-419](file://resources/views/livewire/item-request-form.blade.php#L1-L419)

### Accessibility Features
- Semantic HTML elements (`<header>`, `<main>`, `<footer>`) improve document structure.
- Links include descriptive titles where appropriate.
- SVG icons use `aria-hidden="true"` to avoid redundant announcements.
- Form inputs include labels and error messages for better assistive technology support.
- Focus states are styled for keyboard navigation.

**Section sources**
- [public.blade.php:19-38](file://resources/views/layouts/public.blade.php#L19-L38)
- [item-request-form.blade.php:192-292](file://resources/views/livewire/item-request-form.blade.php#L192-L292)

### Integration with Tailwind CSS Classes
- The layout and feature views extensively use Tailwind utility classes for layout, spacing, typography, colors, and shadows.
- Custom theme configuration defines the sans font stack and source paths for Tailwind scanning.
- Vite compiles Tailwind utilities and injects fonts during development and production builds.

**Section sources**
- [app.css:1-11](file://resources/css/app.css#L1-L11)
- [vite.config.js:1-31](file://vite.config.js#L1-L31)
- [public.blade.php:18-48](file://resources/views/layouts/public.blade.php#L18-L48)
- [item-request-form.blade.php:1-419](file://resources/views/livewire/item-request-form.blade.php#L1-L419)

### Footer Design
- Minimal footer design with only copyright information displayed.
- Uses subtle border styling and small text size for unobtrusive presence.
- Centered alignment with appropriate spacing.

**Updated** Streamlined footer to show only copyright notice, removing additional informational text for a cleaner design.

**Section sources**
- [public.blade.php:45-48](file://resources/views/layouts/public.blade.php#L45-L48)

## Dependency Analysis
The layout system depends on several interconnected components:

- Blade templates render HTML and integrate with Laravel services.
- Vite compiles assets and injects fonts.
- Tailwind CSS provides utility classes.
- Livewire enables interactive components without full page reloads.
- Routes connect URLs to controllers and Livewire components.

```mermaid
graph TB
Blade["Blade Templates"]
Vite["Vite Build Pipeline"]
Tailwind["Tailwind CSS"]
Livewire["Livewire Components"]
Routes["Web Routes"]
Config["App Configuration"]
Blade --> Vite
Blade --> Livewire
Vite --> Tailwind
Routes --> Blade
Config --> Blade
```

**Diagram sources**
- [public.blade.php:1-52](file://resources/views/layouts/public.blade.php#L1-L52)
- [vite.config.js:1-31](file://vite.config.js#L1-L31)
- [web.php:1-18](file://routes/web.php#L1-L18)
- [app.php:1-127](file://config/app.php#L1-L127)

**Section sources**
- [public.blade.php:1-52](file://resources/views/layouts/public.blade.php#L1-L52)
- [vite.config.js:1-31](file://vite.config.js#L1-L31)
- [web.php:1-18](file://routes/web.php#L1-L18)
- [app.php:1-127](file://config/app.php#L1-L127)

## Performance Considerations
- Use Vite's hot module replacement for faster development cycles.
- Keep Tailwind utility usage consistent to minimize CSS bloat.
- Avoid inline styles in Blade templates; prefer Tailwind classes.
- Leverage Livewire's incremental updates to reduce full page reloads.
- Optimize images and fonts; consider lazy loading where appropriate.
- Simplified header structure reduces DOM complexity and improves initial render performance.

[No sources needed since this section provides general guidance]

## Troubleshooting Guide
- If assets do not load, verify Vite server is running and ports match the configuration.
- If fonts are missing, confirm the Bunny Fonts integration is correctly configured in Vite.
- If Livewire components fail to initialize, check that Livewire styles and scripts are included in the layout.
- If responsive styles are not applied, ensure Tailwind is scanning Blade files and storage/framework/views.
- If navigation appears too tall, verify the header height class is set to h-14.

**Section sources**
- [vite.config.js:1-31](file://vite.config.js#L1-L31)
- [app.css:1-11](file://resources/css/app.css#L1-L11)
- [public.blade.php:12-16](file://resources/views/layouts/public.blade.php#L12-L16)

## Conclusion
The Blade layout system in this project provides a robust foundation for public-facing pages. The public layout template centralizes common structure, metadata, simplified navigation, and asset loading, while feature-specific views compose their interfaces using Tailwind utilities and Livewire interactivity. The recent simplifications to the header structure, reduced navigation height, optimized content width, and minimal footer design create a cleaner, more focused user experience. The asset pipeline ensures consistent styling and performance, and the routing layer connects user interactions to the appropriate views and components. Following the patterns documented here will help maintain consistency, accessibility, and scalability across the application.

[No sources needed since this section summarizes without analyzing specific files]

## Appendices

### Creating New Layouts
- Create a new Blade file under `resources/views/layouts/`.
- Define the HTML shell, head section, body shell, and content slot.
- Include necessary meta tags, fonts, Vite assets, and Livewire scripts.
- Add simplified navigation and minimal footer components as needed.
- Reference the new layout in child views using Blade's `@extends` directive.

**Section sources**
- [public.blade.php:1-52](file://resources/views/layouts/public.blade.php#L1-L52)

### Extending Existing Layouts
- Use `@extends('layouts.public')` in child views.
- Define content sections with `@section('content')` and close with `@endsection`.
- Pass variables to the layout via controller or view composition.
- Ensure child views inherit Tailwind classes and asset loading from the parent layout.

**Section sources**
- [public.blade.php:41-43](file://resources/views/layouts/public.blade.php#L41-L43)