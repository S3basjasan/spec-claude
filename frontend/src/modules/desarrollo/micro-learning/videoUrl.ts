export type FuenteVideo = { tipo: 'incrustado'; src: string } | { tipo: 'archivo'; src: string }

const EXTENSIONES_VIDEO = /\.(mp4|webm|ogv|ogg|mov)$/i

/**
 * Resuelve cómo reproducir la URL de un micro-contenido de tipo Video: YouTube y Vimeo se incrustan; un archivo
 * de video se reproduce con la etiqueta nativa. Devuelve `null` si no es una URL https reconocida.
 */
export function resolverFuenteVideo(url: string | null | undefined): FuenteVideo | null {
  if (!url) return null
  let direccion: URL
  try {
    direccion = new URL(url.trim())
  } catch {
    return null
  }
  if (direccion.protocol !== 'https:' && direccion.protocol !== 'http:') return null

  const host = direccion.hostname.replace(/^www\./, '').replace(/^m\./, '')
  const partes = direccion.pathname.split('/').filter(Boolean)

  if (host === 'youtu.be' && partes[0]) {
    return { tipo: 'incrustado', src: `https://www.youtube.com/embed/${partes[0]}` }
  }
  if (host === 'youtube.com' || host === 'youtube-nocookie.com') {
    if (partes[0] === 'embed' && partes[1]) {
      return { tipo: 'incrustado', src: `https://www.youtube.com/embed/${partes[1]}${direccion.search}` }
    }
    if ((partes[0] === 'shorts' || partes[0] === 'live') && partes[1]) {
      return { tipo: 'incrustado', src: `https://www.youtube.com/embed/${partes[1]}` }
    }
    const id = direccion.searchParams.get('v')
    if (partes[0] === 'watch' && id) {
      const lista = direccion.searchParams.get('list')
      return { tipo: 'incrustado', src: `https://www.youtube.com/embed/${id}${lista ? `?list=${encodeURIComponent(lista)}` : ''}` }
    }
    return null
  }
  if (host === 'vimeo.com' && /^\d+$/.test(partes[0] ?? '')) {
    return { tipo: 'incrustado', src: `https://player.vimeo.com/video/${partes[0]}` }
  }
  if (host === 'player.vimeo.com' && partes[0] === 'video' && partes[1]) {
    return { tipo: 'incrustado', src: direccion.toString() }
  }
  if (EXTENSIONES_VIDEO.test(direccion.pathname)) {
    return { tipo: 'archivo', src: direccion.toString() }
  }

  return null
}
