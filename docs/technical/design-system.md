# Design System and UI Customization

## Table of contents

- [Design System and UI Customization](#design-system-and-ui-customization)
  - [Table of contents](#table-of-contents)
  - [Frontend styling model](#frontend-styling-model)
  - [CSS source files](#css-source-files)
  - [Component patterns](#component-patterns)
  - [Brand-aware styling](#brand-aware-styling)
  - [Custom CSS override mode](#custom-css-override-mode)
    - [How it works](#how-it-works)
    - [When to use it](#when-to-use-it)
    - [Best practices](#best-practices)
  - [Example CSS override file](#example-css-override-file)
  - [Build workflow](#build-workflow)

---

## Frontend styling model

FORMNA uses compiled Tailwind CSS v4 with three practical layers:

| Layer | Purpose |
|---|---|
| base | element-level defaults for tables, inputs, labels, and common HTML structures |
| components | semantic classes such as cards, buttons, badges, and alerts |
| utilities | targeted inline Tailwind utilities for layout and exceptions |

Utilities override base and component layers by design.

---

## CSS source files

```text
resources/css/
├── app.src.css
├── _base.css
├── _components.css
├── _fonts.css
└── _rtl.css
```

Common documented responsibilities:

- `app.src.css`: entry point, tokens, imports, and source scanning
- `_base.css`: default element styling
- `_components.css`: reusable semantic component classes
- `_fonts.css`: self-hosted font declarations

---

## Component patterns

The legacy documentation defines common semantic classes such as:

- `page-title`, `section-title`, `help-text`
- `card`, `card-header`, `card-body`, `card-footer`
- `btn`, `btn-primary`, `btn-secondary`, `btn-danger`
- `badge` and color variants
- `alert` variants
- `nav-link`, `filter-tab`, `empty-state`

The goal is to keep templates readable while still allowing inline utility overrides where needed.

---

## Brand-aware styling

Prefer `primary-*` utilities and brand-aware semantic classes for UI that should follow the configured organization brand color.

Examples:

```text
bg-primary-600
text-primary-600
border-primary-500
hover:bg-primary-700
focus:ring-primary-500
```

---

## Custom CSS override mode

FORMNA also supports an advanced **full CSS override** mode.

### How it works

If `public/uploads/branding/custom.css` exists:

- the branding UI is effectively bypassed for style changes
- the platform loads the custom stylesheet after the compiled application CSS
- default indigo/primary fallback classes are used
- super admins retain file-based control over complete styling override behavior

### When to use it

Use override mode when an organization needs:

- strict brand guideline compliance
- major visual redesigns
- custom typography or theme systems
- advanced layout tweaks beyond the standard branding UI

### Best practices

- test on desktop and mobile
- maintain accessible color contrast
- keep selectors simple when possible
- document your overrides for future maintainers

## Example CSS override file

```css
/**
 * FORMNA custom CSS override example
 *
 * Copy or rename this file to:
 * public/uploads/branding/custom.css
 *
 * When that file exists, FORMNA loads it after the main application CSS so
 * organization-specific overrides can take control of the UI.
 */

/* Example: replace the primary indigo color with a custom green */
/*
.bg-indigo-600 {
    background-color: #10b981 !important;
}

.hover\:bg-indigo-700:hover {
    background-color: #059669 !important;
}

.text-indigo-600 {
    color: #10b981 !important;
}
*/

/* Example: adjust body typography */
/*
body {
    font-family: Inter, system-ui, sans-serif !important;
}
*/

/* Add organization-specific styles below */
```

---

## Build workflow

Run these from the project root:

```bash
npm run css:build
npm run css:watch
```

The compiled `public/css/app.css` is intended to be committed and deployed as built output, so production servers do not require a CSS build step.
