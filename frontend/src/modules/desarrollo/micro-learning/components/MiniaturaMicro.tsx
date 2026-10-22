import { useState } from 'react'
import type { TipoMicro } from '../api'
import { ESTILO_TIPO } from '../estilosTipo'
import { microText } from '../text'
import { IconoTipo } from './IconoTipo'

interface MiniaturaMicroProps {
  tipo: TipoMicro
  url: string | null
  className?: string
}

/** Miniatura del contenido; sin URL (o si no carga) muestra una imagen genérica según el tipo. */
export function MiniaturaMicro({ tipo, url, className = 'h-32 w-full' }: MiniaturaMicroProps) {
  const [fallo, setFallo] = useState(false)

  if (url && !fallo) {
    return <img src={url} alt="" loading="lazy" onError={() => setFallo(true)} className={`${className} rounded-lg object-cover`} />
  }

  return (
    <div role="img" aria-label={microText.imagenGenerica(tipo)} className={`${className} flex items-center justify-center rounded-lg ${ESTILO_TIPO[tipo]}`}>
      <IconoTipo tipo={tipo} size={36} />
    </div>
  )
}
