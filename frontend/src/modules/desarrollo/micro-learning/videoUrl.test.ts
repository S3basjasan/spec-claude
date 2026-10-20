import { describe, expect, it } from 'vitest'
import { resolverFuenteVideo } from './videoUrl'

describe('resolverFuenteVideo', () => {
  it('incrusta las URL de YouTube en sus distintos formatos', () => {
    expect(resolverFuenteVideo('https://www.youtube.com/watch?v=abc123')).toEqual({ tipo: 'incrustado', src: 'https://www.youtube.com/embed/abc123' })
    expect(resolverFuenteVideo('https://youtu.be/abc123')).toEqual({ tipo: 'incrustado', src: 'https://www.youtube.com/embed/abc123' })
    expect(resolverFuenteVideo('https://www.youtube.com/embed/abc123?list=PL1')).toEqual({ tipo: 'incrustado', src: 'https://www.youtube.com/embed/abc123?list=PL1' })
  })

  it('incrusta Vimeo y reproduce archivos de video de forma nativa', () => {
    expect(resolverFuenteVideo('https://vimeo.com/12345')).toEqual({ tipo: 'incrustado', src: 'https://player.vimeo.com/video/12345' })
    expect(resolverFuenteVideo('https://cdn.ejemplo.test/clip.mp4')).toEqual({ tipo: 'archivo', src: 'https://cdn.ejemplo.test/clip.mp4' })
  })

  it('devuelve null para URL vacías, no reconocidas o con otro protocolo', () => {
    expect(resolverFuenteVideo(null)).toBeNull()
    expect(resolverFuenteVideo('https://contenido.hrms.test/video/pausas-activas')).toBeNull()
    expect(resolverFuenteVideo('javascript:alert(1)')).toBeNull()
  })
})
