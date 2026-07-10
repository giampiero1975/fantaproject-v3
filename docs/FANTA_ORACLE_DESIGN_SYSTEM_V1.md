# FANTA ORACLE DESIGN SYSTEM v1

**Status:** Approved baseline  
**Scope:** Fanta Oracle V3 Control Center and connected product interfaces

## 1. Purpose

This document defines the stable visual foundation of Fanta Oracle V3. New pages and components must reuse the shared theme and component library instead of introducing isolated styles.

## 2. Official brand palette

| Token | Hex | Primary use |
|---|---:|---|
| Primary Purple | `#7B2CFF` | Primary actions, Oracle identity, focus and highlights |
| Primary Blue | `#2962FF` | Secondary accents, links, charts and informational states |
| Corporate Blue | `#0D1B3D` | Core dark surface, navigation, corporate text and brand foundation |

The three official colors must never be redefined outside the central theme file.

Derived UI colors are allowed only when generated from the official palette for backgrounds, elevated panels, borders and soft states.

## 3. Typography

**Official typeface:** Poppins

- Regular 400: body copy, descriptions and secondary information
- Medium 500: navigation, controls and labels
- SemiBold 600: headings, KPI values and important actions

Avoid additional font families unless a future product requirement is formally approved.

## 4. Interface stack

- Laravel 12
- Jetstream / Fortify for authentication and account features
- Spatie Permission for roles and permissions
- Livewire 3 for interactive application logic
- Flux UI 2 for interface primitives
- Tailwind CSS 4 for design tokens and composition

Each layer has a distinct responsibility. UI components must not duplicate authentication or authorization logic.

## 5. Layout shell

The authenticated application uses:

- persistent desktop sidebar;
- responsive mobile header;
- main content area with consistent horizontal and vertical spacing;
- user profile control at the bottom of the sidebar;
- macro navigation groups closed by default.

Macro navigation headings are fixed labels. They do not change background or color on hover. Child links provide the interactive rollover state.

## 6. Navigation behavior

### Macro groups

- Administration
- Diagnostics
- Operations

Rules:

- closed by default;
- opened only by explicit user action;
- heading color remains stable;
- no decorative hover effect on headings;
- child links use white text and a subtle light surface on hover;
- the current page receives the strongest visual state.

## 7. Reusable components

Approved base components:

- `<x-fo.card>`
- `<x-fo.panel>`
- `<x-fo.stat>`
- `<x-fo.page-header>`

Future reusable components should follow the same `fo-*` naming convention.

Pages should not repeat large blocks of Tailwind classes when a shared component is appropriate.

## 8. Surfaces, borders and elevation

- Base background derives from Corporate Blue.
- Sidebar and primary navigation use Corporate Blue as the principal surface.
- Cards and panels use approved derived elevations.
- Borders remain subtle and cool-toned.
- Shadows are soft, dark and functional; avoid ornamental glow except for deliberate Oracle highlights.

## 9. Interaction states

- Primary action: Primary Purple
- Secondary action / information: Primary Blue
- Hover on navigation child: white text and subtle white surface
- Focus: clearly visible and keyboard accessible
- Success: green
- Warning: amber
- Danger: red

Semantic state colors are functional additions and do not replace the three official brand colors.

## 10. Spacing and shape

- Default component radius: `1rem`
- Compact controls may use smaller radii.
- Card padding must remain consistent across equivalent components.
- Dense dashboards should rely on grid hierarchy, not reduced readability.

## 11. Accessibility

- Maintain readable contrast on dark surfaces.
- Never communicate status using color alone.
- Preserve visible keyboard focus.
- Use semantic headings in page order.
- Interactive targets must remain usable on mobile.

## 12. Governance rules

1. No new hardcoded brand colors inside Blade pages.
2. Official colors and derived tokens live in `resources/css/app.css`.
3. Poppins is the official application font.
4. New registrations receive the base `user` role; elevated roles are assigned through controlled administration workflows.
5. New pages reuse Flux and `fo-*` components before creating custom markup.
6. Any change to palette, typography or core shell requires an update to this document.

## 13. Approved baseline

The UI foundation is considered stable after:

- official palette alignment;
- Poppins adoption;
- Flux sidebar and responsive shell;
- closed macro navigation groups;
- fixed macro labels;
- standardized child-link hover states;
- reusable Fanta Oracle components;
- centralized theme tokens.

From this baseline onward, development should focus on real product features and domain workflows.
