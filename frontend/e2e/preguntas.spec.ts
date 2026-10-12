import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e: siete competencias (COMP-0001…COMP-0007, la última inactiva) y cinco registros de
// preguntas (COMP-0001, 0002, 0004, 0006 y la inactiva 0007). COMP-0003 y COMP-0005 quedan sin registro.
// Estas pruebas solo dependen de esos registros; competencias.spec.ts puede haber añadido otras competencias.
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

async function abrirPreguntas(page: Page) {
  await page.getByRole('link', { name: 'Preguntas', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Preguntas', level: 1 })).toBeVisible()
}

async function token(page: Page): Promise<string | null> {
  return page.evaluate(() => localStorage.getItem('hrms.token'))
}

async function cabeceras(page: Page) {
  return { Authorization: `Bearer ${await token(page)}`, Accept: 'application/json' }
}

const pregunta = (page: Page, n: number) => page.getByRole('textbox', { name: `Pregunta ${n}`, exact: true })

test.describe.configure({ mode: 'serial' })

test('Usuario: consulta solo las activas con código y estado, busca, filtra y despliega las cuatro preguntas sin acciones de gestión', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  await abrirPreguntas(page)

  await expect(page.getByRole('row')).toHaveCount(5) // encabezado + 4 activas con registro
  await expect(page.getByRole('row').nth(1)).toContainText('COMP-0006') // Gestión de proyectos (orden alfabético)
  await expect(page.getByRole('row').nth(1)).toContainText('Activo')
  await expect(page.getByText('Adaptabilidad')).toHaveCount(0) // inactiva: oculta al Usuario
  await expect(page.getByLabel('Filtrar por estado')).toHaveCount(0)
  await expect(page.getByRole('link', { name: 'Nuevo registro' })).toHaveCount(0)
  await expect(page.getByRole('link', { name: /^Editar/ })).toHaveCount(0)
  await expect(page.getByRole('button', { name: /activar|desactivar|eliminar/i })).toHaveCount(0)

  await page.getByLabel('Buscar por nombre de la competencia').fill('lider')
  await expect(page).toHaveURL(/q=lider/)
  await expect(page.getByRole('row')).toHaveCount(2)
  await expect(page.getByRole('row').nth(1)).toContainText('Liderazgo')

  await page.reload()
  await expect(page.getByLabel('Buscar por nombre de la competencia')).toHaveValue('lider')
  await expect(page.getByRole('row')).toHaveCount(2)

  await page.getByLabel('Buscar por nombre de la competencia').fill('')
  await page.getByLabel('Filtrar por tipo').selectOption('Especifica')
  await expect(page).toHaveURL(/tipo=Especifica/)
  await expect(page.getByRole('row')).toHaveCount(2)
  await expect(page.getByRole('row').nth(1)).toContainText('Gestión de proyectos')

  await page.getByRole('button', { name: /Visualizar preguntas.*Gestión de proyectos/ }).click()
  const region = page.getByRole('region', { name: 'Preguntas de Gestión de proyectos' })
  await expect(region.getByRole('listitem')).toHaveCount(4)
  await expect(region.getByRole('listitem').first()).toContainText('Explica cómo planificas un proyecto')
  await page.getByRole('button', { name: /Ocultar preguntas/ }).click()
  await expect(region).toHaveCount(0)
})

test('Usuario: una búsqueda sin coincidencias muestra el estado vacío y permite limpiar', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  await abrirPreguntas(page)

  await page.getByLabel('Buscar por nombre de la competencia').fill('zzzz')
  await expect(page.getByRole('heading', { name: 'No hay resultados' })).toBeVisible()
  await page.getByRole('button', { name: 'Limpiar filtros' }).click()
  await expect(page.getByRole('row')).toHaveCount(5)
})

test('Usuario: no puede escribir por la API ni ver disponibles, no ve inactivas aunque lo pida, pero sí consulta una por su competencia', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  const headers = await cabeceras(page)

  await page.goto('/preguntas/nuevo')
  await expect(page.getByRole('heading', { name: 'No tienes permisos para esta acción' })).toBeVisible()
  await page.goto('/preguntas/1/editar')
  await expect(page.getByRole('heading', { name: 'No tienes permisos para esta acción' })).toBeVisible()

  const datos = { competencia_id: 3, preguntas: ['a', 'b', 'c', 'd'] }
  expect((await page.request.post(`${API}/api/preguntas`, { headers, data: datos })).status()).toBe(403)
  expect((await page.request.put(`${API}/api/preguntas/1`, { headers, data: { ...datos, version: 1 } })).status()).toBe(403)
  expect((await page.request.get(`${API}/api/preguntas/competencias-disponibles`, { headers })).status()).toBe(403)
  expect([404, 405]).toContain((await page.request.delete(`${API}/api/preguntas/1`, { headers })).status())

  const lista = await page.request.get(`${API}/api/preguntas?estado=Todos`, { headers })
  expect((await lista.json()).meta.total).toBe(4)

  const inactiva = await page.request.get(`${API}/api/preguntas/7`, { headers })
  expect(inactiva.status()).toBe(200)
  const cuerpo = (await inactiva.json()).data
  expect(cuerpo).toMatchObject({ codigo: 'COMP-0007', estado: 'Inactivo' })
  expect(cuerpo.preguntas).toHaveLength(4)
})

test('Administrador: alterna el filtro de estado entre activas, inactivas y todas y lo conserva en la URL', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirPreguntas(page)

  await expect(page.getByLabel('Filtrar por estado')).toHaveValue('Activo')
  await expect(page.getByRole('row')).toHaveCount(5)
  await expect(page.getByText('Adaptabilidad')).toHaveCount(0)

  await page.getByLabel('Filtrar por estado').selectOption('Inactivo')
  await expect(page).toHaveURL(/estado=Inactivo/)
  await expect(page.getByRole('row')).toHaveCount(2)
  const fila = page.getByRole('row', { name: /Adaptabilidad/ })
  await expect(fila).toContainText('COMP-0007')
  await expect(fila).toContainText('Inactivo')
  await expect(fila.getByRole('link', { name: 'Editar Adaptabilidad' })).toBeVisible()

  await fila.getByRole('button', { name: /Visualizar preguntas/ }).click()
  await expect(page.getByText('La competencia asociada está inactiva')).toBeVisible()
  await expect(page.getByRole('region', { name: 'Preguntas de Adaptabilidad' }).getByRole('listitem')).toHaveCount(4)

  await page.reload()
  await expect(page.getByLabel('Filtrar por estado')).toHaveValue('Inactivo')
  await expect(page.getByRole('row')).toHaveCount(2)

  await page.getByLabel('Filtrar por estado').selectOption('Todos')
  await expect(page.getByRole('row')).toHaveCount(6)

  await page.getByLabel('Filtrar por estado').selectOption('Activo')
  await expect(page).not.toHaveURL(/estado=/)
})

test('Administrador: crea el registro de COMP-0003 con validación, cuatro campos fijos y selector con búsqueda', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirPreguntas(page)

  await page.getByRole('link', { name: 'Nuevo registro' }).click()
  await expect(page.getByRole('heading', { name: 'Nuevo registro de preguntas' })).toBeVisible()
  await page.getByRole('button', { name: 'Guardar' }).click()
  await expect(page.getByText('Revisa los campos marcados antes de guardar.')).toBeVisible()
  await expect(page.getByText('Selecciona una competencia.')).toBeVisible()
  await expect(page.getByText('Ingresa la pregunta.')).toHaveCount(4)

  // Solo competencias activas sin registro: COMP-0003 y COMP-0005 (competencias.spec.ts pudo añadir otras activas).
  const selector = page.getByRole('combobox', { name: 'Competencia' })
  await selector.click()
  await expect(page.getByRole('option', { name: /Comunicación efectiva/ })).toBeVisible()
  await expect(page.getByRole('option', { name: /Pensamiento analítico/ })).toBeVisible()
  await expect(page.getByRole('option', { name: /Orientación a resultados/ })).toHaveCount(0)
  await expect(page.getByRole('option', { name: /Adaptabilidad/ })).toHaveCount(0)

  await selector.fill('comunic')
  await expect(page.getByRole('option')).toHaveCount(1)
  await page.getByRole('option', { name: /Comunicación efectiva/ }).click()
  const resumen = page.getByLabel('Competencia', { exact: true }).and(page.locator('dl'))
  await expect(resumen).toContainText('Comunicación efectiva')
  await expect(resumen).toContainText('Cardinal')

  // Cuatro campos numerados fijos: no hay agregar ni quitar.
  await expect(page.getByRole('textbox')).toHaveCount(4)
  await expect(page.getByRole('button', { name: /agregar|quitar/i })).toHaveCount(0)

  await pregunta(page, 1).fill('¿Cómo explicas una idea compleja?')
  await pregunta(page, 2).fill('¿cómo explicas una idea compleja? ')
  await pregunta(page, 3).fill('Cuéntame de una vez que escuchaste para entender.')
  await pregunta(page, 4).fill('¿Cómo das una mala noticia?')
  await page.getByRole('button', { name: 'Guardar' }).click()
  await expect(page.getByText('Esta pregunta ya está en el registro.')).toBeVisible()

  await pregunta(page, 2).fill('¿Cómo adaptas tu mensaje a distintas personas?')
  await page.getByRole('button', { name: 'Guardar' }).click()

  await expect(page).toHaveURL(/\/preguntas$/)
  await expect(page.getByRole('status').filter({ hasText: 'creado' })).toHaveText('Registro de preguntas creado.')
  await page.getByLabel('Buscar por nombre de la competencia').fill('comunic')
  await expect(page.getByRole('row')).toHaveCount(2)
  await page.getByRole('button', { name: /Visualizar preguntas/ }).click()
  await expect(page.getByText('¿Cómo das una mala noticia?')).toBeVisible()
})

test('Administrador: edita COMP-0001, el registro inactivo, guarda sin cambios y detecta una edición concurrente', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirPreguntas(page)

  await page.getByRole('link', { name: 'Editar Orientación a resultados' }).click()
  await expect(page.getByRole('heading', { name: 'Editar preguntas' })).toBeVisible()
  await expect(page.getByRole('combobox')).toHaveCount(0) // la competencia no se cambia
  await expect(page.getByText('COMP-0001')).toBeVisible()
  await expect(pregunta(page, 4)).toHaveValue('¿Qué haces cuando un resultado se aleja de lo esperado?')

  await pregunta(page, 1).fill('Cuéntame de tu meta más exigente.')
  await pregunta(page, 4).fill('¿Cómo corriges el rumbo cuando algo falla?')
  await page.getByRole('button', { name: 'Guardar' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'actualizado' })).toHaveText('Registro de preguntas actualizado.')

  await page.getByRole('button', { name: /Visualizar preguntas.*Orientación a resultados/ }).click()
  const region = page.getByRole('region', { name: 'Preguntas de Orientación a resultados' })
  await expect(region.getByRole('listitem')).toHaveCount(4)
  await expect(region).toContainText('Cuéntame de tu meta más exigente.')
  await expect(region).toContainText('¿Cómo corriges el rumbo cuando algo falla?')

  // Registro de una competencia inactiva: se puede editar y se avisa.
  await page.getByLabel('Filtrar por estado').selectOption('Inactivo')
  await page.getByRole('link', { name: 'Editar Adaptabilidad' }).click()
  await expect(page.getByText('La competencia asociada está inactiva')).toBeVisible()
  await page.getByRole('button', { name: 'Guardar' }).click() // sin cambios reales
  await expect(page.getByRole('status').filter({ hasText: 'actualizado' })).toBeVisible()
  await expect(page.getByRole('alert')).toHaveCount(0)

  // Edición concurrente: otra persona guarda mientras este formulario está abierto.
  await page.goto('/preguntas?q=trabajo')
  await page.getByRole('link', { name: 'Editar Trabajo en equipo' }).click()
  await expect(pregunta(page, 1)).toHaveValue('Cuéntame de un logro que dependió de la colaboración de todo el equipo.')
  const otra = await page.request.put(`${API}/api/preguntas/2`, {
    headers: await cabeceras(page),
    data: { preguntas: ['Texto de otra persona', 'b', 'c', 'd'], version: 1 },
  })
  expect(otra.status()).toBe(200)

  await pregunta(page, 2).fill('Texto propio')
  await page.getByRole('button', { name: 'Guardar' }).click()
  await expect(page.getByRole('alert').filter({ hasText: 'El registro cambió' })).toBeVisible()

  await page.getByRole('button', { name: 'Recargar registro' }).click()
  await expect(pregunta(page, 1)).toHaveValue('Texto de otra persona')
})

test('Estado derivado: desactivar la competencia en 001 deja el registro inactivo para todos y reactivarla lo devuelve', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  const headers = await cabeceras(page)

  try {
    await page.getByRole('link', { name: 'Competencias', exact: true }).click()
    await page.getByLabel('Buscar competencia por nombre').fill('trabajo en equipo')
    await page.getByRole('button', { name: 'Desactivar Trabajo en equipo' }).click()
    await page.getByRole('button', { name: 'Desactivar competencia' }).click()
    await expect(page.getByRole('status')).toHaveText('Competencia desactivada.')

    await page.goto('/preguntas?estado=Inactivo')
    await expect(page.getByRole('row', { name: /Trabajo en equipo/ })).toContainText('Inactivo')
    await page.goto('/preguntas')
    await expect(page.getByText('Trabajo en equipo')).toHaveCount(0)

    const login = await page.request.post(`${API}/api/auth/login`, {
      headers: { Accept: 'application/json' },
      data: { email: USUARIO.email, password: PASSWORD },
    })
    const comoUsuario = { Authorization: `Bearer ${(await login.json()).data.token}`, Accept: 'application/json' }
    const lista = await page.request.get(`${API}/api/preguntas?q=trabajo&estado=Todos`, { headers: comoUsuario })
    expect((await lista.json()).meta.total).toBe(0)
    const detalle = await page.request.get(`${API}/api/preguntas/2`, { headers: comoUsuario })
    expect((await detalle.json()).data.estado).toBe('Inactivo')

    await page.goto('/competencias?estado=Inactivo')
    await page.getByRole('button', { name: 'Activar Trabajo en equipo' }).click()
    await expect(page.getByRole('status')).toHaveText('Competencia activada.')

    await page.goto('/preguntas')
    await expect(page.getByRole('row', { name: /Trabajo en equipo/ })).toContainText('Activo')
  } finally {
    // Deja la competencia activa aunque falle una comprobación intermedia.
    await page.request.patch(`${API}/api/competencias/2/estado`, { headers, data: { estado: 'Activo' } })
  }
})

test('foco visible, tema oscuro y sin scroll horizontal a 320 y 1440 px en el listado y el formulario con preguntas largas', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirPreguntas(page)

  await page.getByLabel('Buscar por nombre de la competencia').focus()
  expect(await page.getByLabel('Buscar por nombre de la competencia').evaluate((el) => getComputedStyle(el).outlineWidth)).toBe('2px')

  await page.getByRole('button', { name: 'Cambiar a tema oscuro' }).click()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')

  const sinScroll = () => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)

  for (const ancho of [320, 1440]) {
    await page.setViewportSize({ width: ancho, height: 800 })
    await page.goto('/preguntas')
    await page.getByLabel('Filtrar por estado').selectOption('Todos')
    await expect(page.getByRole('row')).toHaveCount(7) // encabezado + 6 registros (con el de COMP-0003)
    for (let i = 0; i < 6; i++) await page.getByRole('button', { name: /Visualizar preguntas/ }).first().click()
    await expect(page.getByRole('button', { name: /Ocultar preguntas/ })).toHaveCount(6)
    expect(await sinScroll()).toBe(true)

    await page.goto('/preguntas/nuevo')
    await expect(page.getByRole('heading', { name: 'Nuevo registro de preguntas' })).toBeVisible()
    const selector = page.getByRole('combobox', { name: 'Competencia' })
    await selector.focus()
    expect(await selector.evaluate((el) => getComputedStyle(el).outlineWidth)).toBe('2px')
    await selector.click()
    await page.getByRole('option', { name: /Pensamiento analítico/ }).click()
    for (const n of [1, 2, 3, 4]) await pregunta(page, n).fill(`Pregunta larga ${n} `.repeat(30).slice(0, 500))
    await expect(page.getByRole('textbox')).toHaveCount(4)
    expect(await sinScroll()).toBe(true)
  }
})
