// @vitest-environment jsdom
import { act, fireEvent, render, screen } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ReproductorVideo } from './ReproductorVideo'

beforeEach(() => {
  vi.useFakeTimers()
})

afterEach(() => {
  vi.useRealTimers()
})

function reproducir(boton: RegExp | string = 'Reproducir') {
  fireEvent.click(screen.getByRole('button', { name: boton }))
}

describe('ReproductorVideo', () => {
  it('sin posición guardada empieza desde el inicio', () => {
    render(<ReproductorVideo titulo="Video" duracionSegundos={600} posicionInicial={0} modo="real" />)

    expect(screen.getByText('0:00 / 10:00')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reproducir' })).toBeInTheDocument()
    expect(screen.queryByText(/Retomas donde lo dejaste/)).not.toBeInTheDocument()
  })

  it('con posición guardada ofrece reanudar desde ese punto', () => {
    render(<ReproductorVideo titulo="Video" duracionSegundos={600} posicionInicial={95} modo="real" />)

    expect(screen.getByText('Retomas donde lo dejaste: 1:35')).toBeInTheDocument()
    expect(screen.getByText('1:35 / 10:00')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reanudar desde 1:35' })).toBeInTheDocument()
  })

  it('avanza un segundo por segundo al reproducir y guarda la posición al pausar', () => {
    const onPosicion = vi.fn()
    render(<ReproductorVideo titulo="Video" duracionSegundos={600} posicionInicial={95} modo="real" onPosicion={onPosicion} />)

    reproducir('Reanudar desde 1:35')
    act(() => {
      vi.advanceTimersByTime(3000)
    })
    expect(screen.getByText('1:38 / 10:00')).toBeInTheDocument()

    reproducir('Pausar')
    expect(onPosicion).toHaveBeenCalledWith(98)
  })

  it('guarda cada 10 segundos mientras reproduce', () => {
    const onPosicion = vi.fn()
    render(<ReproductorVideo titulo="Video" duracionSegundos={600} posicionInicial={0} modo="real" onPosicion={onPosicion} />)

    reproducir()
    // Un segundo por vez, como en el navegador: cada tick actualiza la posición antes del siguiente.
    for (let segundo = 0; segundo < 10; segundo++) {
      act(() => {
        vi.advanceTimersByTime(1000)
      })
    }

    // Se guarda a los 10 s con la posición vigente (el guardado y el avance caen en el mismo segundo).
    expect(onPosicion).toHaveBeenCalledOnce()
    expect(onPosicion.mock.calls[0][0]).toBeGreaterThanOrEqual(9)
  })

  it('guarda lo último al salir de la pantalla', () => {
    const onPosicion = vi.fn()
    const { unmount } = render(<ReproductorVideo titulo="Video" duracionSegundos={600} posicionInicial={0} modo="real" onPosicion={onPosicion} />)

    reproducir()
    act(() => {
      vi.advanceTimersByTime(4000)
    })
    unmount()

    expect(onPosicion).toHaveBeenLastCalledWith(4)
  })

  it('en la vista previa nunca guarda nada', () => {
    const onPosicion = vi.fn()
    const { unmount } = render(<ReproductorVideo titulo="Video" duracionSegundos={600} posicionInicial={0} modo="previa" onPosicion={onPosicion} />)

    reproducir()
    act(() => {
      vi.advanceTimersByTime(12_000)
    })
    reproducir('Pausar')
    unmount()

    expect(onPosicion).not.toHaveBeenCalled()
  })

  it('un video ya completado no guarda posición', () => {
    const onPosicion = vi.fn()
    render(<ReproductorVideo titulo="Video" duracionSegundos={600} posicionInicial={0} modo="real" completado onPosicion={onPosicion} />)

    reproducir()
    act(() => {
      vi.advanceTimersByTime(12_000)
    })

    expect(onPosicion).not.toHaveBeenCalled()
  })

  it('se detiene al llegar al final', () => {
    render(<ReproductorVideo titulo="Video" duracionSegundos={3} posicionInicial={0} modo="previa" />)

    reproducir()
    act(() => {
      vi.advanceTimersByTime(5000)
    })

    expect(screen.getByText('0:03 / 0:03')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reproducir' })).toBeDisabled()
  })
})
