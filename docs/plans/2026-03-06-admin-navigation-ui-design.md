# Admin Navigation & UI Redesign — Design Document
**Date:** 2026-03-06
**Scope:** Admin flow (Dashboard → Reuniones → Conducción) + Welcome + Login

---

## Context

The app currently has minimal layouts with a flat top navbar for admin and a bare header for copropietario sala. There is no proper role-based post-login redirect, no SuperAdmin layout, no active link states, and no mobile navigation. The goal is to establish a professional, production-grade UI for the admin flow.

## Design Direction

**Hybrid: Dark Sidebar + Light Content**

- Sidebar: deep dark (`#0c111d`) with brand accent indicators
- Content area: off-white (`#f4f6fb`) with white card surfaces
- Guest pages (Welcome, Login): dark background with geometric texture
- CSS custom properties as the single source of truth for all colors

## Color System

All colors defined in `resources/css/admin-theme.css` as CSS custom properties on `:root`. Changing the palette requires editing only this file.

```css
:root {
  /* Brand */
  --color-brand:        #2563eb;
  --color-brand-dark:   #1d4ed8;
  --color-brand-light:  #eff6ff;
  --color-brand-glow:   #3b82f620;

  /* Sidebar */
  --sidebar-bg:         #0c111d;
  --sidebar-border:     #1a2234;
  --sidebar-item-hover: #161e30;
  --sidebar-item-active:#1e2f50;
  --sidebar-text:       #8899b3;
  --sidebar-text-active:#e2e8f0;
  --sidebar-accent:     var(--color-brand);
  --sidebar-width:      256px;

  /* Content */
  --content-bg:         #f4f6fb;
  --surface:            #ffffff;
  --surface-border:     #e4e9f2;
  --surface-hover:      #f8faff;

  /* Text */
  --text-primary:       #0d1526;
  --text-secondary:     #556070;
  --text-muted:         #8a95a8;

  /* Status */
  --color-success:      #10b981;
  --color-danger:       #ef4444;
  --color-warning:      #f59e0b;
  --color-info:         #06b6d4;

  /* Typography */
  --font-display:       'DM Sans', sans-serif;
  --font-body:          'DM Sans', sans-serif;
  --font-mono:          'JetBrains Mono', monospace;
}
```

## Typography

- **DM Sans** — display + body (geometric, modern, not overused)
- **JetBrains Mono** — data values: coeficientes, SHA-256 hashes, timestamps
- Loaded via Google Fonts in `app.blade.php`

## Layouts

### AdminLayout (dark sidebar + light content)

**Structure:**
```
[Sidebar 256px dark] | [Content area flex-1 light]
                       [Topbar: breadcrumb + user menu]
                       [Page content]
```

**Sidebar items (administrador):**
- ASAMBLI logo with animated pulse dot (green when reunion en_curso)
- Dashboard
- Reuniones
- Padrón
- Divider
- User avatar + name + role at bottom
- Logout button

**Sidebar items (super_admin):**
- ASAMBLI logo
- Conjuntos (tenants)
- Divider
- User info + logout

**Active state:** 3px left border in `--color-brand` + `--sidebar-item-active` background + `--sidebar-text-active` text color

**Hover:** `--sidebar-item-hover` background, `200ms ease` transition

**Mobile (< 1024px):**
- Sidebar hidden by default
- Hamburger button in topbar
- Sidebar slides in from left with dark overlay
- Close on overlay click or nav link click

### GuestLayout (dark, centered card)

Used by: Login, Register, ForgotPassword, ResetPassword, VerifyEmail

- Full-viewport dark background (`--sidebar-bg`)
- Subtle dot-grid or mesh gradient overlay (CSS only)
- Centered card with `backdrop-blur`, glowing border, smooth shadow
- ASAMBLI wordmark above card

### Welcome Page

- Full dark hero (same dark as sidebar)
- Animated gradient mesh background (CSS keyframes)
- Large bold headline: "Gestión de Asambleas de Propiedad Horizontal"
- Subheadline: "Votaciones en tiempo real. Quórum dinámico. Reportes auditables."
- CTA button: "Iniciar sesión →"
- Features row (3 cards): Quórum en Tiempo Real · Votaciones Seguras · Auditoría SHA-256
- Minimal top nav: logo left, login button right

## Transitions & Motion

- Sidebar items: `transition: background-color 200ms ease, color 200ms ease`
- Page content: Inertia.js handles SPA transitions (no full reload)
- Mobile drawer: `transform: translateX(-100%)` → `translateX(0)` with `300ms cubic-bezier(0.4, 0, 0.2, 1)`
- Card hover: `transform: translateY(-2px)` + shadow deepening, `200ms ease`
- Status badges: no animation (data clarity over decoration)
- Welcome hero gradient: `@keyframes gradientShift` 8s infinite

## Files to Create / Modify

| File | Action |
|------|--------|
| `resources/css/admin-theme.css` | CREATE — CSS custom properties |
| `resources/css/app.css` | MODIFY — import admin-theme.css, add Google Fonts |
| `resources/js/Layouts/AdminLayout.jsx` | REWRITE — full sidebar + drawer |
| `resources/js/Layouts/GuestLayout.jsx` | REWRITE — dark card layout |
| `resources/js/Pages/Welcome.jsx` | REWRITE — dark hero landing |
| `resources/js/Pages/Auth/Login.jsx` | REWRITE — redesigned login |
| `resources/js/Pages/SuperAdmin/Tenants/Index.jsx` | MODIFY — use sidebar role detection |

## Implementation Notes

- `usePage().props.auth.user.roles` used to determine sidebar items per role
- No SuperAdminLayout created separately — AdminLayout reads role and renders correct nav items
- All hardcoded hex colors replaced with CSS variables
- `route()` Ziggy helper used for all hrefs (already available via Breeze)
