import type { EstadoMicro, TipoMicro } from './api'

/**
 * Colores por tipo de micro-contenido (design/DESIGN.md §2): Video rojo, PDF azul, Artículo verde y Quiz púrpura.
 * Siempre van acompañados del nombre del tipo; el color nunca es la única señal.
 */
export const ESTILO_TIPO: Record<TipoMicro, string> = {
  Video: 'bg-dangersoft text-danger',
  PDF: 'bg-infosoft text-info',
  Articulo: 'bg-soft text-accent',
  Quiz: 'bg-violetsoft text-violet',
}

/** Borde superior de la tarjeta según el tipo. */
export const BORDE_TIPO: Record<TipoMicro, string> = {
  Video: 'border-t-danger',
  PDF: 'border-t-info',
  Articulo: 'border-t-accent',
  Quiz: 'border-t-violet',
}

/** Variante del `Badge` de estado: Borrador neutro, Publicado por defecto y Archivado atenuado. */
export const VARIANTE_ESTADO: Record<EstadoMicro, 'default' | 'neutral' | 'warning'> = {
  Borrador: 'warning',
  Publicado: 'default',
  Archivado: 'neutral',
}
