import { iconoDe } from '../iconos'

interface IconoCategoriaProps {
  id: string
  size?: number
  /** Texto accesible; sin él, el ícono es decorativo. */
  label?: string
}

/** Dibuja uno de los íconos de la lista cerrada de categorías (trazos de 24×24). */
export function IconoCategoria({ id, size = 20, label }: IconoCategoriaProps) {
  const icono = iconoDe(id)

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={2}
      strokeLinecap="round"
      strokeLinejoin="round"
      role={label ? 'img' : undefined}
      aria-label={label}
      aria-hidden={label ? undefined : true}
    >
      {icono && <path d={icono.d} />}
    </svg>
  )
}

interface FichaDeIconoProps extends IconoCategoriaProps {
  activo: boolean
  /** Lado de la ficha en píxeles. */
  lado?: number
}

/** Ícono sobre una ficha cuadrada: acento si la categoría está activa y neutra si está inactiva. */
export function FichaDeIcono({ id, activo, lado = 36, size = 18, label }: FichaDeIconoProps) {
  return (
    <span
      style={{ width: lado, height: lado }}
      className={`inline-flex flex-none items-center justify-center rounded-lg ${activo ? 'bg-soft text-accent' : 'bg-border text-muted'}`}
    >
      <IconoCategoria id={id} size={size} label={label} />
    </span>
  )
}
