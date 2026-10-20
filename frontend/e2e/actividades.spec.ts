import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e (PlanFormacionSeeder, PersonalSeeder y ActividadesSeeder):
//   admin@hrms.test es el Administrador y el aprobador designado; usuario@hrms.test es Ana Núñez Paredes.
//   7 actividades ACT-0001…0007 (la 7.ª, «[Rep] Taller de reingreso», la siembra ReportesSeeder sin Ana): Presencial (plan cerrado del año anterior, Finalizada, Ana Presente con 5), Codesarrollo
//   (En curso, Ana Presente con 4), Mentoring (Finalizada sin registros: «Asistencia pendiente»), OJT (Programada),
//   Virtual «[Act] Analítica con tableros» (En curso, Ana Presente sin nota) y E-learning (acción cancelada, Ana Presente con 3).
// Las pruebas son seriales: el administrador crea la actividad de una acción nueva, registra asistencia y después Ana la ve.
// Esta especificación va antes que las demás (orden alfabético), así que parte de la siembra sin tocar.
const PASSWORD = 'password-de-prueba'
const API = 'http://127.0.0.1:8001'
const ANIO = new Date().getFullYear()
const SUFIJO = Date.now() % 100000
const ACCION = `E2E Taller de actividades ${SUFIJO}`

async function iniciarSesion(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible({ timeout: 30_000 })
}

async function cabeceras(page: Page) {
  const token = await page.evaluate(() => localStorage.getItem('hrms.token'))
  return { Authorization: `Bearer ${token}`, Accept: 'application/json', 'Content-Type': 'application/json' }
}

/** Crea por la API un plan Ejecutando con una acción en curso (todo el año) a la que se inscribe Ana. */
async function prepararAccionEjecutando(page: Page) {
  const headers = await cabeceras(page)
  const opciones = await (await page.request.get(`${API}/api/planes/opciones`, { headers })).json()
  const colaboradores = await (await page.request.get(`${API}/api/planes/colaboradores?q=Ana`, { headers })).json()
  const ana = colaboradores.data[0]
  expect(ana, 'Ana debe existir entre los colaboradores vigentes').toBeTruthy()

  const plan = await page.request.post(`${API}/api/planes`, {
    headers,
    data: {
      nombre: `Plan E2E actividades ${SUFIJO}`,
      anio: ANIO,
      aprobador_id: opciones.aprobadores[0].id,
      acciones: [
        {
          nombre: ACCION,
          tipo: 'Presencial',
          fecha_inicio: `${ANIO}-01-01`,
          fecha_fin: `${ANIO}-12-31`,
          duracion_horas: 8,
          formador: 'Ps. Marco Salazar',
          capacidad_maxima: 5,
          costo: 0,
          competencia_ids: [opciones.competencias[0].id],
          colaborador_ids: [ana.id],
        },
      ],
    },
  })
  expect(plan.ok(), await plan.text()).toBeTruthy()
  const id = (await plan.json()).data.id
  for (const estado of ['Revision', 'Aprobado', 'Ejecutando']) {
    const r = await page.request.patch(`${API}/api/planes/${id}/estado`, { headers, data: { estado } })
    expect(r.ok(), await r.text()).toBeTruthy()
  }
}

async function abrirActividades(page: Page) {
  await page.getByRole('link', { name: 'Actividades', exact: true }).click()
}

test.describe.configure({ mode: 'serial', timeout: 120_000 })

test('Administrador: ve las actividades sembradas, el badge de asistencia pendiente y no puede eliminarlas ni cancelarlas', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await abrirActividades(page)

  await expect(page.getByRole('heading', { name: 'Actividades formativas', level: 1 })).toBeVisible()
  const tabla = page.getByRole('table', { name: 'Actividades formativas' })
  // Por defecto solo las del año actual: la del plan cerrado del año anterior no aparece.
  await expect(tabla.getByRole('row', { name: /Comunicación asertiva en el trabajo/ })).toHaveCount(0)
  await expect(tabla.getByRole('row', { name: /Mentoring para nuevos líderes/ })).toContainText('Asistencia pendiente')
  await expect(tabla.getByRole('row', { name: /Codesarrollo: casos reales de equipo/ })).toContainText('En curso')
  await expect(tabla.getByRole('row', { name: /Autoaprendizaje en datos/ })).toContainText('Cancelada')
  await expect(page.getByRole('button', { name: /Eliminar|Cancelar actividad/ })).toHaveCount(0)

  // Cambiar el rango de fechas permite ver otros años.
  await page.getByLabel('Desde').fill(`${ANIO - 1}-01-01`)
  await expect(tabla.getByRole('row', { name: /Comunicación asertiva en el trabajo/ })).toContainText('Finalizada')
})

test('Administrador: crea la actividad de una acción nueva, registra asistencia con confirmación y consulta el historial', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await prepararAccionEjecutando(page)
  await abrirActividades(page)

  await page.getByRole('button', { name: '+ Nueva Actividad' }).click()
  const dialogo = page.getByRole('dialog', { name: 'Nueva actividad formativa' })
  await dialogo.getByRole('combobox', { name: 'Acción del plan' }).fill('E2E Taller')
  await dialogo.getByRole('option', { name: new RegExp(ACCION) }).click()
  const heredados = dialogo.getByRole('region', { name: 'Datos de la acción (solo lectura)' })
  await expect(heredados).toContainText('Ps. Marco Salazar')
  await expect(heredados).toContainText('Presencial')
  await dialogo.getByRole('button', { name: 'Crear actividad' }).click()
  await expect(dialogo.getByText('El lugar o enlace es obligatorio.')).toBeVisible()
  await dialogo.getByLabel('Lugar').fill('Sala de capacitación, piso 3')
  await dialogo.getByRole('button', { name: 'Crear actividad' }).click()
  await expect(dialogo).toBeHidden()
  await expect(page.getByText(/Se creó la actividad ACT-0008/)).toBeVisible()

  const fila = page.getByRole('row', { name: new RegExp(ACCION) })
  await expect(fila).toContainText('ACT-0008')
  await expect(fila).toContainText('En curso')

  // Registro de asistencia: marcar todos presentes, calificar a Ana y guardar.
  await fila.getByRole('link', { name: `Registrar asistencia de ${ACCION}` }).click()
  await expect(page.getByRole('heading', { name: `Asistencia — ${ACCION}`, level: 1 })).toBeVisible()
  const ana = page.getByRole('group', { name: /Asistencia de Ana/ })
  await expect(page.getByRole('spinbutton', { name: /Calificación de Ana/ })).toBeDisabled()
  await page.getByRole('button', { name: 'Marcar todos presentes' }).click()
  await page.getByRole('spinbutton', { name: /Calificación de Ana/ }).fill('5')
  await page.getByRole('textbox', { name: /Observaciones de Ana/ }).fill('Participación destacada')
  await page.getByRole('button', { name: 'Guardar' }).click()
  await expect(page.getByText('Registro guardado.')).toBeVisible()
  await expect(ana.getByRole('button', { name: 'Presente' })).toHaveAttribute('aria-pressed', 'true')

  // Cambiar un registro existente pide confirmación; al pasar a Ausente se elimina la calificación.
  await ana.getByRole('button', { name: 'Ausente' }).click()
  await page.getByRole('button', { name: 'Guardar' }).click()
  const confirmacion = page.getByRole('dialog', { name: 'Confirma los cambios en registros existentes' })
  await expect(confirmacion).toContainText('Presente (5) → Ausente')
  await confirmacion.getByRole('button', { name: 'Confirmar y guardar' }).click()
  await expect(page.getByText('Registro guardado.')).toBeVisible()
  await expect(page.getByRole('spinbutton', { name: /Calificación de Ana/ })).toBeDisabled()
  await expect(page.getByRole('spinbutton', { name: /Calificación de Ana/ })).toHaveValue('')

  // Historial con valor anterior y nuevo.
  await page.getByRole('button', { name: 'Ver historial' }).click()
  const historial = page.getByRole('dialog', { name: 'Historial de registros' })
  await expect(historial).toContainText('Presente (5) → Ausente')
  await expect(historial).toContainText('Pendiente → Presente (5)')
  await historial.getByRole('button', { name: 'Cerrar', exact: true }).last().click()

  // Calificaciones agregadas.
  await page.getByRole('link', { name: 'Volver a actividades' }).click()
  await page.getByRole('tab', { name: 'Calificaciones' }).click()
  await expect(page.getByRole('table', { name: 'Calificaciones por actividad' })).toBeVisible()
  await page.getByRole('tab', { name: 'Por participante' }).click()
  await expect(page.getByRole('table', { name: 'Calificaciones por participante' })).toContainText('Ana')
})

test('Administrador: edita el lugar de la actividad mientras el plan no esté cerrado', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await abrirActividades(page)

  await page.getByRole('button', { name: `Editar ${ACCION}` }).click()
  const edicion = page.getByRole('dialog', { name: 'Editar actividad formativa' })
  await expect(edicion.getByRole('combobox', { name: 'Acción del plan' })).toHaveCount(0)
  await edicion.getByLabel('Lugar').fill('Auditorio principal')
  await edicion.getByRole('button', { name: 'Guardar cambios' }).click()
  await expect(page.getByText('Se actualizó la actividad ACT-0008.')).toBeVisible()
  await expect(page.getByRole('row', { name: new RegExp(ACCION) })).toContainText('Auditorio principal')
})

test('Colaborador: ve sus actividades con su asistencia y calificación, sin observaciones, y no accede a la administración', async ({ page }) => {
  await iniciarSesion(page, 'usuario@hrms.test')
  await abrirActividades(page)

  await expect(page.getByRole('heading', { name: 'Mis actividades formativas', level: 1 })).toBeVisible()
  await expect(page.getByRole('list', { name: 'Resumen de mis actividades' })).toBeVisible()
  const proximas = page.getByRole('region', { name: 'Próximas actividades' })
  const historial = page.getByRole('region', { name: 'Historial' })

  const codesarrollo = proximas.getByRole('article').filter({ hasText: 'Codesarrollo: casos reales de equipo' })
  await expect(codesarrollo).toContainText('En curso')
  await expect(codesarrollo).toContainText('Presente')
  await expect(codesarrollo).toContainText('4 / 5')
  await expect(proximas.getByRole('article').filter({ hasText: ACCION })).toContainText('Ausente')

  // El historial incluye la actividad del plan cerrado del año anterior y la cancelada (con lo que se registró).
  const finalizada = historial.getByRole('article').filter({ hasText: 'Comunicación asertiva en el trabajo' })
  await expect(finalizada).toContainText('Finalizada')
  await expect(finalizada).toContainText('5 / 5')
  await expect(historial.getByRole('article').filter({ hasText: 'Autoaprendizaje en datos' })).toContainText('Cancelada')

  // Las observaciones son internas.
  await expect(page.getByText(/Excelente participación|Aporta casos reales|Participación destacada|Observaciones/)).toHaveCount(0)
  await expect(page.getByRole('button', { name: /Nueva Actividad|Editar|Eliminar/ })).toHaveCount(0)

  // La administración es solo del Administrador.
  await page.goto('/actividades/1/asistencia')
  await expect(page.getByText('No tienes permisos para ver esta pantalla')).toBeVisible()
})

test('Colaborador sin ficha: ve un mensaje informativo', async ({ page }) => {
  await iniciarSesion(page, 'sinficha@hrms.test')
  await abrirActividades(page)

  await expect(page.getByText('Aún no tienes actividades', { exact: true })).toBeVisible()
  await expect(page.getByRole('article')).toHaveCount(0)
})
