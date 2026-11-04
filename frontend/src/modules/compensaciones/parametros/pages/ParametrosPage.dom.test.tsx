// @vitest-environment jsdom
import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Route } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { httpError } from '../../../../test/apiErrors'
import { renderRoutes } from '../../../../test/renderRoutes'
import { useCurrentUser } from '../../../auth/login'
import { guardarParametros, listarParametros, type ClaveParametro, type Parametro } from '../api'
import { ParametrosPage } from './ParametrosPage'

vi.mock('../api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('../api')>()),
  listarParametros: vi.fn(),
  guardarParametros: vi.fn(),
}))
vi.mock('../../../auth/login', () => ({ useCurrentUser: vi.fn() }))

const BASE: Record<ClaveParametro, Pick<Parametro, 'valor' | 'minimo' | 'maximo' | 'por_defecto'>> = {
  puntos_por_colaborador: { valor: 100, minimo: 1, maximo: 1000, por_defecto: 100 },
  plazo_anulacion_dias: { valor: 7, minimo: 1, maximo: 30, por_defecto: 7 },
  umbral_concentracion: { valor: 50, minimo: 1, maximo: 100, por_defecto: 50 },
  plazo_max_desafio_dias: { valor: 30, minimo: 1, maximo: 90, por_defecto: 30 },
  umbral_clima_unidad: { valor: 33, minimo: 1, maximo: 100, por_defecto: 33 },
  sensibilidad_radar: { valor: 2, minimo: 1, maximo: 3, por_defecto: 2 },
}

function parametros(cambios: Partial<Record<ClaveParametro, Partial<Parametro>>> = {}): Parametro[] {
  return (Object.keys(BASE) as ClaveParametro[]).map((clave) => ({
    clave,
    ...BASE[clave],
    valor_programado: null,
    version: 0,
    ultimo_cambio: null,
    ...cambios[clave],
  }))
}

function comoRol(role: 'admin' | 'user') {
  vi.mocked(useCurrentUser).mockReturnValue({ data: { id: 1, name: 'Ana', email: 'a@hrms.test', role } } as ReturnType<typeof useCurrentUser>)
}

function renderPagina() {
  return renderRoutes(
    <>
      <Route path="/compensaciones/parametros" element={<ParametrosPage />} />
      <Route path="/compensaciones/categorias" element={<p>Pantalla de categorías</p>} />
    </>,
    '/compensaciones/parametros',
  )
}

async function esperarCarga() {
  await screen.findByRole('heading', { name: 'Puntos por colaborador al mes' })
}

async function escribir(etiqueta: string, valor: string) {
  const campo = await screen.findByLabelText(etiqueta, { selector: 'input:not([type="radio"])' })
  await userEvent.clear(campo)
  if (valor !== '') await userEvent.type(campo, valor)
  return campo
}

beforeEach(() => {
  comoRol('admin')
  vi.mocked(listarParametros).mockReset().mockResolvedValue(parametros())
  vi.mocked(guardarParametros).mockReset().mockResolvedValue(parametros())
})

describe('ParametrosPage', () => {
  it('muestra una tarjeta por parámetro con su rango y el estado inicial', async () => {
    renderPagina()

    expect(await screen.findByRole('heading', { name: 'Parámetros del módulo', level: 1 })).toBeInTheDocument()
    await esperarCarga()
    for (const titulo of [
      'Puntos por colaborador al mes',
      'Plazo para anular un reconocimiento',
      'Alerta de concentración',
      'Plazo máximo de un desafío',
      'Alerta de clima por unidad',
      'Sensibilidad del radar de clima',
    ]) {
      expect(screen.getByRole('heading', { name: titulo })).toBeInTheDocument()
    }
    expect(screen.getByText('Número entero de 1 a 1000.')).toBeInTheDocument()
    expect(screen.getByText('Número entero de 1 a 30.')).toBeInTheDocument()
    expect(screen.getAllByText('Valor inicial')).toHaveLength(6)
  })

  it('bloquea Guardar y Descartar mientras no hay cambios', async () => {
    renderPagina()
    await esperarCarga()

    expect(screen.getByRole('button', { name: 'Guardar' })).toBeDisabled()
    expect(screen.getByRole('button', { name: 'Descartar' })).toBeDisabled()
  })

  it('en los puntos muestra actual → nuevo, la vigencia y el ejemplo de 10 personas', async () => {
    renderPagina()
    await escribir('Puntos', '150')

    expect(screen.getByText('100 → 150 puntos por colaborador al mes')).toBeInTheDocument()
    expect(screen.getByText(/Rige desde el .* \(día 1 del mes siguiente\)\./)).toBeInTheDocument()
    expect(screen.getByText('Un equipo de 10 personas tendría 1500 puntos al mes.')).toBeInTheDocument()
  })

  it('muestra el valor programado de los puntos', async () => {
    vi.mocked(listarParametros).mockResolvedValue(
      parametros({ puntos_por_colaborador: { valor_programado: { valor: 150, vigente_desde: '2026-12-01' }, version: 4 } }),
    )
    renderPagina()

    expect(await screen.findByText(/Programado: 150 desde el 1 de diciembre de 2026\./)).toBeInTheDocument()
    expect(screen.getByText('Actual: 100')).toBeInTheDocument()
  })

  it.each([
    ['0', 'Debe estar entre 1 y 30.'],
    ['31', 'Debe estar entre 1 y 30.'],
    ['2.5', 'Debe ser un número entero.'],
    ['diez', 'Debe ser un número entero.'],
    ['', 'Escribe un valor.'],
  ])('rechaza «%s» en el plazo de anulación junto al campo y no envía nada', async (valor, mensaje) => {
    renderPagina()
    const campos = await screen.findAllByLabelText('Días', { selector: 'input:not([type="radio"])' })
    await userEvent.clear(campos[0])
    if (valor !== '') await userEvent.type(campos[0], valor)
    await userEvent.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText(mensaje)).toBeInTheDocument()
    expect(guardarParametros).not.toHaveBeenCalled()
  })

  it('envía solo los campos modificados con su versión y confirma el guardado', async () => {
    vi.mocked(listarParametros).mockResolvedValue(parametros({ plazo_anulacion_dias: { version: 3 } }))
    vi.mocked(guardarParametros).mockResolvedValue(parametros({ plazo_anulacion_dias: { valor: 10, version: 4 } }))
    renderPagina()
    const [dias] = await screen.findAllByLabelText('Días', { selector: 'input:not([type="radio"])' })
    await userEvent.clear(dias)
    await userEvent.type(dias, '10')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar' }))

    await waitFor(() => expect(guardarParametros).toHaveBeenCalledWith([{ clave: 'plazo_anulacion_dias', valor: 10, version: 3 }]))
    expect(await screen.findByText('Los parámetros se guardaron correctamente.')).toBeInTheDocument()
  })

  it('muestra bajo el campo de puntos el error de la categoría más alta y conserva lo escrito', async () => {
    vi.mocked(guardarParametros).mockRejectedValue(
      httpError(422, { message: 'x', errors: { 'cambios.0.valor': ['No puede ser menor que la puntuación de la categoría «Liderazgo» (200 puntos).'] } }),
    )
    renderPagina()
    const campo = await escribir('Puntos', '150')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('No puede ser menor que la puntuación de la categoría «Liderazgo» (200 puntos).')).toBeInTheDocument()
    expect(campo).toHaveValue('150')
  })

  it('ante un 409 avisa, conserva lo escrito y recarga los valores al pedirlo', async () => {
    vi.mocked(guardarParametros).mockRejectedValue(httpError(409, { message: 'otro' }))
    renderPagina()
    const campo = await escribir('Puntos', '150')
    await userEvent.click(screen.getByRole('button', { name: 'Guardar' }))

    expect(await screen.findByText('Otra persona cambió un parámetro mientras editabas. Recarga para ver los valores actuales.')).toBeInTheDocument()
    expect(campo).toHaveValue('150')

    vi.mocked(listarParametros).mockResolvedValue(parametros({ puntos_por_colaborador: { valor: 120, version: 5 } }))
    await userEvent.click(screen.getByRole('button', { name: 'Recargar' }))

    await waitFor(() => expect(screen.getByLabelText('Puntos', { selector: 'input:not([type="radio"])' })).toHaveValue('120'))
  })

  it('descarta los cambios escritos', async () => {
    renderPagina()
    const campo = await escribir('Puntos', '150')

    await userEvent.click(screen.getByRole('button', { name: 'Descartar' }))

    expect(campo).toHaveValue('100')
  })

  it('la sensibilidad son tres opciones con su explicación y ningún campo numérico', async () => {
    renderPagina()
    const grupo = await screen.findByRole('group', { name: 'Sensibilidad del radar de clima' })

    const radios = within(grupo).getAllByRole('radio')
    expect(radios).toHaveLength(3)
    expect(within(grupo).queryByRole('textbox')).not.toBeInTheDocument()
    expect(within(grupo).getByText('Solo alerta en casos claros. Menos avisos.')).toBeInTheDocument()
    expect(within(grupo).getByText('Equilibrio entre detectar a tiempo y no saturar al líder.')).toBeInTheDocument()
    expect(within(grupo).getByText('Detecta antes las señales leves. Más avisos.')).toBeInTheDocument()
    expect(within(grupo).getByRole('radio', { name: /Normal/ })).toBeChecked()
  })

  it('al elegir otra sensibilidad envía el entero 3 sin umbrales', async () => {
    vi.mocked(listarParametros).mockResolvedValue(parametros({ sensibilidad_radar: { version: 2 } }))
    renderPagina()
    await userEvent.click(await screen.findByRole('radio', { name: /Alta/ }))
    await userEvent.click(screen.getByRole('button', { name: 'Guardar' }))

    await waitFor(() => expect(guardarParametros).toHaveBeenCalledWith([{ clave: 'sensibilidad_radar', valor: 3, version: 2 }]))
  })

  it('muestra el último cambio, y «de valor por defecto» en el primero', async () => {
    vi.mocked(listarParametros).mockResolvedValue(
      parametros({
        plazo_anulacion_dias: { valor: 10, version: 5, ultimo_cambio: { por: 'Marta Cevallos', en: '2026-10-03T15:20:00-05:00', valor_anterior: 7, valor_nuevo: 10 } },
        umbral_concentracion: { valor: 60, version: 6, ultimo_cambio: { por: 'Marta Cevallos', en: '2026-10-04T10:00:00-05:00', valor_anterior: null, valor_nuevo: 60 } },
        sensibilidad_radar: { valor: 3, version: 7, ultimo_cambio: { por: 'Marta Cevallos', en: '2026-10-05T10:00:00-05:00', valor_anterior: 2, valor_nuevo: 3 } },
      }),
    )
    renderPagina()

    expect(await screen.findByText('Último cambio: Marta Cevallos, 3 de octubre de 2026 (de 7 a 10)')).toBeInTheDocument()
    expect(screen.getByText('Último cambio: Marta Cevallos, 4 de octubre de 2026 (de valor por defecto a 60)')).toBeInTheDocument()
    expect(screen.getByText('Último cambio: Marta Cevallos, 5 de octubre de 2026 (de Normal a Alta)')).toBeInTheDocument()
  })

  it('muestra error con Reintentar si falla la carga', async () => {
    vi.mocked(listarParametros).mockRejectedValueOnce(httpError(500)).mockRejectedValueOnce(httpError(500))
    renderPagina()

    await userEvent.click(await screen.findByRole('button', { name: 'Reintentar' }, { timeout: 4000 }))
    vi.mocked(listarParametros).mockResolvedValue(parametros())
    expect(await screen.findByRole('heading', { name: 'Puntos por colaborador al mes' })).toBeInTheDocument()
  })

  describe('pestañas del módulo', () => {
    it('el Gestor ve las pestañas Categorías y Parámetros y puede ir a Categorías', async () => {
      renderPagina()

      const pestanas = await screen.findByRole('tablist', { name: 'Secciones de reconocimientos' })
      expect(within(pestanas).getByRole('tab', { name: 'Parámetros' })).toHaveAttribute('aria-selected', 'true')

      await userEvent.click(within(pestanas).getByRole('tab', { name: 'Categorías' }))
      expect(await screen.findByText('Pantalla de categorías')).toBeInTheDocument()
    })

    it('quien no es Gestor no ve las pestañas ni los parámetros', async () => {
      comoRol('user')
      renderPagina()

      expect(await screen.findByText('Esta sección es solo para el Gestor de Talento.')).toBeInTheDocument()
      expect(screen.queryByRole('tab', { name: 'Parámetros' })).not.toBeInTheDocument()
      expect(listarParametros).not.toHaveBeenCalled()
    })
  })
})
