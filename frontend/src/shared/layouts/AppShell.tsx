import { useEffect, useRef, useState, type ReactNode } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useTheme } from '../lib/theme'
import { IconChevronDown, IconMoon, IconSun } from '../ui/icons'
import { shellText } from './text'

export interface ShellUser {
  name: string
  role: 'admin' | 'user'
}

export interface ShellNavItem {
  to: string
  label: string
  icon: ReactNode
  end?: boolean
  /** Si se indica, el ítem solo se muestra a esos roles (la autoridad sigue siendo el backend). */
  roles?: ShellUser['role'][]
}

export interface ShellNavGroup {
  label: string
  items: ShellNavItem[]
}

interface AppShellProps {
  user: ShellUser
  navGroups: ShellNavGroup[]
  onLogout: () => void
  isLoggingOut: boolean
}

function isActivePath(pathname: string, item: ShellNavItem): boolean {
  return item.end ? pathname === item.to : pathname === item.to || pathname.startsWith(`${item.to}/`)
}

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
}

/**
 * Marco de toda pantalla autenticada (design/DESIGN.md §3). Es presentacional:
 * recibe usuario y acciones por props y no depende de ningún módulo de negocio.
 */
export function AppShell({ user, navGroups, onLogout, isLoggingOut }: AppShellProps) {
  const { theme, toggleTheme } = useTheme()
  const [menuOpen, setMenuOpen] = useState(false)
  const menuRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!menuOpen) return
    const onPointerDown = (event: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) setMenuOpen(false)
    }
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setMenuOpen(false)
    }
    document.addEventListener('mousedown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)
    return () => {
      document.removeEventListener('mousedown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [menuOpen])

  const roleLabel = shellText.roles[user.role]
  const visibleGroups = navGroups
    .map((group) => ({ ...group, items: group.items.filter((item) => !item.roles || item.roles.includes(user.role)) }))
    .filter((group) => group.items.length > 0)

  const { pathname } = useLocation()
  const activeGroup = visibleGroups.find((group) => group.items.some((item) => isActivePath(pathname, item)))?.label
  const [openGroups, setOpenGroups] = useState<Set<string>>(
    () => new Set(activeGroup ? [activeGroup] : visibleGroups.slice(0, 1).map((group) => group.label)),
  )

  // Al navegar a un ítem, su grupo se abre para que la ubicación actual siempre sea visible.
  useEffect(() => {
    if (activeGroup) setOpenGroups((current) => (current.has(activeGroup) ? current : new Set(current).add(activeGroup)))
  }, [activeGroup])

  const toggleGroup = (label: string) =>
    setOpenGroups((current) => {
      const next = new Set(current)
      if (!next.delete(label)) next.add(label)
      return next
    })

  return (
    <div className="flex min-h-screen flex-col bg-bg text-text">
      <a
        href="#contenido"
        className="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-3 focus:py-2"
      >
        {shellText.skipToContent}
      </a>

      <header className="flex h-16 items-center justify-between gap-3 border-b border-border bg-surface px-4 sm:px-6">
        <div className="flex items-center gap-2.5">
          <span className="flex size-8 items-center justify-center rounded-lg bg-accent font-bold text-onaccent" aria-hidden="true">
            H
          </span>
          <span className="text-lg font-bold tracking-tight">{shellText.brand}</span>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={toggleTheme}
            aria-label={theme === 'dark' ? shellText.switchToLight : shellText.switchToDark}
            className="flex size-11 items-center justify-center rounded-lg text-text hover:bg-soft"
          >
            {theme === 'dark' ? <IconSun /> : <IconMoon />}
          </button>

          <div ref={menuRef} className="relative">
            <button
              type="button"
              onClick={() => setMenuOpen((open) => !open)}
              aria-haspopup="menu"
              aria-expanded={menuOpen}
              aria-label={`${shellText.userMenu}: ${user.name}`}
              className="flex min-h-11 items-center gap-2 rounded-lg px-2 hover:bg-soft"
            >
              <span className="flex size-8 items-center justify-center rounded-full bg-soft text-sm font-semibold text-accent" aria-hidden="true">
                {initials(user.name)}
              </span>
              <span className="hidden text-sm font-semibold sm:inline">{user.name}</span>
              <IconChevronDown size={18} />
            </button>

            {menuOpen && (
              <div
                role="menu"
                aria-label={shellText.userMenu}
                className="absolute right-0 z-40 mt-2 w-64 rounded-[10px] border border-border bg-surface p-2 shadow-[0_8px_24px_rgba(0,0,0,0.12)]"
              >
                <div className="border-b border-border px-3 py-2">
                  <p className="text-sm font-semibold">{user.name}</p>
                  <p className="text-[13px] text-muted">{roleLabel}</p>
                </div>
                <button
                  type="button"
                  role="menuitem"
                  onClick={onLogout}
                  disabled={isLoggingOut}
                  className="mt-1 flex min-h-11 w-full items-center rounded-lg px-3 text-left text-sm font-semibold text-danger hover:bg-dangersoft disabled:opacity-60"
                >
                  {isLoggingOut ? shellText.loggingOut : shellText.logout}
                </button>
              </div>
            )}
          </div>
        </div>
      </header>

      <div className="flex flex-1 flex-col md:flex-row">
        <nav
          aria-label={shellText.mainNavigation}
          className="border-b border-border bg-surface md:w-62 md:shrink-0 md:border-b-0 md:border-r"
        >
          <div className="flex flex-col gap-1 p-3 md:sticky md:top-0 md:max-h-screen md:overflow-y-auto">
            {visibleGroups.map((group, index) => {
              const open = openGroups.has(group.label)
              const panelId = `nav-panel-${index}`
              return (
                <section key={group.label}>
                  <h2>
                    <button
                      type="button"
                      onClick={() => toggleGroup(group.label)}
                      aria-expanded={open}
                      aria-controls={panelId}
                      className="flex min-h-11 w-full items-center justify-between rounded-lg px-3 text-left text-[13px] font-semibold uppercase tracking-wide text-muted hover:bg-soft"
                    >
                      {group.label}
                      <span className={`transition-transform ${open ? '' : '-rotate-90'}`} aria-hidden="true">
                        <IconChevronDown size={18} />
                      </span>
                    </button>
                  </h2>
                  <ul id={panelId} hidden={!open} className="flex flex-wrap gap-1 pb-2 md:flex-col">
                    {group.items.map((item) => (
                      <li key={item.to}>
                        <NavLink
                          to={item.to}
                          end={item.end}
                          className={({ isActive }) =>
                            `flex min-h-11 items-center gap-3 rounded-lg px-3 text-[15px] ${
                              isActive ? 'bg-soft font-semibold text-accent' : 'text-text hover:bg-soft'
                            }`
                          }
                        >
                          {item.icon}
                          {item.label}
                        </NavLink>
                      </li>
                    ))}
                  </ul>
                </section>
              )
            })}
          </div>
        </nav>

        <main id="contenido" className="min-w-0 flex-1 p-4 sm:p-8">
          <Outlet />
        </main>
      </div>

      <footer className="flex flex-wrap justify-between gap-x-6 gap-y-2 border-t border-border bg-surface px-4 py-4 text-[13px] text-muted sm:px-6">
        <span>© 2026 {shellText.brand} · {shellText.version}</span>
        <span className="flex gap-5">
          <a href="#ayuda" className="hover:underline">{shellText.help}</a>
          <a href="#privacidad" className="hover:underline">{shellText.privacy}</a>
          <a href="#terminos" className="hover:underline">{shellText.terms}</a>
        </span>
      </footer>
    </div>
  )
}
