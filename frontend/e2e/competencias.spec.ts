import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e: siete competencias (COMP-0001…COMP-0007, la última inactiva) y los usuarios de prueba.
const PASSWORD = 'password-de-prueba'
const API = 'http://127.0.0.1:8001'
const ADMIN = { email: 'admin@hrms.test', name: 'Administrador de prueba' }
const USUARIO = { email: 'usuario@hrms.test', name: 'Usuario de prueba' }

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
}

async function llenarFormulario(page: Page, nombre: string, tipo: 'Cardinal' | 'Específica') {
  await page.getByLabel('Nombre', { exact: true }).fill(nombre)
  await page.getByLabel('Descripción', { exact: true }).fill(`Descripción de ${nombre}.`)
  await page.getByLabel('Tipo', { exact: true }).selectOption({ label: tipo })
  for (const letra of ['A', 'B', 'C', 'D']) {
    await page.getByLabel(`Grado ${letra}`, { exact: true }).fill(`Grado ${letra} de ${nombre}.`)
  }
}

async function token(page: Page): Promise<string | null> {
  return page.evaluate(() => localStorage.getItem('hrms.token'))
}

test.describe.configure({ mode: 'serial' })

test('Usuario: consulta solo las activas con código y estado, busca, filtra y despliega los grados sin acciones de gestión', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  await abrirCompetencias(page)

  await expect(page.getByRole('row')).toHaveCount(7) // encabezado + 6 activas
  await expect(page.getByRole('row').nth(1)).toContainText('COMP-0003') // Comunicación efectiva (orden alfabético)
  await expect(page.getByRole('row').nth(1)).toContainText('Activo')
  await expect(page.getByText('Adaptabilidad')).toHaveCount(0) // inactiva: oculta al Usuario
  await expect(page.getByLabel('Filtrar por estado')).toHaveCount(0)
  await expect(page.getByRole('link', { name: 'Nueva competencia' })).toHaveCount(0)
  await expect(page.getByRole('link', { name: /^Editar/ })).toHaveCount(0)
  await expect(page.getByRole('button', { name: /^(Activar|Desactivar)/ })).toHaveCount(0)

  await page.getByLabel('Buscar competencia por nombre').fill('trab')
  await expect(page).toHaveURL(/q=trab/)
  await expect(page.getByRole('row')).toHaveCount(2)
  await expect(page.getByRole('row').nth(1)).toContainText('Trabajo en equipo')

  await page.reload()
  await expect(page.getByLabel('Buscar competencia por nombre')).toHaveValue('trab')
  await expect(page.getByRole('row')).toHaveCount(2)

  await page.getByLabel('Buscar competencia por nombre').fill('')
  await page.getByLabel('Filtrar por tipo').selectOption('Especifica')
  await expect(page).toHaveURL(/tipo=Especifica/)
  await expect(page.getByRole('row')).toHaveCount(3) // Gestión de proyectos y Pensamiento analítico
  await expect(page.getByRole('row').nth(1)).toContainText('Gestión de proyectos')

  await page.getByRole('button', { name: /Visualizar grados.*Gestión de proyectos/ }).click()
  await expect(page.getByText('Grado A', { exact: true })).toBeVisible()
  await expect(page.getByText('Grado D', { exact: true })).toBeVisible()
  await expect(page.getByRole('region', { name: 'Detalle de Gestión de proyectos' })).toContainText('COMP-0006')
  await page.getByRole('button', { name: /Ocultar grados/ }).click()
  await expect(page.getByText('Grado A', { exact: true })).toHaveCount(0)
})

test('Usuario: una búsqueda sin coincidencias muestra el estado vacío y permite limpiar', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  await abrirCompetencias(page)

  await page.getByLabel('Buscar competencia por nombre').fill('zzzz')
  await expect(page.getByRole('heading', { name: 'No hay resultados' })).toBeVisible()
  await page.getByRole('button', { name: 'Limpiar filtros' }).click()
  await expect(page.getByRole('row')).toHaveCount(7)
})

test('Usuario: no puede escribir por la API, no ve inactivas aunque lo pida, pero sí consulta una por su identificador', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  const cabeceras = { Authorization: `Bearer ${await token(page)}`, Accept: 'application/json' }

  await page.goto('/competencias/nueva')
  await expect(page.getByRole('heading', { name: 'No tienes permisos para esta acción' })).toBeVisible()

  expect((await page.request.patch(`${API}/api/competencias/1/estado`, { headers: cabeceras, data: { estado: 'Inactivo' } })).status()).toBe(403)
  expect([404, 405]).toContain((await page.request.delete(`${API}/api/competencias/1`, { headers: cabeceras })).status())

  const lista = await page.request.get(`${API}/api/competencias?estado=Todos`, { headers: cabeceras })
  expect((await lista.json()).meta.total).toBe(6)

  const inactiva = await page.request.get(`${API}/api/competencias/7`, { headers: cabeceras })
  expect(inactiva.status()).toBe(200)
  expect((await inactiva.json()).data).toMatchObject({ codigo: 'COMP-0007', estado: 'Inactivo' })
})

test('Administrador: alterna el filtro de estado entre activas, inactivas y todas y lo conserva en la URL', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirCompetencias(page)

  await expect(page.getByLabel('Filtrar por estado')).toHaveValue('Activo')
  await expect(page.getByRole('row')).toHaveCount(7)
  await expect(page.getByText('Adaptabilidad')).toHaveCount(0)

  await page.getByLabel('Filtrar por estado').selectOption('Inactivo')
  await expect(page).toHaveURL(/estado=Inactivo/)
  await expect(page.getByRole('row')).toHaveCount(2)
  const fila = page.getByRole('row', { name: /Adaptabilidad/ })
  await expect(fila).toContainText('COMP-0007')
  await expect(fila).toContainText('Inactivo')
  await expect(fila.getByRole('button', { name: 'Activar Adaptabilidad' })).toBeVisible()

  await page.reload()
  await expect(page.getByLabel('Filtrar por estado')).toHaveValue('Inactivo')
  await expect(page.getByRole('row')).toHaveCount(2)

  await page.getByLabel('Filtrar por estado').selectOption('Todos')
  await expect(page.getByRole('row')).toHaveCount(8)

  await page.getByLabel('Filtrar por estado').selectOption('Activo')
  await expect(page).not.toHaveURL(/estado=/)
})

test('Administrador: crea una competencia con validación y la ve activa en el listado', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirCompetencias(page)

  await page.getByRole('link', { name: 'Nueva competencia' }).click()
  await page.getByRole('button', { name: 'Guardar competencia' }).click()
  await expect(page.getByText('Revisa los campos marcados antes de guardar.')).toBeVisible()
  await expect(page.getByText('Ingresa el nombre de la competencia.')).toBeVisible()
  await expect(page.getByText('Ingresa la descripción del grado D.')).toBeVisible()

  await llenarFormulario(page, 'Gestión del tiempo', 'Específica')
  await page.getByLabel('Nombre', { exact: true }).fill('Liderazgo')
  await page.getByRole('button', { name: 'Guardar competencia' }).click()
  await expect(page.getByText('Ya existe una competencia con ese nombre.')).toBeVisible()
  await expect(page.getByLabel('Grado B', { exact: true })).toHaveValue('Grado B de Gestión del tiempo.')

  // También colisiona con una competencia inactiva (sin distinguir mayúsculas).
  await page.getByLabel('Nombre', { exact: true }).fill('ADAPTABILIDAD')
  await page.getByRole('button', { name: 'Guardar competencia' }).click()
  await expect(page.getByText('Ya existe una competencia con ese nombre.')).toBeVisible()

  await page.getByLabel('Nombre', { exact: true }).fill('Gestión del tiempo')
  await page.getByRole('button', { name: 'Guardar competencia' }).click()

  await expect(page).toHaveURL(/\/competencias$/)
  await expect(page.getByRole('status')).toHaveText('Competencia creada.')
  await page.getByLabel('Buscar competencia por nombre').fill('tiempo')
  await expect(page.getByRole('row')).toHaveCount(2)
  await expect(page.getByRole('row').nth(1)).toContainText('Activo')
  await page.getByRole('button', { name: /Visualizar grados/ }).click()
  await expect(page.getByText('COMP-0008').last()).toBeVisible()
})

test('Administrador: edita una competencia conservando el código y detecta una edición concurrente', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirCompetencias(page)
  await page.getByLabel('Buscar competencia por nombre').fill('tiempo')
  await expect(page.getByRole('row')).toHaveCount(2)
  await page.getByRole('link', { name: 'Editar Gestión del tiempo' }).click()

  await expect(page.getByLabel('Código', { exact: true })).toHaveValue('COMP-0008')
  await expect(page.getByLabel('Código', { exact: true })).toHaveAttribute('readonly', '')
  await page.getByLabel('Nombre', { exact: true }).fill('Gestión del tiempo y prioridades')
  await page.getByLabel('Grado A', { exact: true }).fill('Grado A editado.')
  await page.getByRole('button', { name: 'Guardar competencia' }).click()
  await expect(page.getByRole('status')).toHaveText('Competencia actualizada.')

  await page.getByLabel('Buscar competencia por nombre').fill('prioridades')
  await expect(page.getByRole('row')).toHaveCount(2)
  await page.getByRole('button', { name: /Visualizar grados/ }).click()
  await expect(page.getByText('COMP-0008').last()).toBeVisible()
  await expect(page.getByText('Grado A editado.')).toBeVisible()

  // Edición concurrente: otra persona guarda mientras este formulario está abierto.
  await page.getByRole('link', { name: /Editar Gestión del tiempo y prioridades/ }).click()
  await expect(page.getByLabel('Código', { exact: true })).toHaveValue('COMP-0008')
  const otra = await page.request.put(`${API}/api/competencias/8`, {
    headers: { Authorization: `Bearer ${await token(page)}`, Accept: 'application/json' },
    data: {
      nombre: 'Gestión del tiempo y prioridades',
      descripcion: 'Cambiada por otra persona.',
      tipo: 'Especifica',
      grados: { A: 'a', B: 'b', C: 'c', D: 'd' },
      version: 2,
    },
  })
  expect(otra.status()).toBe(200)

  await page.getByLabel('Nombre', { exact: true }).fill('Gestión del tiempo (conflicto)')
  await page.getByRole('button', { name: 'Guardar competencia' }).click()
  await expect(page.getByRole('alert').filter({ hasText: 'La competencia cambió' })).toBeVisible()

  await page.getByRole('button', { name: 'Recargar competencia' }).click()
  await expect(page.getByLabel('Descripción', { exact: true })).toHaveValue('Cambiada por otra persona.')
})

test('Administrador: edita una competencia inactiva y sigue inactiva', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirCompetencias(page)
  await page.getByLabel('Filtrar por estado').selectOption('Inactivo')
  await expect(page.getByRole('row')).toHaveCount(2)

  await page.getByRole('link', { name: 'Editar Adaptabilidad' }).click()
  await expect(page.getByLabel('Código', { exact: true })).toHaveValue('COMP-0007')
  await page.getByLabel('Descripción', { exact: true }).fill('Adaptación a los cambios (revisada).')
  await page.getByRole('button', { name: 'Guardar competencia' }).click()
  await expect(page.getByRole('status')).toHaveText('Competencia actualizada.')

  await page.getByLabel('Filtrar por estado').selectOption('Inactivo')
  const fila = page.getByRole('row', { name: /Adaptabilidad/ })
  await expect(fila).toContainText('Adaptación a los cambios (revisada).')
  await expect(fila).toContainText('Inactivo')
})

test('Administrador: desactiva con confirmación y activa sin confirmación; el Usuario deja de verla', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirCompetencias(page)
  await page.getByLabel('Buscar competencia por nombre').fill('prioridades')
  await expect(page.getByRole('row')).toHaveCount(2)

  await page.getByRole('button', { name: 'Desactivar Gestión del tiempo y prioridades' }).click()
  const dialogo = page.getByRole('alertdialog')
  await expect(dialogo).toContainText('¿Desactivar la competencia «Gestión del tiempo y prioridades»?')
  await expect(dialogo).toContainText('no podrá asignarse en nuevos registros')
  await page.keyboard.press('Escape')
  await expect(dialogo).toHaveCount(0)
  await expect(page.getByRole('row')).toHaveCount(2)

  await page.getByRole('button', { name: 'Desactivar Gestión del tiempo y prioridades' }).click()
  await page.getByRole('button', { name: 'Cancelar' }).click()
  await expect(page.getByRole('row')).toHaveCount(2)

  await page.getByRole('button', { name: 'Desactivar Gestión del tiempo y prioridades' }).click()
  await page.getByRole('button', { name: 'Desactivar competencia' }).click()
  await expect(page.getByRole('status')).toHaveText('Competencia desactivada.')
  await expect(page.getByRole('heading', { name: 'No hay resultados' })).toBeVisible() // ya no está entre las activas

  // El Usuario no la ve en su listado, pero sí puede consultarla por su identificador.
  const login = await page.request.post(`${API}/api/auth/login`, {
    headers: { Accept: 'application/json' },
    data: { email: USUARIO.email, password: PASSWORD },
  })
  const cabecerasUsuario = { Authorization: `Bearer ${(await login.json()).data.token}`, Accept: 'application/json' }
  const listaUsuario = await page.request.get(`${API}/api/competencias?q=prioridades&estado=Todos`, { headers: cabecerasUsuario })
  expect((await listaUsuario.json()).meta.total).toBe(0)
  const detalleUsuario = await page.request.get(`${API}/api/competencias/8`, { headers: cabecerasUsuario })
  expect((await detalleUsuario.json()).data.estado).toBe('Inactivo')

  // Activar actúa de inmediato, sin diálogo.
  await page.getByLabel('Filtrar por estado').selectOption('Inactivo')
  await expect(page.getByRole('row')).toHaveCount(2)
  await expect(page.getByRole('row').nth(1)).toContainText('Inactivo')
  await page.getByRole('button', { name: 'Activar Gestión del tiempo y prioridades' }).click()
  await expect(page.getByRole('status')).toHaveText('Competencia activada.')
  await expect(page.getByRole('alertdialog')).toHaveCount(0)
  await expect(page.getByRole('heading', { name: 'No hay resultados' })).toBeVisible() // ya no está entre las inactivas
})

test('foco visible, tema oscuro y sin scroll horizontal en el listado, el formulario y el diálogo', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirCompetencias(page)

  await page.getByLabel('Buscar competencia por nombre').focus()
  expect(await page.getByLabel('Buscar competencia por nombre').evaluate((el) => getComputedStyle(el).outlineWidth)).toBe('2px')

  await page.getByRole('button', { name: 'Cambiar a tema oscuro' }).click()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')

  for (const ancho of [320, 1440]) {
    await page.setViewportSize({ width: ancho, height: 800 })
    await page.getByLabel('Filtrar por estado').selectOption('Todos')
    await expect(page.getByRole('row')).toHaveCount(9) // encabezado + 8 competencias
    await page.getByRole('button', { name: /Visualizar grados/ }).first().click()
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    await page.getByRole('button', { name: /^Desactivar/ }).first().click()
    const dialogo = page.getByRole('alertdialog')
    await expect(dialogo).toBeVisible()
    const caja = await dialogo.boundingBox()
    expect(caja && caja.x >= 0 && caja.x + caja.width <= ancho).toBe(true)
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    await page.keyboard.press('Escape')
    await page.goto('/competencias/nueva')
    await expect(page.getByRole('heading', { name: 'Nueva competencia' })).toBeVisible()
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    await page.goto('/competencias')
  }
})
