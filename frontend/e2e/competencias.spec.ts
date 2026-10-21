import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e: siete competencias completas (COMP-0001…COMP-0007: Orientación a resultados,
// Trabajo en equipo [Números], Comunicación efectiva [Categorías], Liderazgo, Pensamiento analítico, Gestión de
// proyectos y Adaptabilidad, la última inactiva) y los usuarios de prueba.
const PASSWORD = 'password-de-prueba'
const API = 'http://127.0.0.1:8001'
const ADMIN = { email: 'admin@hrms.test' }
const USUARIO = { email: 'usuario@hrms.test' }

async function iniciarSesion(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible()
}

async function abrirCompetencias(page: Page) {
  await page.getByRole('link', { name: 'Competencias' }).click()
  await expect(page.getByRole('heading', { name: 'Competencias', level: 1 })).toBeVisible()
  await expect(page.getByRole('article').first()).toBeVisible()
}

async function token(page: Page): Promise<string | null> {
  return page.evaluate(() => localStorage.getItem('hrms.token'))
}

function tarjeta(page: Page, nombre: string) {
  return page.getByRole('article').filter({ hasText: nombre })
}

async function abrirTarjeta(page: Page, nombre: string) {
  await page.getByRole('button', { name: `Ver información adicional de ${nombre}` }).click()
  await expect(tarjeta(page, nombre).getByRole('tablist')).toBeVisible()
}

async function aceptarAviso(page: Page, mensaje: string) {
  const aviso = page.getByRole('alertdialog', { name: mensaje })
  await expect(aviso).toBeVisible()
  await aviso.getByRole('button', { name: 'Aceptar' }).click()
  await expect(aviso).toHaveCount(0)
}

test.describe.configure({ mode: 'serial' })

test('Usuario: consulta solo las activas, busca, filtra y recorre las tres pestañas sin acciones de gestión', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  await abrirCompetencias(page)

  await expect(page.getByRole('article')).toHaveCount(6) // 6 activas; Adaptabilidad (inactiva) no se muestra
  await expect(page.getByRole('article').first()).toContainText('COMP-0001')
  await expect(page.getByText('Adaptabilidad')).toHaveCount(0)
  await expect(page.getByText('6 competencias')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Nueva competencia' })).toHaveCount(0)
  await expect(page.getByRole('button', { name: /^(Activar|Desactivar)/ })).toHaveCount(0)

  await page.getByRole('searchbox', { name: 'Buscar competencia' }).fill('lidér')
  await expect(page).toHaveURL(/q=lid/)
  await expect(page.getByRole('article')).toHaveCount(1)
  await expect(page.getByText('1 competencia', { exact: true })).toBeVisible()

  await page.reload()
  await expect(page.getByRole('searchbox', { name: 'Buscar competencia' })).toHaveValue('lidér')
  await page.getByRole('searchbox', { name: 'Buscar competencia' }).fill('')
  await page.getByLabel('Tipo', { exact: true }).selectOption('Especifica')
  await expect(page).toHaveURL(/tipo=Especifica/)
  await expect(page.getByRole('article')).toHaveCount(2) // Pensamiento analítico y Gestión de proyectos

  await abrirTarjeta(page, 'Gestión de proyectos')
  const detalle = tarjeta(page, 'Gestión de proyectos')
  await expect(detalle.getByText('Planificar, ejecutar y controlar proyectos')).toBeVisible()
  await expect(detalle.getByText('Grado A', { exact: true })).toBeVisible()
  await expect(detalle.getByRole('button', { name: /^Editar/ })).toHaveCount(0)

  await detalle.getByRole('tab', { name: 'Comportamientos' }).click()
  await expect(detalle.getByText('Dirige portafolios de proyectos.')).toBeVisible()
  await detalle.getByRole('tab', { name: 'Preguntas' }).click()
  await expect(detalle.getByText('¿Cómo identificas y gestionas los riesgos de un proyecto?')).toBeVisible()

  await page.getByRole('button', { name: 'Ocultar información adicional de Gestión de proyectos' }).click()
  await expect(page.getByRole('tablist')).toHaveCount(0)
})

test('Usuario: una búsqueda sin coincidencias muestra el mensaje y permite limpiar los filtros', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  await abrirCompetencias(page)

  await page.getByRole('searchbox', { name: 'Buscar competencia' }).fill('zzzz')
  await expect(page.getByText('Ninguna competencia coincide con los filtros.')).toBeVisible()
  await page.getByRole('button', { name: 'Limpiar filtros' }).first().click()
  await expect(page.getByRole('article')).toHaveCount(6)
})

test('Usuario: la vista previa de la escala cambia los nombres solo en pantalla y no ofrece aplicarla', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  await abrirCompetencias(page)
  await abrirTarjeta(page, 'Liderazgo')
  const detalle = tarjeta(page, 'Liderazgo')

  await detalle.getByRole('button', { name: 'Números' }).click()
  await expect(detalle.getByText('Grados de desarrollo · Números (vista previa)')).toBeVisible()
  await expect(detalle.getByText('Nivel 1', { exact: true })).toBeVisible()
  await expect(detalle.getByRole('button', { name: 'Aplicar esta escala' })).toHaveCount(0)

  await detalle.getByRole('button', { name: 'Descartar' }).click()
  await expect(detalle.getByText('Grado A', { exact: true })).toBeVisible()
})

test('Usuario: no puede escribir por la API ni ver inactivas, pero sí consulta una por su identificador', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  const cabeceras = { Authorization: `Bearer ${await token(page)}`, Accept: 'application/json' }

  expect((await page.request.patch(`${API}/api/competencias/1/estado`, { headers: cabeceras, data: { estado: 'Inactivo' } })).status()).toBe(403)
  expect((await page.request.put(`${API}/api/competencias/1/preguntas`, { headers: cabeceras, data: { version: 1, preguntas: ['a', 'b', 'c', 'd'] } })).status()).toBe(403)
  expect([404, 405]).toContain((await page.request.delete(`${API}/api/competencias/1`, { headers: cabeceras })).status())
  expect((await page.request.get(`${API}/api/comportamientos`, { headers: cabeceras })).status()).toBe(404)

  const lista = await page.request.get(`${API}/api/competencias?estado=Todos`, { headers: cabeceras })
  expect((await lista.json()).meta.total).toBe(6)

  const inactiva = await page.request.get(`${API}/api/competencias/7`, { headers: cabeceras })
  expect(inactiva.status()).toBe(200)
  const cuerpo = (await inactiva.json()).data
  expect(cuerpo).toMatchObject({ codigo: 'COMP-0007', estado: 'Inactivo' })
  expect(cuerpo.auditoria).toBeUndefined()
})

test('Administrador: ve activas e inactivas sin filtro de estado y con las acciones de gestión', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirCompetencias(page)

  await expect(page.getByRole('article')).toHaveCount(7)
  await expect(page.getByText('7 competencias')).toBeVisible()
  await expect(page.getByLabel(/estado/i)).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Nueva competencia' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Activar Adaptabilidad' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Desactivar Liderazgo' })).toBeVisible()
})

test('Administrador: crea una competencia completa con la escala Categorías y nombres propios', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirCompetencias(page)

  await page.getByRole('button', { name: 'Nueva competencia' }).click()
  const dialogo = page.getByRole('dialog', { name: 'Nueva competencia' })
  await expect(dialogo.getByLabel('Nombre *')).toBeVisible()

  // Sin completar nada no se guarda y los errores aparecen junto a los campos.
  await dialogo.getByRole('button', { name: 'Guardar competencia' }).click()
  await expect(dialogo.getByText('Ingresa el nombre.')).toBeVisible()
  await expect(dialogo.getByText('Completa los cuatro grados.')).toBeVisible()

  await dialogo.getByLabel('Nombre *').fill('Pensamiento estratégico')
  await dialogo.getByLabel('Tipo *').selectOption({ label: 'Específica' })
  await dialogo.getByLabel('Descripción *').fill('Anticipar escenarios y decidir con visión de largo plazo.')

  await dialogo.getByRole('radio', { name: /Categorías/ }).click()
  const nombres = ['Maestría', 'Avanzado', 'Competente', 'Inicial']
  for (const [indice, nombre] of nombres.entries()) await dialogo.getByLabel(`Categoría ${indice + 1}`).fill(nombre)

  for (const [grado, nombre] of nombres.entries()) {
    await dialogo.getByLabel(nombre, { exact: true }).fill(`Descripción del nivel ${nombre}.`)
    for (const numero of [1, 2, 3, 4]) {
      await dialogo.getByLabel(`${nombre} · Comportamiento ${numero}`).fill(`${nombre}: comportamiento ${numero}.`)
    }
    void grado
  }
  for (const numero of [1, 2, 3, 4]) await dialogo.getByLabel(`Pregunta ${numero}`, { exact: true }).fill(`Pregunta estratégica ${numero}.`)

  await dialogo.getByRole('button', { name: 'Guardar competencia' }).click()
  await aceptarAviso(page, 'Competencia creada correctamente.')

  await expect(page.getByText('COMP-0008')).toBeVisible()
  await expect(page.getByText('8 competencias')).toBeVisible()
  await abrirTarjeta(page, 'Pensamiento estratégico')
  const detalle = tarjeta(page, 'Pensamiento estratégico')
  await expect(detalle.getByText('Maestría', { exact: true })).toBeVisible()
  await detalle.getByRole('tab', { name: 'Preguntas' }).click()
  await expect(detalle.getByText('Pregunta estratégica 4.')).toBeVisible()
})

test('Administrador: edita por separado los comportamientos, las preguntas y la competencia', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirCompetencias(page)
  await abrirTarjeta(page, 'Liderazgo')
  const detalle = tarjeta(page, 'Liderazgo')

  await detalle.getByRole('tab', { name: 'Comportamientos' }).click()
  await detalle.getByRole('button', { name: 'Editar comportamientos de Liderazgo' }).click()
  const editarComportamientos = page.getByRole('dialog', { name: 'Editar comportamientos' })
  await editarComportamientos.getByLabel('Grado A · Comportamiento 1').fill('Inspira con el ejemplo.')
  await editarComportamientos.getByRole('button', { name: 'Guardar cambios' }).click()
  await aceptarAviso(page, 'Comportamientos actualizados correctamente.')
  await expect(detalle.getByText('Inspira con el ejemplo.')).toBeVisible()

  await detalle.getByRole('tab', { name: 'Preguntas' }).click()
  await detalle.getByRole('button', { name: 'Editar preguntas de Liderazgo' }).click()
  const editarPreguntas = page.getByRole('dialog', { name: 'Editar preguntas' })
  await editarPreguntas.getByLabel('Pregunta 1', { exact: true }).fill('¿Cómo inspiras a tu equipo?')
  await editarPreguntas.getByRole('button', { name: 'Guardar cambios' }).click()
  await aceptarAviso(page, 'Preguntas actualizadas correctamente.')
  await expect(detalle.getByText('¿Cómo inspiras a tu equipo?')).toBeVisible()

  await detalle.getByRole('tab', { name: 'Competencia' }).click()
  await detalle.getByRole('button', { name: 'Editar grados de desarrollo de Liderazgo' }).click()
  const editarCompetencia = page.getByRole('dialog', { name: 'Editar competencia' })
  await editarCompetencia.getByLabel('Nombre *').fill('Liderazgo estratégico')
  await editarCompetencia.getByRole('button', { name: 'Guardar cambios' }).click()
  await aceptarAviso(page, 'Competencia actualizada correctamente.')
  await expect(page.getByRole('button', { name: /Liderazgo estratégico/ }).first()).toBeVisible()
})

test('Administrador: aplica otra escala y el cambio persiste sin alterar los textos', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirCompetencias(page)
  await abrirTarjeta(page, 'Orientación a resultados')
  const detalle = tarjeta(page, 'Orientación a resultados')

  await detalle.getByRole('button', { name: 'Números' }).click()
  await detalle.getByRole('button', { name: 'Aplicar esta escala' }).click()
  await aceptarAviso(page, 'Escala de grados actualizada correctamente.')
  await expect(detalle.getByText('Grados de desarrollo · Números')).toBeVisible()
  await expect(detalle.getByText('Nivel 1', { exact: true })).toBeVisible()

  await page.reload()
  await abrirTarjeta(page, 'Orientación a resultados')
  await expect(tarjeta(page, 'Orientación a resultados').getByText('Grados de desarrollo · Números')).toBeVisible()
  await expect(tarjeta(page, 'Orientación a resultados').getByText('Define metas desafiantes para su área')).toBeVisible()
})

test('Administrador: desactiva con confirmación, el Usuario deja de verla y vuelve al reactivarla', async ({ browser, page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirCompetencias(page)

  await page.getByRole('button', { name: 'Desactivar Gestión de proyectos' }).click()
  const confirmacion = page.getByRole('alertdialog', { name: '¿Desactivar «Gestión de proyectos»?' })
  await expect(confirmacion).toContainText('no podrá asignarse en registros nuevos')
  await confirmacion.getByRole('button', { name: 'Cancelar' }).click()
  await expect(page.getByRole('button', { name: 'Desactivar Gestión de proyectos' })).toBeVisible()

  await page.getByRole('button', { name: 'Desactivar Gestión de proyectos' }).click()
  await page.getByRole('alertdialog', { name: '¿Desactivar «Gestión de proyectos»?' }).getByRole('button', { name: 'Desactivar' }).click()
  await aceptarAviso(page, 'Competencia desactivada.')
  await expect(page.getByRole('button', { name: 'Activar Gestión de proyectos' })).toBeVisible()

  const otro = await browser.newContext({ baseURL: 'http://127.0.0.1:5174', locale: 'es-EC' })
  const paginaUsuario = await otro.newPage()
  await iniciarSesion(paginaUsuario, USUARIO.email)
  await abrirCompetencias(paginaUsuario)
  await expect(paginaUsuario.getByText('Gestión de proyectos')).toHaveCount(0)

  await page.getByRole('button', { name: 'Activar Gestión de proyectos' }).click()
  await aceptarAviso(page, 'Competencia activada.')
  await paginaUsuario.reload()
  await expect(paginaUsuario.getByRole('article').filter({ hasText: 'Gestión de proyectos' })).toBeVisible()
  await otro.close()
})

test('Administrador: si otra persona cambió la competencia mientras la editaba, el guardado se rechaza con el aviso de recargar', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirCompetencias(page)
  await abrirTarjeta(page, 'Adaptabilidad')
  const detalle = tarjeta(page, 'Adaptabilidad')
  await detalle.getByRole('tab', { name: 'Preguntas' }).click()
  await detalle.getByRole('button', { name: 'Editar preguntas de Adaptabilidad' }).click()
  const dialogo = page.getByRole('dialog', { name: 'Editar preguntas' })
  await expect(dialogo.getByLabel('Pregunta 1', { exact: true })).toBeVisible()

  // Otra persona guarda primero: sube la versión de la competencia.
  const cabeceras = { Authorization: `Bearer ${await token(page)}`, Accept: 'application/json' }
  const actual = await page.request.get(`${API}/api/competencias/7`, { headers: cabeceras })
  const version = (await actual.json()).data.version
  const otra = await page.request.put(`${API}/api/competencias/7/preguntas`, {
    headers: cabeceras,
    data: { version, preguntas: ['Otra 1', 'Otra 2', 'Otra 3', 'Otra 4'] },
  })
  expect(otra.status()).toBe(200)

  await dialogo.getByLabel('Pregunta 1', { exact: true }).fill('Mi cambio tardío')
  await dialogo.getByRole('button', { name: 'Guardar cambios' }).click()
  await expect(dialogo.getByText('Esta competencia cambió mientras la editabas. Recarga para ver la versión actual.')).toBeVisible()

  await dialogo.getByRole('button', { name: 'Recargar competencia' }).click()
  await expect(dialogo.getByLabel('Pregunta 1', { exact: true })).toHaveValue('Otra 1')
})
