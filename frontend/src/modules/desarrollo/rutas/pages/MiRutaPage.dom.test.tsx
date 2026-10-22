// @vitest-environment jsdom
import { screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { inscripcionPropia, miRuta } from '../../../../test/rutasFixtures'
import { renderRoutes } from '../../../../test/renderRoutes'
import { obtenerMiRuta } from '../api'
import { MiRutaPage } from './MiRutaPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  obtenerMiRuta: vi.fn(),
}))

function renderPage() {
  return renderRoutes(<Route path="/rutas" element={<MiRutaPage />} />, '/rutas')
}

beforeEach(() => {
  vi.mocked(obtenerMiRuta).mockReset().mockResolvedValue(miRuta())
})

describe('MiRutaPage', () => {
  it('muestra un esqueleto mientras carga', () => {
    vi.mocked(obtenerMiRuta).mockReturnValue(new Promise(() => {}))
    renderPage()

    expect(screen.getByRole('status', { name: 'Cargando…' })).toBeInTheDocument()
  })

  it('muestra el título, el aviso permanente «guía, no promesa» y la escalera de la ruta', async () => {
    renderPage()

    expect(screen.getByRole('heading', { level: 1, name: 'Mi ruta de carrera' })).toBeInTheDocument()
    expect(screen.getByRole('note')).toHaveTextContent('Esta ruta es una guía de desarrollo; no implica la promesa de un puesto.')
    const escalera = await screen.findByRole('list', { name: 'Escalera de niveles' })
    expect(within(escalera).getAllByRole('listitem')).toHaveLength(3)
  })

  it('resalta el nivel actual, marca el objetivo e indica el siguiente', async () => {
    renderPage()

    const actual = await screen.findByRole('button', { name: /Ver nivel 1.*Estás aquí/ })
    expect(actual.closest('li')).toHaveAttribute('aria-current', 'step')
    expect(screen.getByRole('button', { name: /Ver nivel 3.*Objetivo/ })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Ver nivel 2.*Siguiente/ })).toBeInTheDocument()
  })

  it('el detalle inicial es el nivel objetivo, con competencias, conocimientos informativos y experiencia', async () => {
    renderPage()

    expect(await screen.findByRole('heading', { level: 2, name: 'Tu nivel objetivo: Analista senior' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { level: 3, name: 'Competencias' })).toBeInTheDocument()
    expect(screen.getByRole('heading', { level: 3, name: 'Conocimientos' })).toBeInTheDocument()
    expect(screen.getByText('Informativo')).toBeInTheDocument()
    expect(screen.getByRole('heading', { level: 3, name: 'Experiencia' })).toBeInTheDocument()
  })

  it('al elegir el siguiente nivel muestra lo que se exige en él', async () => {
    renderPage()

    await userEvent.click(await screen.findByRole('button', { name: /Ver nivel 2/ }))

    expect(screen.getByRole('heading', { level: 2, name: 'Tu siguiente nivel: Analista de datos' })).toBeInTheDocument()
    expect(screen.getByText('Tecnólogo')).toBeInTheDocument()
  })

  it('«Ver mi adecuación» no se ofrece mientras la vista de adecuación no exista', async () => {
    renderPage()
    await screen.findByRole('list', { name: 'Escalera de niveles' })

    expect(screen.queryByRole('link', { name: 'Ver mi adecuación' })).not.toBeInTheDocument()
  })

  it('«Ver mi adecuación» aparece solo con nivel objetivo y cuando la vista está disponible', async () => {
    vi.mocked(obtenerMiRuta).mockResolvedValue(miRuta({ inscripciones: [inscripcionPropia({ puede_ver_adecuacion: true })] }))
    renderPage()

    expect(await screen.findByRole('link', { name: 'Ver mi adecuación' })).toBeInTheDocument()
  })

  it('sin nivel objetivo lo indica y no ofrece la adecuación', async () => {
    vi.mocked(obtenerMiRuta).mockResolvedValue(miRuta({ inscripciones: [inscripcionPropia({ nivel_objetivo_id: null, puede_ver_adecuacion: true })] }))
    renderPage()

    expect(await screen.findByText('Todavía no tienes un nivel objetivo definido.')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Ver mi adecuación' })).not.toBeInTheDocument()
  })

  it('lista las rutas Vigentes disponibles, solo de lectura', async () => {
    renderPage()

    const disponibles = await screen.findByRole('region', { name: 'Rutas disponibles' })
    expect(disponibles).toHaveTextContent('Carrera gerencial')
    expect(disponibles).toHaveTextContent('CAR-0002 · 4 niveles')
    expect(within(disponibles).queryByRole('button')).not.toBeInTheDocument()
  })

  it('sin inscripciones muestra el estado vacío y las rutas disponibles', async () => {
    vi.mocked(obtenerMiRuta).mockResolvedValue(miRuta({ inscripciones: [] }))
    renderPage()

    expect(await screen.findByText('Aún no estás inscrito en ninguna ruta')).toBeInTheDocument()
    expect(screen.getByRole('region', { name: 'Rutas disponibles' })).toBeInTheDocument()
  })

  it('no ofrece ninguna acción de gestión ni de interés', async () => {
    renderPage()
    await screen.findByRole('list', { name: 'Escalera de niveles' })

    expect(screen.queryByRole('button', { name: /Nueva ruta|Inscribir|Retirar|Registrar mi interés/ })).not.toBeInTheDocument()
  })

  it('con Estructura caída avisa, ofrece «Reintentar» y no inventa requisitos', async () => {
    const propia = inscripcionPropia()
    vi.mocked(obtenerMiRuta).mockResolvedValue(
      miRuta({ fuentes_no_disponibles: ['estructura'], inscripciones: [{ ...propia, niveles: propia.niveles.map((n) => ({ ...n, competencias: [], conocimientos: { informativo: true, formacion: [], capacitacion: [] } })) }] }),
    )
    renderPage()

    expect(await screen.findByText(/No se pudo consultar Estructura Empresarial/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Reintentar' })).toBeInTheDocument()
    expect(screen.getByText('El puesto no define competencias.')).toBeInTheDocument()
  })

  it('con error muestra «Reintentar»', async () => {
    vi.mocked(obtenerMiRuta).mockRejectedValue(new Error('caída'))
    renderPage()

    expect(await screen.findByRole('button', { name: 'Reintentar' })).toBeInTheDocument()
  })
})
