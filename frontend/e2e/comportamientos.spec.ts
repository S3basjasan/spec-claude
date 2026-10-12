import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e: siete competencias (COMP-0001…COMP-0007, la última inactiva) y cinco registros de
// comportamientos (COMP-0001, 0002, 0004, 0006 y la inactiva 0007). COMP-0003 y COMP-0005 quedan sin registro.
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

async function abrirComportamientos(page: Page) {
  await page.getByRole('link', { name: 'Comportamientos' }).click()
  await expect(page.getByRole('heading', { name: 'Comportamientos', level: 1 })).toBeVisible()
}

async function token(page: Page): Promise<string | null> {
  return page.evaluate(() => localStorage.getItem('hrms.token'))
}

async function cabeceras(page: Page) {
  return { Authorization: `Bearer ${await token(page)}`, Accept: 'application/json' }
}

test.describe.configure({ mode: 'serial' })

test('Usuario: consulta solo las activas con código y estado, busca, filtra y despliega los comportamientos sin acciones de gestión', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  await abrirComportamientos(page)

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

  await page.getByRole('button', { name: /Visualizar comportamientos.*Gestión de proyectos/ }).click()
  const region = page.getByRole('region', { name: 'Comportamientos de Gestión de proyectos' })
  for (const letra of ['A', 'B', 'C', 'D']) {
    await expect(region.getByRole('heading', { name: `Grado ${letra}` })).toBeVisible()
  }
  await expect(region.getByRole('listitem')).toHaveCount(10) // 3 + 1 + 4 + 2
  await expect(region).toContainText('Dirige portafolios de proyectos.')
  await page.getByRole('button', { name: /Ocultar comportamientos/ }).click()
  await expect(region).toHaveCount(0)
})

test('Usuario: una búsqueda sin coincidencias muestra el estado vacío y permite limpiar', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  await abrirComportamientos(page)

  await page.getByLabel('Buscar por nombre de la competencia').fill('zzzz')
  await expect(page.getByRole('heading', { name: 'No hay resultados' })).toBeVisible()
  await page.getByRole('button', { name: 'Limpiar filtros' }).click()
  await expect(page.getByRole('row')).toHaveCount(5)
})

test('Usuario: no puede escribir por la API ni ver disponibles, no ve inactivas aunque lo pida, pero sí consulta una por su competencia', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  const headers = await cabeceras(page)

  await page.goto('/comportamientos/nuevo')
  await expect(page.getByRole('heading', { name: 'No tienes permisos para esta acción' })).toBeVisible()
  await page.goto('/comportamientos/1/editar')
  await expect(page.getByRole('heading', { name: 'No tienes permisos para esta acción' })).toBeVisible()

  const datos = { competencia_id: 3, comportamientos: { A: ['a'], B: ['b'], C: ['c'], D: ['d'] } }
  expect((await page.request.post(`${API}/api/comportamientos`, { headers, data: datos })).status()).toBe(403)
  expect((await page.request.put(`${API}/api/comportamientos/1`, { headers, data: { ...datos, version: 1 } })).status()).toBe(403)
  expect((await page.request.get(`${API}/api/comportamientos/competencias-disponibles`, { headers })).status()).toBe(403)
  expect([404, 405]).toContain((await page.request.delete(`${API}/api/comportamientos/1`, { headers })).status())

  const lista = await page.request.get(`${API}/api/comportamientos?estado=Todos`, { headers })
  expect((await lista.json()).meta.total).toBe(4)

  const inactiva = await page.request.get(`${API}/api/comportamientos/7`, { headers })
  expect(inactiva.status()).toBe(200)
  expect((await inactiva.json()).data).toMatchObject({ codigo: 'COMP-0007', estado: 'Inactivo' })
})

test('Administrador: alterna el filtro de estado entre activas, inactivas y todas y lo conserva en la URL', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirComportamientos(page)

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

  await fila.getByRole('button', { name: /Visualizar comportamientos/ }).click()
  await expect(page.getByText('La competencia asociada está inactiva')).toBeVisible()
  await expect(page.getByRole('region', { name: 'Comportamientos de Adaptabilidad' }).getByRole('listitem')).toHaveCount(10) // 2 + 3 + 1 + 4

  await page.reload()
  await expect(page.getByLabel('Filtrar por estado')).toHaveValue('Inactivo')
  await expect(page.getByRole('row')).toHaveCount(2)

  await page.getByLabel('Filtrar por estado').selectOption('Todos')
  await expect(page.getByRole('row')).toHaveCount(6)

  await page.getByLabel('Filtrar por estado').selectOption('Activo')
  await expect(page).not.toHaveURL(/estado=/)
})

test('Administrador: crea el registro de COMP-0003 con validación, límites de 1 a 4 y selector con búsqueda', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirComportamientos(page)

  await page.getByRole('link', { name: 'Nuevo registro' }).click()
  await expect(page.getByRole('heading', { name: 'Nuevo registro de comportamientos' })).toBeVisible()
  await page.getByRole('button', { name: 'Guardar' }).click()
  await expect(page.getByText('Revisa los campos marcados antes de guardar.')).toBeVisible()
  await expect(page.getByText('Selecciona una competencia.')).toBeVisible()
  await expect(page.getByText('Ingresa el comportamiento.')).toHaveCount(4)

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

  // Un grado admite de 1 a 4 comportamientos.
  const grupoA = page.getByRole('group', { name: 'Grado A' })
  for (let i = 0; i < 3; i++) await grupoA.getByRole('button', { name: 'Agregar comportamiento' }).click()
  await expect(grupoA.getByRole('textbox')).toHaveCount(4)
  await expect(grupoA.getByRole('button', { name: 'Agregar comportamiento' })).toBeDisabled()
  for (let i = 0; i < 3; i++) await grupoA.getByRole('button', { name: 'Quitar comportamiento 1 del grado A' }).click()
  await expect(grupoA.getByRole('textbox')).toHaveCount(1)
  await expect(grupoA.getByRole('button', { name: 'Quitar comportamiento 1 del grado A' })).toBeDisabled()

  await grupoA.getByRole('button', { name: 'Agregar comportamiento' }).click()
  await page.getByRole('textbox', { name: 'Comportamiento 1 del grado A', exact: true }).fill('Expresa ideas con claridad.')
  await page.getByRole('textbox', { name: 'Comportamiento 2 del grado A', exact: true }).fill('expresa ideas con claridad.')
  for (const letra of ['B', 'C', 'D']) await page.getByRole('textbox', { name: `Comportamiento 1 del grado ${letra}`, exact: true }).fill(`Comportamiento del grado ${letra}.`)
  await page.getByRole('button', { name: 'Guardar' }).click()
  await expect(page.getByText('Este comportamiento ya está en el grado.')).toBeVisible()

  await page.getByRole('textbox', { name: 'Comportamiento 2 del grado A', exact: true }).fill('Escucha activamente.')
  await page.getByRole('button', { name: 'Guardar' }).click()

  await expect(page).toHaveURL(/\/comportamientos$/)
  await expect(page.getByRole('status').filter({ hasText: 'creado' })).toHaveText('Registro de comportamientos creado.')
  await page.getByLabel('Buscar por nombre de la competencia').fill('comunic')
  await expect(page.getByRole('row')).toHaveCount(2)
  await page.getByRole('button', { name: /Visualizar comportamientos/ }).click()
  await expect(page.getByText('Escucha activamente.')).toBeVisible()
})

test('Administrador: edita COMP-0001, el registro inactivo, guarda sin cambios y detecta una edición concurrente', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirComportamientos(page)

  await page.getByRole('link', { name: 'Editar Orientación a resultados' }).click()
  await expect(page.getByRole('heading', { name: 'Editar comportamientos' })).toBeVisible()
  await expect(page.getByRole('combobox')).toHaveCount(0) // la competencia no se cambia
  await expect(page.getByText('COMP-0001')).toBeVisible()
  await expect(page.getByRole('textbox', { name: 'Comportamiento 4 del grado A', exact: true })).toHaveValue('Reconoce y comparte los logros del equipo.')

  await page.getByRole('textbox', { name: 'Comportamiento 1 del grado A', exact: true }).fill('Define metas desafiantes.')
  await page.getByRole('button', { name: 'Quitar comportamiento 4 del grado A' }).click()
  await page.getByRole('group', { name: 'Grado D' }).getByRole('button', { name: 'Agregar comportamiento' }).click()
  await page.getByRole('textbox', { name: 'Comportamiento 2 del grado D', exact: true }).fill('Cumple con apoyo de su jefatura.')
  await page.getByRole('button', { name: 'Guardar' }).click()
  await expect(page.getByRole('status').filter({ hasText: 'actualizado' })).toHaveText('Registro de comportamientos actualizado.')

  await page.getByRole('button', { name: /Visualizar comportamientos.*Orientación a resultados/ }).click()
  const region = page.getByRole('region', { name: 'Comportamientos de Orientación a resultados' })
  await expect(region.getByRole('listitem')).toHaveCount(10) // 3 + 3 + 2 + 2
  await expect(region).toContainText('Define metas desafiantes.')
  await expect(region).toContainText('Cumple con apoyo de su jefatura.')

  // Registro de una competencia inactiva: se puede editar y se avisa.
  await page.getByLabel('Filtrar por estado').selectOption('Inactivo')
  await page.getByRole('link', { name: 'Editar Adaptabilidad' }).click()
  await expect(page.getByText('La competencia asociada está inactiva')).toBeVisible()
  await page.getByRole('button', { name: 'Guardar' }).click() // sin cambios reales
  await expect(page.getByRole('status').filter({ hasText: 'actualizado' })).toBeVisible()
  await expect(page.getByRole('alert')).toHaveCount(0)

  // Edición concurrente: otra persona guarda mientras este formulario está abierto.
  await page.goto('/comportamientos?q=trabajo')
  await page.getByRole('link', { name: 'Editar Trabajo en equipo' }).click()
  await expect(page.getByRole('textbox', { name: 'Comportamiento 1 del grado A', exact: true })).toHaveValue('Construye equipos cohesionados entre áreas.')
  const otra = await page.request.put(`${API}/api/comportamientos/2`, {
    headers: await cabeceras(page),
    data: { comportamientos: { A: ['Texto de otra persona'], B: ['b'], C: ['c'], D: ['d'] }, version: 1 },
  })
  expect(otra.status()).toBe(200)

  await page.getByRole('textbox', { name: 'Comportamiento 1 del grado B', exact: true }).fill('Texto propio')
  await page.getByRole('button', { name: 'Guardar' }).click()
  await expect(page.getByRole('alert').filter({ hasText: 'El registro cambió' })).toBeVisible()

  await page.getByRole('button', { name: 'Recargar registro' }).click()
  await expect(page.getByRole('textbox', { name: 'Comportamiento 1 del grado A', exact: true })).toHaveValue('Texto de otra persona')
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

    await page.goto('/comportamientos?estado=Inactivo')
    await expect(page.getByRole('row', { name: /Trabajo en equipo/ })).toContainText('Inactivo')
    await page.goto('/comportamientos')
    await expect(page.getByText('Trabajo en equipo')).toHaveCount(0)

    const login = await page.request.post(`${API}/api/auth/login`, {
      headers: { Accept: 'application/json' },
      data: { email: USUARIO.email, password: PASSWORD },
    })
    const comoUsuario = { Authorization: `Bearer ${(await login.json()).data.token}`, Accept: 'application/json' }
    const lista = await page.request.get(`${API}/api/comportamientos?q=trabajo&estado=Todos`, { headers: comoUsuario })
    expect((await lista.json()).meta.total).toBe(0)
    const detalle = await page.request.get(`${API}/api/comportamientos/2`, { headers: comoUsuario })
    expect((await detalle.json()).data.estado).toBe('Inactivo')

    await page.goto('/competencias?estado=Inactivo')
    await page.getByRole('button', { name: 'Activar Trabajo en equipo' }).click()
    await expect(page.getByRole('status')).toHaveText('Competencia activada.')

    await page.goto('/comportamientos')
    await expect(page.getByRole('row', { name: /Trabajo en equipo/ })).toContainText('Activo')
  } finally {
    // Deja la competencia activa aunque falle una comprobación intermedia.
    await page.request.patch(`${API}/api/competencias/2/estado`, { headers, data: { estado: 'Activo' } })
  }
})

test('foco visible, tema oscuro y sin scroll horizontal a 320 y 1440 px en el listado y el formulario con 16 campos', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirComportamientos(page)

  await page.getByLabel('Buscar por nombre de la competencia').focus()
  expect(await page.getByLabel('Buscar por nombre de la competencia').evaluate((el) => getComputedStyle(el).outlineWidth)).toBe('2px')

  await page.getByRole('button', { name: 'Cambiar a tema oscuro' }).click()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')

  const sinScroll = () => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)

  for (const ancho of [320, 1440]) {
    await page.setViewportSize({ width: ancho, height: 800 })
    await page.goto('/comportamientos')
    await page.getByLabel('Filtrar por estado').selectOption('Todos')
    await expect(page.getByRole('row')).toHaveCount(7) // encabezado + 6 registros (con el de COMP-0003)
    for (let i = 0; i < 6; i++) await page.getByRole('button', { name: /Visualizar comportamientos/ }).first().click()
    await expect(page.getByRole('button', { name: /Ocultar comportamientos/ })).toHaveCount(6)
    expect(await sinScroll()).toBe(true)

    await page.goto('/comportamientos/nuevo')
    await expect(page.getByRole('heading', { name: 'Nuevo registro de comportamientos' })).toBeVisible()
    const selector = page.getByRole('combobox', { name: 'Competencia' })
    await selector.focus()
    expect(await selector.evaluate((el) => getComputedStyle(el).outlineWidth)).toBe('2px')
    await selector.click()
    await page.getByRole('option', { name: /Pensamiento analítico/ }).click()
    for (const letra of ['A', 'B', 'C', 'D']) {
      const grupo = page.getByRole('group', { name: `Grado ${letra}` })
      for (let i = 0; i < 3; i++) await grupo.getByRole('button', { name: 'Agregar comportamiento' }).click()
      for (let i = 1; i <= 4; i++) await page.getByRole('textbox', { name: `Comportamiento ${i} del grado ${letra}`, exact: true }).fill('Texto largo '.repeat(20).slice(0, 300))
    }
    await expect(page.getByRole('textbox')).toHaveCount(16)
    expect(await sinScroll()).toBe(true)
  }
})
