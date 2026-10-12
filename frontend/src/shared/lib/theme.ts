import { useSyncExternalStore } from 'react'

export type Theme = 'light' | 'dark'

const THEME_KEY = 'hrms.theme'
const listeners = new Set<() => void>()

function readStored(): Theme | null {
  try {
    const value = localStorage.getItem(THEME_KEY)
    return value === 'light' || value === 'dark' ? value : null
  } catch {
    return null
  }
}

function systemTheme(): Theme {
  return typeof window !== 'undefined' && window.matchMedia?.('(prefers-color-scheme: dark)').matches
    ? 'dark'
    : 'light'
}

export function getTheme(): Theme {
  const attr = document.documentElement.dataset.theme
  return attr === 'dark' || attr === 'light' ? attr : (readStored() ?? systemTheme())
}

function apply(theme: Theme): void {
  document.documentElement.dataset.theme = theme
  listeners.forEach((listener) => listener())
}

/** Aplica el tema guardado (o el del sistema) al arrancar. */
export function initTheme(): void {
  apply(readStored() ?? systemTheme())
}

export function setTheme(theme: Theme): void {
  try {
    localStorage.setItem(THEME_KEY, theme)
  } catch {
    // Sin almacenamiento: el tema solo dura la sesión del navegador.
  }
  apply(theme)
}

function subscribe(listener: () => void): () => void {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

export function useTheme(): { theme: Theme; toggleTheme: () => void } {
  const theme = useSyncExternalStore(subscribe, getTheme, () => 'light' as Theme)
  return { theme, toggleTheme: () => setTheme(theme === 'dark' ? 'light' : 'dark') }
}
