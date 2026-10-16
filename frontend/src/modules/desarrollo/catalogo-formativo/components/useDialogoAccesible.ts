import { useEffect, useRef, type KeyboardEvent } from 'react'

const FOCUSABLE = 'button:not([disabled]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'

/**
 * Comportamiento accesible de un diálogo modal: lleva el foco adentro al abrir, lo devuelve al cerrar, lo mantiene
 * dentro con Tab y cierra con Escape (salvo que esté `bloqueado`, p. ej. mientras guarda).
 */
export function useDialogoAccesible(onCerrar: () => void, bloqueado = false) {
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const anterior = document.activeElement instanceof HTMLElement ? document.activeElement : null
    const primero = ref.current?.querySelector<HTMLElement>(FOCUSABLE)
    ;(primero ?? ref.current)?.focus()
    return () => anterior?.focus()
  }, [])

  const onKeyDown = (event: KeyboardEvent<HTMLElement>) => {
    if (event.key === 'Escape' && !bloqueado) {
      event.stopPropagation()
      onCerrar()
      return
    }
    if (event.key !== 'Tab') return
    const items = Array.from(ref.current?.querySelectorAll<HTMLElement>(FOCUSABLE) ?? [])
    if (items.length === 0) return
    const primero = items[0]
    const ultimo = items[items.length - 1]
    if (event.shiftKey && document.activeElement === primero) {
      event.preventDefault()
      ultimo.focus()
    } else if (!event.shiftKey && document.activeElement === ultimo) {
      event.preventDefault()
      primero.focus()
    }
  }

  return { ref, onKeyDown }
}
