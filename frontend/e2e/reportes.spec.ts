import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e (PlanFormacionSeeder, PersonalSeeder, DnfSeeder, ActividadesSeeder y ReportesSeeder):
//   admin@hrms.test es el Administrador; usuario@hrms.test es Ana Núñez Paredes (el seeder de Reportes no toca sus datos).
//   «[Rep] Taller de reingreso: aplicar lo aprendido» (ACT-0007, Finalizada, costo 1 500) con encuestas, seguimientos de los
//   tres hitos —con alerta de reingreso— y evaluación de impacto; «Mentoring para nuevos líderes» está Finalizada sin evaluación.
// Las pruebas son seriales: el administrador recorre el dashboard, registra una evaluación de impacto y exporta; después se prepara
// una actividad reciente en la que Ana estuvo presente y Ana responde su encuesta pendiente.
const PASSWORD = 'password-de-prueba'
const API = 'http://127.0.0.1:8001'
const ANIO = new Date().getFullYear()
const SUFIJO = Date.now() % 100000
const ACCION = `E2E Taller de reportes ${SUFIJO}`

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

const aIso = (fecha: Date) => `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`
const haceDias = (dias: number) => new Date(Date.now() - dias * 86_400_000)

/** Crea por la API un plan Ejecutando con una acción terminada hace 5 días, su actividad y la asistencia de Ana (Presente). */
async function prepararActividadReciente(page: Page) {
  const headers = await cabeceras(page)
  const opciones = await (await page.request.get(`${API}/api/planes/opciones`, { headers })).json()
  const colaboradores = await (await page.request.get(`${API}/api/planes/colaboradores?q=Ana`, { headers })).json()
  const ana = colaboradores.data[0]
  expect(ana, 'Ana debe existir entre los colaboradores vigentes').toBeTruthy()

  const inicio = aIso(new Date(Math.max(haceDias(12).getTime(), new Date(ANIO, 0, 1).getTime())))
  const fin = aIso(haceDias(5))
  const plan = await page.request.post(`${API}/api/planes`, {
    headers,
    data: {
      nombre: `Plan E2E reportes ${SUFIJO}`,
      anio: ANIO,
      aprobador_id: opciones.aprobadores[0].id,
      acciones: [
        {
          nombre: ACCION,
          tipo: 'Presencial',
          fecha_inicio: inicio,
          fecha_fin: fin,
          duracion_horas: 8,
          formador: 'Ps. Marco Salazar',
          capacidad_maxima: 5,
          costo: 400,
          competencia_ids: [opciones.competencias[0].id],
          colaborador_ids: [ana.id],
        },
      ],
    },
  })
  expect(plan.ok(), await plan.text()).toBeTruthy()
  const planCreado = (await plan.json()).data
  for (const estado of ['Revision', 'Aprobado', 'Ejecutando']) {
    const r = await page.request.patch(`${API}/api/planes/${planCreado.id}/estado`, { headers, data: { estado } })
    expect(r.ok(), await r.text()).toBeTruthy()
  }

  const actividad = await page.request.post(`${API}/api/actividades`, {
    headers,
    data: { plan_accion_id: planCreado.acciones[0].id, lugar_enlace: 'Sala E2E', departamento_id: null },
  })
  expect(actividad.ok(), await actividad.text()).toBeTruthy()
  const creada = (await actividad.json()).data
  const asistencia = await page.request.put(`${API}/api/actividades/${creada.id}/asistencia`, {
    headers,
    data: { version: creada.version, registros: [{ colaborador_id: ana.id, asistencia: 'Presente', calificacion: 5, observaciones: null }] },
  })
  expect(asistencia.ok(), await asistencia.text()).toBeTruthy()
}

test.describe.configure({ mode: 'serial', timeout: 120_000 })

test('Administrador: ve el dashboard con comparación, filtra, abre comentarios anónimos y revisa el impacto a 30-60-90 días', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await page.getByRole('link', { name: 'Reportes', exact: true }).click()

  await expect(page.getByRole('heading', { name: 'Reportes de evaluación de resultados', level: 1 })).toBeVisible()
  for (const nombre of ['% Plan cumplido', 'Total horas', 'Satisfacción promedio', 'Brechas cerradas', 'ROI estimado']) {
    await expect(page.getByRole('article', { name: nombre })).toBeVisible()
  }
  await expect(page.getByRole('article', { name: 'Inversión por colaborador formado' })).toBeVisible()
  await expect(page.getByRole('article', { name: 'ROI estimado' })).not.toContainText('Sin evaluaciones de impacto registradas.')
  await expect(page.getByRole('figure', { name: 'Distribución por tipo de formación' })).toBeVisible()
  await expect(page.getByRole('figure', { name: 'Evolución de brechas' })).toBeVisible()

  // Un filtro por departamento queda en la URL y el dashboard sigue mostrando sus tarjetas.
  await page.getByLabel('Departamento', { exact: true }).selectOption({ index: 1 })
  await expect(page).toHaveURL(/departamento_id=/)
  await expect(page.getByRole('article', { name: '% Plan cumplido' })).toBeVisible()
  await page.getByRole('button', { name: 'Limpiar filtros' }).click()
  await expect(page).not.toHaveURL(/departamento_id=/)

  // Detalle: la actividad sembrada y sus comentarios, sin el nombre del autor.
  await page.getByRole('tab', { name: 'Detalle' }).click()
  const tabla = page.getByRole('table', { name: 'Detalle de actividades formativas del período' })
  const fila = tabla.getByRole('row', { name: /\[Rep\] Taller de reingreso/ })
  await expect(fila).toBeVisible()
  await fila.getByRole('button', { name: /Ver comentarios de satisfacción/ }).click()
  const comentarios = page.getByRole('dialog', { name: /Comentarios de satisfacción/ })
  await expect(comentarios).toContainText('Los ejemplos fueron muy útiles.')
  await expect(comentarios).toContainText('Los comentarios son anónimos')
  await expect(comentarios).not.toContainText(/Luis|María|Carla|José/)
  await comentarios.getByRole('button', { name: 'Cerrar', exact: true }).first().click()
  await expect(comentarios).toBeHidden()

  // Impacto a 30-60-90 días con la alerta de reingreso.
  await page.getByRole('tab', { name: 'Impacto 30-60-90' }).click()
  const seguimiento = page.getByRole('table', { name: 'Seguimiento a 30, 60 y 90 días por actividad' })
  await expect(seguimiento.getByRole('row', { name: /\[Rep\] Taller de reingreso/ })).toContainText('Alerta de reingreso')
  await expect(page.getByRole('alert')).toContainText('alerta de reingreso')
})

test('Administrador: registra la evaluación de impacto de una acción finalizada, ve su ROI y exporta a Excel', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await page.goto('/reportes?vista=impactos')

  const tabla = page.getByRole('table', { name: 'Acciones finalizadas y su evaluación de impacto' })
  const fila = tabla.getByRole('row', { name: /Mentoring para nuevos líderes/ })
  await expect(fila).toContainText('Sin evaluación')
  await fila.getByRole('button', { name: 'Registrar impacto de Mentoring para nuevos líderes' }).click()

  const dialogo = page.getByRole('dialog', { name: /Evaluación de impacto: Mentoring para nuevos líderes/ })
  await dialogo.getByRole('button', { name: 'Guardar' }).click()
  await expect(dialogo.getByText('El indicador de negocio es obligatorio.')).toBeVisible()
  await dialogo.getByLabel('Indicador de negocio').fill('Rotación de líderes')
  await dialogo.getByLabel('Unidad').fill('personas')
  await dialogo.getByLabel('Valor antes').fill('8')
  await dialogo.getByLabel('Valor después').fill('5')
  await dialogo.getByLabel('Beneficio estimado (USD)').fill('1200')
  await dialogo.getByLabel('Justificación').fill('Menos salidas de líderes recién nombrados.')
  // Costo de la acción 600 USD: ROI = (1 200 − 600) / 600 × 100 = 100 %.
  await expect(dialogo.getByText('ROI calculado de la acción').locator('..')).toContainText('100,0 %')
  await dialogo.getByRole('button', { name: 'Guardar' }).click()

  await expect(page.getByText('Se guardó la evaluación de impacto.')).toBeVisible()
  await expect(dialogo).toBeHidden()
  const actualizada = tabla.getByRole('row', { name: /Mentoring para nuevos líderes/ })
  await expect(actualizada).toContainText('Con evaluación')
  await expect(actualizada).toContainText('100,0 %')
  await expect(actualizada.getByRole('button', { name: 'Editar impacto de Mentoring para nuevos líderes' })).toBeVisible()

  // Exportación a Excel con los filtros aplicados.
  await page.goto('/reportes')
  await expect(page.getByRole('article', { name: 'ROI estimado' })).toBeVisible()
  const descarga = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Exportar Excel' }).click()
  expect((await descarga).suggestedFilename()).toMatch(/^reportes-evaluacion-\d{4}-\d{2}-\d{2}_\d{4}-\d{2}-\d{2}\.xlsx$/)
  await expect(page.getByText(/Se descargó reportes-evaluacion-/)).toBeVisible()
})

test('Colaborador: ve «Mi progreso», responde su encuesta pendiente y no accede a las vistas del administrador', async ({ browser }) => {
  const admin = await browser.newPage()
  await iniciarSesion(admin, 'admin@hrms.test')
  await prepararActividadReciente(admin)
  await admin.close()

  const page = await browser.newPage()
  await iniciarSesion(page, 'usuario@hrms.test')
  await page.getByRole('link', { name: 'Reportes', exact: true }).click()

  await expect(page.getByRole('heading', { name: 'Mi progreso', level: 1 })).toBeVisible()
  await expect(page.getByRole('article', { name: 'Formaciones completadas' })).toBeVisible()
  await expect(page.getByRole('article', { name: 'Horas acumuladas' })).toBeVisible()
  await expect(page.getByRole('button', { name: /Exportar/ })).toHaveCount(0)
  await expect(page.getByRole('tab', { name: 'Detalle' })).toHaveCount(0)

  const pendientes = page.getByRole('region', { name: 'Pendientes por responder' })
  await expect(pendientes).toContainText('Encuesta de satisfacción')
  await expect(pendientes).toContainText(ACCION)
  await pendientes.getByRole('button', { name: `Responder Encuesta de satisfacción de ${ACCION}` }).click()

  const dialogo = page.getByRole('dialog', { name: new RegExp(`Encuesta de satisfacción: ${ACCION}`) })
  await dialogo.getByRole('button', { name: 'Enviar' }).click()
  await expect(dialogo.getByText('Elige una calificación de 1 a 5.')).toBeVisible()
  await dialogo.getByRole('radio', { name: '5 de 5' }).check({ force: true })
  await dialogo.getByRole('textbox', { name: /Comentario/ }).fill('Muy útil, gracias.')
  await dialogo.getByRole('button', { name: 'Enviar' }).click()
  await page.getByRole('button', { name: 'Sí, enviar' }).click()

  await expect(page.getByText('Gracias: tu respuesta quedó registrada.')).toBeVisible()
  await expect(pendientes).not.toContainText(ACCION)

  // Una administración no es accesible: la ruta muestra siempre la vista del colaborador.
  const respuesta = await page.request.get(`${API}/api/reportes/dashboard`, { headers: await cabeceras(page) })
  expect(respuesta.status()).toBe(403)
  await page.close()
})

test('foco visible, tema oscuro y sin scroll horizontal a 320 y 1440 px en el dashboard, las tablas y el formulario de impacto', async ({ page }) => {
  const sinScroll = () => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)

  await iniciarSesion(page, 'admin@hrms.test')
  await page.goto('/reportes')
  await page.getByLabel('Desde').focus()
  expect(await page.getByLabel('Desde').evaluate((el) => getComputedStyle(el).outlineWidth)).toBe('2px')
  await page.getByRole('button', { name: 'Cambiar a tema oscuro' }).click()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')

  for (const ancho of [320, 1440]) {
    await page.setViewportSize({ width: ancho, height: 800 })
    for (const vista of ['indicadores', 'detalle', 'seguimiento', 'impactos']) {
      await page.goto(`/reportes?vista=${vista}`)
      await expect(page.getByRole('tab', { selected: true })).toBeVisible()
      await expect(page.getByRole('status', { name: 'Cargando reportes' })).toHaveCount(0)
      expect(await sinScroll(), `sin scroll horizontal en ${vista} a ${ancho}px`).toBe(true)
    }

    await page.goto('/reportes?vista=impactos')
    await page.getByRole('button', { name: /^Registrar impacto de|^Editar impacto de/ }).first().click()
    await expect(page.getByRole('dialog')).toBeVisible()
    expect(await sinScroll(), `sin scroll horizontal con el formulario a ${ancho}px`).toBe(true)
    await page.keyboard.press('Escape')
  }
})

test('Colaborador: «Mi progreso» sin scroll horizontal a 320 y 1440 px y con tema oscuro', async ({ page }) => {
  const sinScroll = () => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)

  await iniciarSesion(page, 'usuario@hrms.test')
  await page.getByRole('button', { name: 'Cambiar a tema oscuro' }).click()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
  for (const ancho of [320, 1440]) {
    await page.setViewportSize({ width: ancho, height: 800 })
    await page.goto('/reportes')
    await expect(page.getByRole('heading', { name: 'Mi progreso', level: 1 })).toBeVisible()
    await expect(page.getByRole('article', { name: 'Formaciones completadas' })).toBeVisible()
    expect(await sinScroll(), `sin scroll horizontal a ${ancho}px`).toBe(true)
  }
})
