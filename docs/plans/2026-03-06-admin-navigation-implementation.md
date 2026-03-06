# Admin Navigation & UI Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Reemplazar los layouts genéricos de Laravel con un sistema de diseño profesional: sidebar oscuro + contenido claro para Admin, pantalla oscura con card para Auth, y landing page propia para Welcome.

**Architecture:** CSS custom properties en un único archivo de tema → importado en app.css → consumido por todos los layouts JSX. AdminLayout usa React state para el drawer móvil. No se crea SuperAdminLayout separado — AdminLayout detecta el rol del usuario para renderizar los ítems de navegación correctos.

**Tech Stack:** React 18, Inertia.js, Tailwind CSS, CSS Custom Properties, DM Sans + JetBrains Mono (Google Fonts via bunny.net)

---

### Task 1: CSS Theme Foundation

**Files:**
- Create: `resources/css/admin-theme.css`
- Modify: `resources/css/app.css`
- Modify: `resources/views/app.blade.php`

**Step 1: Crear admin-theme.css con todas las variables de color**

```css
/* resources/css/admin-theme.css */
@import url('https://fonts.bunny.net/css?family=dm-sans:400,500,600,700&display=swap');
@import url('https://fonts.bunny.net/css?family=jetbrains-mono:400,500&display=swap');

:root {
  /* ── Brand ─────────────────────────────── */
  --color-brand:         #2563eb;
  --color-brand-dark:    #1d4ed8;
  --color-brand-light:   #eff6ff;
  --color-brand-glow:    rgba(59, 130, 246, 0.12);

  /* ── Sidebar (dark) ─────────────────────── */
  --sidebar-bg:          #0c111d;
  --sidebar-border:      #1a2234;
  --sidebar-item-hover:  #161e30;
  --sidebar-item-active: #1e3055;
  --sidebar-text:        #7a8fa8;
  --sidebar-text-active: #e8edf5;
  --sidebar-accent:      var(--color-brand);
  --sidebar-width:       256px;

  /* ── Content (light) ────────────────────── */
  --content-bg:          #f4f6fb;
  --surface:             #ffffff;
  --surface-border:      #e4e9f2;
  --surface-hover:       #f8faff;

  /* ── Text ───────────────────────────────── */
  --text-primary:        #0d1526;
  --text-secondary:      #556070;
  --text-muted:          #8a95a8;

  /* ── Status ─────────────────────────────── */
  --color-success:       #10b981;
  --color-success-bg:    #ecfdf5;
  --color-danger:        #ef4444;
  --color-danger-bg:     #fef2f2;
  --color-warning:       #f59e0b;
  --color-warning-bg:    #fffbeb;
  --color-info:          #06b6d4;
  --color-info-bg:       #ecfeff;

  /* ── Typography ─────────────────────────── */
  --font-display:        'DM Sans', sans-serif;
  --font-body:           'DM Sans', sans-serif;
  --font-mono:           'JetBrains Mono', monospace;

  /* ── Radii & Shadows ────────────────────── */
  --radius-sm:           6px;
  --radius-md:           10px;
  --radius-lg:           14px;
  --shadow-sm:           0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
  --shadow-md:           0 4px 16px rgba(0,0,0,0.08), 0 2px 4px rgba(0,0,0,0.04);
  --shadow-lg:           0 10px 40px rgba(0,0,0,0.12), 0 4px 8px rgba(0,0,0,0.06);
}
```

**Step 2: Actualizar app.css para importar el tema**

```css
/* resources/css/app.css */
@import './admin-theme.css';

@tailwind base;
@tailwind components;
@tailwind utilities;

@layer base {
  html {
    font-family: var(--font-body);
  }
}
```

**Step 3: Actualizar app.blade.php — reemplazar Figtree por DM Sans**

En `resources/views/app.blade.php`, reemplazar la línea del font de Figtree:

```html
<!-- Fonts — REEMPLAZAR esta línea: -->
<link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

<!-- CON estas dos líneas: -->
<link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700&display=swap" rel="stylesheet" />
<link href="https://fonts.bunny.net/css?family=jetbrains-mono:400,500&display=swap" rel="stylesheet" />
```

**Step 4: Verificar que el build no falla**

```bash
./sail npm run build
```

Esperado: compilación exitosa sin errores CSS.

**Step 5: Commit**

```bash
git add resources/css/admin-theme.css resources/css/app.css resources/views/app.blade.php
git commit -m "feat: add CSS theme system with custom properties and DM Sans font"
```

---

### Task 2: AdminLayout — Sidebar + Drawer

**Files:**
- Modify: `resources/js/Layouts/AdminLayout.jsx`

**Context:** AdminLayout es usado por TODAS las páginas de admin y super-admin. Detecta el rol del usuario vía `auth.user` para mostrar los ítems de navegación correctos. El sidebar tiene 256px en desktop. En móvil (< 1024px) se oculta y se activa mediante un botón hamburger que muestra un drawer con overlay.

**Step 1: Reescribir AdminLayout.jsx**

```jsx
// resources/js/Layouts/AdminLayout.jsx
import { useState, useEffect } from 'react'
import { Link, usePage, router } from '@inertiajs/react'

const NAV_ADMIN = [
    {
        href: '/admin/dashboard',
        label: 'Dashboard',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
            </svg>
        ),
    },
    {
        href: '/admin/reuniones',
        label: 'Reuniones',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
        ),
    },
    {
        href: '/admin/padron',
        label: 'Padrón',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        ),
    },
]

const NAV_SUPERADMIN = [
    {
        href: '/super-admin/tenants',
        label: 'Conjuntos',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
        ),
    },
]

function NavItem({ href, label, icon, onClick }) {
    const { url } = usePage()
    const isActive = url.startsWith(href)

    return (
        <Link
            href={href}
            onClick={onClick}
            style={{
                display: 'flex',
                alignItems: 'center',
                gap: '10px',
                padding: '9px 14px',
                borderRadius: 'var(--radius-md)',
                fontSize: '14px',
                fontWeight: isActive ? '600' : '500',
                color: isActive ? 'var(--sidebar-text-active)' : 'var(--sidebar-text)',
                background: isActive ? 'var(--sidebar-item-active)' : 'transparent',
                borderLeft: isActive ? '3px solid var(--sidebar-accent)' : '3px solid transparent',
                textDecoration: 'none',
                transition: 'all 200ms ease',
                marginBottom: '2px',
            }}
            onMouseEnter={e => {
                if (!isActive) {
                    e.currentTarget.style.background = 'var(--sidebar-item-hover)'
                    e.currentTarget.style.color = 'var(--sidebar-text-active)'
                }
            }}
            onMouseLeave={e => {
                if (!isActive) {
                    e.currentTarget.style.background = 'transparent'
                    e.currentTarget.style.color = 'var(--sidebar-text)'
                }
            }}
        >
            <span style={{ opacity: isActive ? 1 : 0.7, flexShrink: 0 }}>{icon}</span>
            {label}
        </Link>
    )
}

function Sidebar({ navItems, user, onClose }) {
    const logout = () => router.post('/logout')

    return (
        <div style={{
            width: 'var(--sidebar-width)',
            background: 'var(--sidebar-bg)',
            borderRight: '1px solid var(--sidebar-border)',
            display: 'flex',
            flexDirection: 'column',
            height: '100vh',
            position: 'sticky',
            top: 0,
            flexShrink: 0,
        }}>
            {/* Logo */}
            <div style={{
                padding: '24px 20px 20px',
                borderBottom: '1px solid var(--sidebar-border)',
            }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    <div style={{
                        width: '32px', height: '32px',
                        background: 'var(--color-brand)',
                        borderRadius: '8px',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        flexShrink: 0,
                    }}>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </div>
                    <span style={{
                        fontFamily: 'var(--font-display)',
                        fontWeight: '700',
                        fontSize: '17px',
                        color: '#e8edf5',
                        letterSpacing: '-0.3px',
                    }}>
                        ASAMBLI
                    </span>
                </div>
            </div>

            {/* Nav items */}
            <nav style={{ flex: 1, padding: '16px 12px', overflowY: 'auto' }}>
                {navItems.map(item => (
                    <NavItem key={item.href} {...item} onClick={onClose} />
                ))}
            </nav>

            {/* User footer */}
            <div style={{
                padding: '16px 16px 20px',
                borderTop: '1px solid var(--sidebar-border)',
            }}>
                <div style={{
                    display: 'flex', alignItems: 'center', gap: '10px',
                    marginBottom: '12px',
                }}>
                    <div style={{
                        width: '34px', height: '34px',
                        borderRadius: '50%',
                        background: 'var(--sidebar-item-active)',
                        border: '1px solid var(--sidebar-border)',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                        flexShrink: 0,
                        color: 'var(--sidebar-text-active)',
                        fontWeight: '600',
                        fontSize: '13px',
                    }}>
                        {user?.name?.charAt(0)?.toUpperCase() ?? '?'}
                    </div>
                    <div style={{ overflow: 'hidden' }}>
                        <p style={{
                            color: 'var(--sidebar-text-active)',
                            fontSize: '13px',
                            fontWeight: '600',
                            whiteSpace: 'nowrap',
                            overflow: 'hidden',
                            textOverflow: 'ellipsis',
                        }}>
                            {user?.name}
                        </p>
                        <p style={{
                            color: 'var(--sidebar-text)',
                            fontSize: '11px',
                            textTransform: 'capitalize',
                        }}>
                            {user?.roles?.[0]?.name ?? 'Usuario'}
                        </p>
                    </div>
                </div>
                <button
                    onClick={logout}
                    style={{
                        width: '100%',
                        padding: '7px 12px',
                        borderRadius: 'var(--radius-sm)',
                        border: '1px solid var(--sidebar-border)',
                        background: 'transparent',
                        color: 'var(--sidebar-text)',
                        fontSize: '12px',
                        fontWeight: '500',
                        cursor: 'pointer',
                        display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '6px',
                        transition: 'all 200ms ease',
                        fontFamily: 'var(--font-body)',
                    }}
                    onMouseEnter={e => {
                        e.currentTarget.style.background = 'var(--color-danger-bg)'
                        e.currentTarget.style.borderColor = 'var(--color-danger)'
                        e.currentTarget.style.color = 'var(--color-danger)'
                    }}
                    onMouseLeave={e => {
                        e.currentTarget.style.background = 'transparent'
                        e.currentTarget.style.borderColor = 'var(--sidebar-border)'
                        e.currentTarget.style.color = 'var(--sidebar-text)'
                    }}
                >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Cerrar sesión
                </button>
            </div>
        </div>
    )
}

export default function AdminLayout({ children, title }) {
    const { auth } = usePage().props
    const [drawerOpen, setDrawerOpen] = useState(false)

    const isSuperAdmin = auth?.user?.roles?.some(r => r.name === 'super_admin')
    const navItems = isSuperAdmin ? NAV_SUPERADMIN : NAV_ADMIN

    // Cerrar drawer al cambiar de ruta
    useEffect(() => {
        setDrawerOpen(false)
    }, [])

    return (
        <div style={{
            display: 'flex',
            minHeight: '100vh',
            background: 'var(--content-bg)',
            fontFamily: 'var(--font-body)',
        }}>
            {/* Sidebar desktop */}
            <div style={{ display: 'none' }} className="lg-sidebar">
                <Sidebar navItems={navItems} user={auth?.user} />
            </div>

            {/* Mobile overlay */}
            {drawerOpen && (
                <div
                    onClick={() => setDrawerOpen(false)}
                    style={{
                        position: 'fixed', inset: 0,
                        background: 'rgba(0,0,0,0.6)',
                        zIndex: 40,
                        backdropFilter: 'blur(2px)',
                        animation: 'fadeIn 200ms ease',
                    }}
                />
            )}

            {/* Mobile drawer */}
            <div style={{
                position: 'fixed', top: 0, left: 0, bottom: 0,
                zIndex: 50,
                transform: drawerOpen ? 'translateX(0)' : 'translateX(-100%)',
                transition: 'transform 300ms cubic-bezier(0.4, 0, 0.2, 1)',
            }} className="lg-drawer-hidden">
                <Sidebar navItems={navItems} user={auth?.user} onClose={() => setDrawerOpen(false)} />
            </div>

            {/* Main */}
            <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
                {/* Topbar */}
                <header style={{
                    height: '56px',
                    background: 'var(--surface)',
                    borderBottom: '1px solid var(--surface-border)',
                    display: 'flex',
                    alignItems: 'center',
                    padding: '0 24px',
                    gap: '16px',
                    position: 'sticky',
                    top: 0,
                    zIndex: 30,
                    boxShadow: 'var(--shadow-sm)',
                }}>
                    {/* Hamburger (mobile) */}
                    <button
                        onClick={() => setDrawerOpen(true)}
                        className="lg-hidden"
                        style={{
                            padding: '6px',
                            border: 'none',
                            background: 'transparent',
                            cursor: 'pointer',
                            color: 'var(--text-secondary)',
                            borderRadius: 'var(--radius-sm)',
                            display: 'flex',
                        }}
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>
                        </svg>
                    </button>

                    {/* Title / breadcrumb */}
                    {title && (
                        <h1 style={{
                            fontSize: '15px',
                            fontWeight: '600',
                            color: 'var(--text-primary)',
                            margin: 0,
                            flex: 1,
                        }}>
                            {title}
                        </h1>
                    )}
                    {!title && <div style={{ flex: 1 }} />}
                </header>

                {/* Page content */}
                <main style={{ flex: 1, padding: '28px 28px' }}>
                    {children}
                </main>
            </div>

            {/* Responsive styles via <style> tag */}
            <style>{`
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to   { opacity: 1; }
                }

                /* Desktop: mostrar sidebar, ocultar hamburger */
                @media (min-width: 1024px) {
                    .lg-sidebar      { display: flex !important; }
                    .lg-hidden       { display: none !important; }
                    .lg-drawer-hidden { display: none !important; }
                }
                /* Mobile: ocultar sidebar desktop */
                @media (max-width: 1023px) {
                    .lg-sidebar { display: none !important; }
                }
            `}</style>
        </div>
    )
}
```

**Step 2: Build y verificar que carga correctamente**

```bash
./sail npm run build
```

Navegar a `http://localhost/admin/dashboard` (autenticado como administrador). Verificar:
- Sidebar visible en desktop con ítems de nav
- Logo ASAMBLI con ícono en el sidebar
- Topbar con título de página
- Sin errores en consola

**Step 3: Verificar mobile**

Reducir ventana a < 1024px. Verificar:
- Sidebar oculto
- Botón hamburger visible en topbar
- Al hacer click, sidebar aparece con overlay oscuro
- Al hacer click en overlay, sidebar se cierra

**Step 4: Commit**

```bash
git add resources/js/Layouts/AdminLayout.jsx
git commit -m "feat: redesign AdminLayout with dark sidebar, drawer, and CSS variables"
```

---

### Task 3: GuestLayout — Dark Card

**Files:**
- Modify: `resources/js/Layouts/GuestLayout.jsx`

**Context:** GuestLayout envuelve todas las páginas de auth (Login, Register, ForgotPassword, ResetPassword, VerifyEmail). El layout actual es un wrapper simple. El nuevo layout tiene fondo oscuro full-viewport con un grid de puntos decorativo y una card centrada con blur y borde luminoso.

**Step 1: Reescribir GuestLayout.jsx**

```jsx
// resources/js/Layouts/GuestLayout.jsx
import { Link } from '@inertiajs/react'

export default function GuestLayout({ children }) {
    return (
        <div style={{
            minHeight: '100vh',
            background: 'var(--sidebar-bg)',
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '24px 16px',
            fontFamily: 'var(--font-body)',
            position: 'relative',
            overflow: 'hidden',
        }}>
            {/* Dot grid background */}
            <div style={{
                position: 'absolute', inset: 0,
                backgroundImage: 'radial-gradient(circle, #1e2f50 1px, transparent 1px)',
                backgroundSize: '28px 28px',
                opacity: 0.5,
                pointerEvents: 'none',
            }} />

            {/* Gradient glow top */}
            <div style={{
                position: 'absolute', top: '-120px', left: '50%',
                transform: 'translateX(-50%)',
                width: '600px', height: '400px',
                background: 'radial-gradient(ellipse, rgba(37,99,235,0.15) 0%, transparent 70%)',
                pointerEvents: 'none',
            }} />

            {/* Logo */}
            <Link href="/" style={{ textDecoration: 'none', marginBottom: '28px', position: 'relative' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    <div style={{
                        width: '36px', height: '36px',
                        background: 'var(--color-brand)',
                        borderRadius: '9px',
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                    }}>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </div>
                    <span style={{
                        fontWeight: '700',
                        fontSize: '20px',
                        color: '#e8edf5',
                        letterSpacing: '-0.3px',
                    }}>
                        ASAMBLI
                    </span>
                </div>
            </Link>

            {/* Card */}
            <div style={{
                width: '100%',
                maxWidth: '420px',
                background: 'rgba(22, 30, 48, 0.8)',
                backdropFilter: 'blur(12px)',
                border: '1px solid var(--sidebar-border)',
                borderRadius: 'var(--radius-lg)',
                padding: '32px',
                boxShadow: '0 0 0 1px rgba(37,99,235,0.08), var(--shadow-lg)',
                position: 'relative',
            }}>
                {children}
            </div>

            {/* Footer */}
            <p style={{
                marginTop: '24px',
                fontSize: '12px',
                color: 'var(--sidebar-text)',
                position: 'relative',
            }}>
                © {new Date().getFullYear()} ASAMBLI · Gestión de Propiedad Horizontal
            </p>
        </div>
    )
}
```

**Step 2: Build y verificar**

```bash
./sail npm run build
```

Navegar a `http://localhost/login`. Verificar:
- Fondo oscuro con grid de puntos
- Card centrada con blur
- Logo ASAMBLI arriba de la card
- Form de login visible dentro de la card

**Step 3: Commit**

```bash
git add resources/js/Layouts/GuestLayout.jsx
git commit -m "feat: redesign GuestLayout with dark card and dot-grid background"
```

---

### Task 4: Login Page — Formulario Rediseñado

**Files:**
- Modify: `resources/js/Pages/Auth/Login.jsx`

**Context:** Login.jsx usa GuestLayout como wrapper. El formulario actual usa componentes genéricos de Breeze (InputLabel, TextInput, PrimaryButton) con clases Tailwind en inglés. Reescribir el formulario con estilos inline usando CSS variables, manteniendo exactamente la misma lógica de `useForm` y `post(route('login'))`.

**Step 1: Reescribir Login.jsx**

```jsx
// resources/js/Pages/Auth/Login.jsx
import GuestLayout from '@/Layouts/GuestLayout'
import { Head, Link, useForm } from '@inertiajs/react'

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    })

    const submit = (e) => {
        e.preventDefault()
        post(route('login'), { onFinish: () => reset('password') })
    }

    const inputStyle = {
        width: '100%',
        padding: '10px 14px',
        borderRadius: 'var(--radius-sm)',
        border: '1px solid var(--sidebar-border)',
        background: 'rgba(12, 17, 29, 0.6)',
        color: 'var(--sidebar-text-active)',
        fontSize: '14px',
        fontFamily: 'var(--font-body)',
        outline: 'none',
        transition: 'border-color 200ms ease, box-shadow 200ms ease',
        boxSizing: 'border-box',
    }

    const labelStyle = {
        display: 'block',
        fontSize: '13px',
        fontWeight: '500',
        color: 'var(--sidebar-text)',
        marginBottom: '6px',
    }

    return (
        <GuestLayout>
            <Head title="Iniciar sesión" />

            <h2 style={{
                fontSize: '20px',
                fontWeight: '700',
                color: 'var(--sidebar-text-active)',
                marginBottom: '6px',
                letterSpacing: '-0.3px',
            }}>
                Bienvenido de nuevo
            </h2>
            <p style={{
                fontSize: '13px',
                color: 'var(--sidebar-text)',
                marginBottom: '24px',
            }}>
                Ingresa a tu cuenta de ASAMBLI
            </p>

            {status && (
                <div style={{
                    marginBottom: '16px',
                    padding: '10px 14px',
                    borderRadius: 'var(--radius-sm)',
                    background: 'var(--color-success-bg)',
                    border: '1px solid var(--color-success)',
                    color: 'var(--color-success)',
                    fontSize: '13px',
                }}>
                    {status}
                </div>
            )}

            <form onSubmit={submit}>
                {/* Email */}
                <div style={{ marginBottom: '16px' }}>
                    <label style={labelStyle} htmlFor="email">Correo electrónico</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        autoFocus
                        onChange={e => setData('email', e.target.value)}
                        style={inputStyle}
                        onFocus={e => {
                            e.target.style.borderColor = 'var(--color-brand)'
                            e.target.style.boxShadow = '0 0 0 3px var(--color-brand-glow)'
                        }}
                        onBlur={e => {
                            e.target.style.borderColor = 'var(--sidebar-border)'
                            e.target.style.boxShadow = 'none'
                        }}
                    />
                    {errors.email && (
                        <p style={{ marginTop: '5px', fontSize: '12px', color: 'var(--color-danger)' }}>
                            {errors.email}
                        </p>
                    )}
                </div>

                {/* Password */}
                <div style={{ marginBottom: '20px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' }}>
                        <label style={{ ...labelStyle, marginBottom: 0 }} htmlFor="password">Contraseña</label>
                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                style={{ fontSize: '12px', color: 'var(--color-brand)', textDecoration: 'none' }}
                            >
                                ¿Olvidaste tu contraseña?
                            </Link>
                        )}
                    </div>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        onChange={e => setData('password', e.target.value)}
                        style={inputStyle}
                        onFocus={e => {
                            e.target.style.borderColor = 'var(--color-brand)'
                            e.target.style.boxShadow = '0 0 0 3px var(--color-brand-glow)'
                        }}
                        onBlur={e => {
                            e.target.style.borderColor = 'var(--sidebar-border)'
                            e.target.style.boxShadow = 'none'
                        }}
                    />
                    {errors.password && (
                        <p style={{ marginTop: '5px', fontSize: '12px', color: 'var(--color-danger)' }}>
                            {errors.password}
                        </p>
                    )}
                </div>

                {/* Remember + Submit */}
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: '12px' }}>
                    <label style={{ display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer' }}>
                        <input
                            type="checkbox"
                            name="remember"
                            checked={data.remember}
                            onChange={e => setData('remember', e.target.checked)}
                            style={{ width: '14px', height: '14px', accentColor: 'var(--color-brand)', cursor: 'pointer' }}
                        />
                        <span style={{ fontSize: '13px', color: 'var(--sidebar-text)' }}>Recordarme</span>
                    </label>

                    <button
                        type="submit"
                        disabled={processing}
                        style={{
                            padding: '10px 24px',
                            background: processing ? 'var(--color-brand-dark)' : 'var(--color-brand)',
                            color: 'white',
                            border: 'none',
                            borderRadius: 'var(--radius-sm)',
                            fontSize: '14px',
                            fontWeight: '600',
                            cursor: processing ? 'not-allowed' : 'pointer',
                            opacity: processing ? 0.7 : 1,
                            transition: 'all 200ms ease',
                            fontFamily: 'var(--font-body)',
                        }}
                        onMouseEnter={e => { if (!processing) e.currentTarget.style.background = 'var(--color-brand-dark)' }}
                        onMouseLeave={e => { if (!processing) e.currentTarget.style.background = 'var(--color-brand)' }}
                    >
                        {processing ? 'Entrando...' : 'Iniciar sesión'}
                    </button>
                </div>
            </form>
        </GuestLayout>
    )
}
```

**Step 2: Build y verificar**

```bash
./sail npm run build
```

Navegar a `http://localhost/login`. Verificar:
- Título "Bienvenido de nuevo"
- Campos con focus ring azul
- Botón "Iniciar sesión"
- Link "¿Olvidaste tu contraseña?"
- Login funcional (intentar con credenciales)

**Step 3: Commit**

```bash
git add resources/js/Pages/Auth/Login.jsx
git commit -m "feat: redesign Login page with dark card aesthetic and CSS variables"
```

---

### Task 5: Welcome Page — Landing Hero

**Files:**
- Modify: `resources/js/Pages/Welcome.jsx`

**Context:** Welcome.jsx es la página raíz `/`. El archivo actual muestra links de documentación de Laravel. Reescribir completamente con hero oscuro, gradiente animado, headline de ASAMBLI, y 3 cards de features. Mantener la prop `auth` para mostrar "Ir al dashboard" si el usuario ya está autenticado.

**Step 1: Reescribir Welcome.jsx**

```jsx
// resources/js/Pages/Welcome.jsx
import { Head, Link } from '@inertiajs/react'

const FEATURES = [
    {
        icon: (
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
        ),
        title: 'Quórum en Tiempo Real',
        description: 'Seguimiento en vivo del quórum por unidades o coeficiente. WebSockets con Laravel Reverb.',
    },
    {
        icon: (
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
        ),
        title: 'Votaciones Seguras',
        description: 'Votaciones con múltiples opciones, ponderadas por coeficiente de propiedad. Resultados instantáneos.',
    },
    {
        icon: (
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
        ),
        title: 'Auditoría SHA-256',
        description: 'Cada votación genera un hash criptográfico. Reportes PDF y CSV para actas legales.',
    },
]

export default function Welcome({ auth }) {
    return (
        <>
            <Head title="ASAMBLI — Gestión de Asambleas" />

            <div style={{
                minHeight: '100vh',
                background: 'var(--sidebar-bg)',
                fontFamily: 'var(--font-body)',
                color: '#e8edf5',
                position: 'relative',
                overflow: 'hidden',
            }}>
                {/* Animated gradient background */}
                <div style={{
                    position: 'absolute', inset: 0,
                    background: 'radial-gradient(ellipse 80% 60% at 50% -10%, rgba(37,99,235,0.18) 0%, transparent 60%)',
                    animation: 'pulseGlow 6s ease-in-out infinite alternate',
                    pointerEvents: 'none',
                }} />

                {/* Dot grid */}
                <div style={{
                    position: 'absolute', inset: 0,
                    backgroundImage: 'radial-gradient(circle, #1e2f50 1px, transparent 1px)',
                    backgroundSize: '28px 28px',
                    opacity: 0.45,
                    pointerEvents: 'none',
                }} />

                {/* Nav */}
                <nav style={{
                    position: 'relative',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '20px 40px',
                    borderBottom: '1px solid var(--sidebar-border)',
                }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                        <div style={{
                            width: '32px', height: '32px',
                            background: 'var(--color-brand)',
                            borderRadius: '8px',
                            display: 'flex', alignItems: 'center', justifyContent: 'center',
                        }}>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                            </svg>
                        </div>
                        <span style={{ fontWeight: '700', fontSize: '17px', letterSpacing: '-0.3px' }}>
                            ASAMBLI
                        </span>
                    </div>

                    {auth?.user ? (
                        <Link
                            href="/admin/dashboard"
                            style={{
                                padding: '8px 20px',
                                background: 'var(--color-brand)',
                                color: 'white',
                                textDecoration: 'none',
                                borderRadius: 'var(--radius-sm)',
                                fontSize: '13px',
                                fontWeight: '600',
                                transition: 'background 200ms ease',
                            }}
                        >
                            Ir al Dashboard →
                        </Link>
                    ) : (
                        <Link
                            href={route('login')}
                            style={{
                                padding: '8px 20px',
                                background: 'var(--color-brand)',
                                color: 'white',
                                textDecoration: 'none',
                                borderRadius: 'var(--radius-sm)',
                                fontSize: '13px',
                                fontWeight: '600',
                                transition: 'background 200ms ease',
                            }}
                        >
                            Iniciar sesión →
                        </Link>
                    )}
                </nav>

                {/* Hero */}
                <div style={{
                    position: 'relative',
                    maxWidth: '900px',
                    margin: '0 auto',
                    padding: '100px 40px 80px',
                    textAlign: 'center',
                }}>
                    <div style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '8px',
                        padding: '6px 14px',
                        borderRadius: '100px',
                        border: '1px solid rgba(37,99,235,0.3)',
                        background: 'rgba(37,99,235,0.08)',
                        marginBottom: '32px',
                        fontSize: '12px',
                        fontWeight: '500',
                        color: '#93c5fd',
                        letterSpacing: '0.02em',
                    }}>
                        <span style={{
                            width: '6px', height: '6px',
                            borderRadius: '50%',
                            background: '#10b981',
                            animation: 'pulse 2s ease-in-out infinite',
                            display: 'inline-block',
                        }} />
                        Votaciones en tiempo real con WebSockets
                    </div>

                    <h1 style={{
                        fontSize: 'clamp(36px, 5vw, 62px)',
                        fontWeight: '800',
                        letterSpacing: '-2px',
                        lineHeight: '1.08',
                        marginBottom: '24px',
                        background: 'linear-gradient(135deg, #e8edf5 0%, #93c5fd 100%)',
                        WebkitBackgroundClip: 'text',
                        WebkitTextFillColor: 'transparent',
                        backgroundClip: 'text',
                    }}>
                        Gestión de Asambleas<br />de Propiedad Horizontal
                    </h1>

                    <p style={{
                        fontSize: '18px',
                        color: 'var(--sidebar-text)',
                        lineHeight: '1.6',
                        maxWidth: '560px',
                        margin: '0 auto 40px',
                    }}>
                        Conduce asambleas con quórum dinámico, votaciones ponderadas
                        y reportes auditables con firma SHA-256.
                    </p>

                    <Link
                        href={route('login')}
                        style={{
                            display: 'inline-flex',
                            alignItems: 'center',
                            gap: '8px',
                            padding: '14px 32px',
                            background: 'var(--color-brand)',
                            color: 'white',
                            textDecoration: 'none',
                            borderRadius: 'var(--radius-md)',
                            fontSize: '16px',
                            fontWeight: '600',
                            transition: 'all 200ms ease',
                            boxShadow: '0 4px 20px rgba(37,99,235,0.35)',
                        }}
                        onMouseEnter={e => {
                            e.currentTarget.style.background = 'var(--color-brand-dark)'
                            e.currentTarget.style.transform = 'translateY(-1px)'
                            e.currentTarget.style.boxShadow = '0 6px 28px rgba(37,99,235,0.45)'
                        }}
                        onMouseLeave={e => {
                            e.currentTarget.style.background = 'var(--color-brand)'
                            e.currentTarget.style.transform = 'translateY(0)'
                            e.currentTarget.style.boxShadow = '0 4px 20px rgba(37,99,235,0.35)'
                        }}
                    >
                        Comenzar ahora
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </Link>
                </div>

                {/* Features */}
                <div style={{
                    position: 'relative',
                    maxWidth: '1000px',
                    margin: '0 auto',
                    padding: '0 40px 80px',
                    display: 'grid',
                    gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
                    gap: '20px',
                }}>
                    {FEATURES.map((f, i) => (
                        <div
                            key={i}
                            style={{
                                padding: '28px',
                                background: 'rgba(22, 30, 48, 0.7)',
                                border: '1px solid var(--sidebar-border)',
                                borderRadius: 'var(--radius-lg)',
                                backdropFilter: 'blur(8px)',
                                transition: 'all 250ms ease',
                            }}
                            onMouseEnter={e => {
                                e.currentTarget.style.borderColor = 'rgba(37,99,235,0.4)'
                                e.currentTarget.style.transform = 'translateY(-3px)'
                                e.currentTarget.style.boxShadow = '0 8px 32px rgba(37,99,235,0.12)'
                            }}
                            onMouseLeave={e => {
                                e.currentTarget.style.borderColor = 'var(--sidebar-border)'
                                e.currentTarget.style.transform = 'translateY(0)'
                                e.currentTarget.style.boxShadow = 'none'
                            }}
                        >
                            <div style={{
                                width: '44px', height: '44px',
                                borderRadius: 'var(--radius-md)',
                                background: 'rgba(37,99,235,0.12)',
                                border: '1px solid rgba(37,99,235,0.2)',
                                display: 'flex', alignItems: 'center', justifyContent: 'center',
                                color: '#93c5fd',
                                marginBottom: '16px',
                            }}>
                                {f.icon}
                            </div>
                            <h3 style={{ fontSize: '16px', fontWeight: '700', marginBottom: '8px', color: '#e8edf5' }}>
                                {f.title}
                            </h3>
                            <p style={{ fontSize: '14px', color: 'var(--sidebar-text)', lineHeight: '1.6', margin: 0 }}>
                                {f.description}
                            </p>
                        </div>
                    ))}
                </div>
            </div>

            <style>{`
                @keyframes pulseGlow {
                    from { opacity: 0.8; transform: scale(1); }
                    to   { opacity: 1;   transform: scale(1.05); }
                }
                @keyframes pulse {
                    0%, 100% { opacity: 1; transform: scale(1); }
                    50%       { opacity: 0.5; transform: scale(0.85); }
                }
            `}</style>
        </>
    )
}
```

**Step 2: Build y verificar**

```bash
./sail npm run build
```

Navegar a `http://localhost/`. Verificar:
- Fondo oscuro con gradiente animado sutil
- Navbar con logo y botón "Iniciar sesión"
- Headline con gradiente de texto
- Badge verde animado
- 3 cards de features con hover lift
- Link "Comenzar ahora" con shadow azul

**Step 3: Commit**

```bash
git add resources/js/Pages/Welcome.jsx
git commit -m "feat: redesign Welcome page with dark hero, animated gradient, and feature cards"
```

---

### Task 6: Verificación Final del Flujo Completo

**Step 1: Build de producción**

```bash
./sail npm run build
```

**Step 2: Checklist de verificación**

Navegar por estas rutas en orden:

| URL | Verificar |
|-----|-----------|
| `http://localhost/` | Welcome oscuro, gradiente, 3 features |
| `http://localhost/login` | Card oscura, logo, form funcional |
| `http://localhost/admin/dashboard` | Sidebar visible, topbar, nav activo en Dashboard |
| `http://localhost/admin/reuniones` | Sidebar con "Reuniones" activo (borde azul izquierdo) |
| `http://localhost/admin/padron` | Sidebar con "Padrón" activo |
| Mobile 375px en cualquier ruta admin | Sidebar oculto, hamburger visible, drawer funciona |

**Step 3: Commit final**

```bash
git add -A
git commit -m "feat: complete admin navigation UI redesign with professional dark sidebar aesthetic"
```
