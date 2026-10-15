import { expect, test, type Locator, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e (TiposUnidadSeeder y UnidadesSeeder; los códigos son UO- + id):
//   UO-001 Gerencia general (Gerencia, raíz)
//     UO-002 Talento humano (Departamento; 1 dependiente activa y 3 posiciones, 2 Ocupadas y 1 Vacante)
//       UO-009 Reclutamiento (Sección)
//     UO-003 Tecnología (Departamento; 2 dependientes activas)
//       UO-004 Inteligencia de negocio (Área) · UO-005 Desarrollo (Área, solo posiciones Suprimidas)
//     UO-006 Finanzas · UO-007 Ventas (Departamentos, sin dependientes ni posiciones)
//     UO-008 Operaciones (Departamento, Inactiva, solo una posición Suprimida)
// Las pruebas son seriales: las que cambian datos los restablecen al terminar (las unidades nunca se eliminan: lo
// creado queda). La base se siembra de nuevo en cada corrida.
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

async function abrirUnidades(page: Page) {
  await page.getByRole('link', { name: 'Unidades', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Unidades organizacionales', level: 1 })).toBeVisible()
}

async function token(page: Page): Promise<string | null> {
  return page.evaluate(() => localStorage.getItem('hrms.token'))
}

async function cabeceras(page: Page) {
  return { Authorization: `Bearer ${await token(page)}`, Accept: 'application/json' }
}

/** Token de la API de otra persona, sin cambiar la sesión de la página. */
async function cabecerasDe(page: Page, email: string) {
  const respuesta = await page.request.post(`${API}/api/auth/login`, { data: { email, password: PASSWORD }, headers: { Accept: 'application/json' } })
  return { Authorization: `Bearer ${(await respuesta.json()).data.token}`, Accept: 'application/json' }
}

/** Nombres de las unidades del árbol, en el orden en que se muestran (sin el prefijo oculto "Nivel N"). */
async function nombresDelArbol(page: Page): Promise<string[]> {
  const textos = await page.getByRole('rowheader').allTextContents()
  return textos.map((texto) => texto.replace(/^Nivel \d+:\s*/, '').trim())
}

/** Encabezado de fila (nombre con sangría) de una unidad. */
const nombreDe = (page: Page, nombre: string) => page.getByRole('rowheader', { name: new RegExp(`^Nivel \\d+: ${nombre}$`) })

/** Fila completa de una unidad. */
const filaDe = (page: Page, nombre: string): Locator => page.getByRole('row').filter({ has: nombreDe(page, nombre) })

/** Sangría (padding izquierdo) del nombre de una unidad. */
const sangriaDe = (page: Page, nombre: string) => nombreDe(page, nombre).locator('span').first()

async function idDeLaUnidad(page: Page, nombre: string): Promise<number> {
  const lista = await page.request.get(`${API}/api/unidades?estado=Todas&q=${encodeURIComponent(nombre)}`, { headers: await cabeceras(page) })
  const unidad = (await lista.json()).data.find((u: { nombre: string }) => u.nombre === nombre)
  return unidad.id
}

/** Cuerpo de un PUT a partir del detalle que devuelve la API, con los cambios indicados. */
async function cuerpoDePut(page: Page, id: number, cambios: Record<string, unknown> = {}) {
  const respuesta = await page.request.get(`${API}/api/unidades/${id}`, { headers: await cabeceras(page) })
  const d = (await respuesta.json()).data
  return {
    nombre: d.nombre,
    tipo_unidad_id: d.tipo.id,
    unidad_padre_id: d.unidad_padre?.id ?? null,
    version: d.version,
    ...cambios,
  }
}

const FORMULARIO_NUEVA = { name: 'Nueva unidad' }

test.describe.configure({ mode: 'serial' })

test('Usuario: consulta las unidades activas en orden jerárquico, busca sin tildes y conserva la búsqueda en la URL', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  await abrirUnidades(page)

  await expect(nombreDe(page, 'Gerencia general')).toBeVisible()
  await expect.poll(() => nombresDelArbol(page)).toEqual([
    'Gerencia general',
    'Talento humano',
    'Reclutamiento', // UO-009 va bajo UO-002, aunque su código es el último
    'Tecnología',
    'Inteligencia de negocio',
    'Desarrollo',
    'Finanzas',
    'Ventas',
  ])
  await expect(page.getByText('8 unidades encontradas')).toBeVisible()
  await expect(page.getByText('Operaciones')).toHaveCount(0) // inactiva: oculta al Usuario

  // Sangría de 24 px por nivel y "—" como padre de la raíz.
  await expect(sangriaDe(page, 'Gerencia general')).toHaveCSS('padding-left', '0px')
  await expect(sangriaDe(page, 'Talento humano')).toHaveCSS('padding-left', '24px')
  await expect(sangriaDe(page, 'Reclutamiento')).toHaveCSS('padding-left', '48px')
  await expect(filaDe(page, 'Gerencia general').getByRole('cell', { name: '—' })).toBeVisible()
  await expect(filaDe(page, 'Reclutamiento').getByRole('cell', { name: 'Talento humano' })).toBeVisible()
  await expect(filaDe(page, 'Ventas').getByText('Activa')).toBeVisible()

  // El Usuario no tiene filtro de estado ni controles de gestión.
  await expect(page.getByLabel('Filtrar por estado')).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Nueva unidad' })).toHaveCount(0)
  await expect(page.getByRole('button', { name: /editar|desactivar|reactivar|eliminar/i })).toHaveCount(0)
  await expect(page.getByRole('columnheader', { name: 'Acciones' })).toHaveCount(0)

  // La búsqueda por tipo ignora mayúsculas y tildes y conserva la sangría real de cada coincidencia.
  await page.getByLabel('Buscar unidad').fill('area')
  await expect(page).toHaveURL(/q=area/)
  await expect(page.getByText('2 unidades encontradas')).toBeVisible()
  await expect.poll(() => nombresDelArbol(page)).toEqual(['Inteligencia de negocio', 'Desarrollo'])
  await expect(sangriaDe(page, 'Desarrollo')).toHaveCSS('padding-left', '48px')

  await page.getByLabel('Buscar unidad').fill('TECNOLOGIA')
  await expect(page.getByText('1 unidad encontrada')).toBeVisible()
  await expect.poll(() => nombresDelArbol(page)).toEqual(['Tecnología'])
  await expect(sangriaDe(page, 'Tecnología')).toHaveCSS('padding-left', '24px')

  await page.reload()
  await expect(page.getByLabel('Buscar unidad')).toHaveValue('TECNOLOGIA')
  await expect.poll(() => nombresDelArbol(page)).toEqual(['Tecnología'])

  // Por código.
  await page.getByLabel('Buscar unidad').fill('uo-009')
  await expect.poll(() => nombresDelArbol(page)).toEqual(['Reclutamiento'])

  // Sin coincidencias.
  await page.getByLabel('Buscar unidad').fill('zzzz')
  await expect(page.getByText('Ninguna unidad coincide con la búsqueda.')).toBeVisible()
  await page.getByRole('button', { name: 'Limpiar filtros' }).click()
  await expect(page.getByText('8 unidades encontradas')).toBeVisible()
  await expect(page).not.toHaveURL(/q=/)
})

test('Usuario: no ve inactivas aunque lo pida, consulta una por su identificador sin auditoría y no puede escribir ni eliminar', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  const headers = await cabeceras(page)

  const lista = await page.request.get(`${API}/api/unidades?estado=Todas`, { headers })
  expect((await lista.json()).meta.total).toBe(8)

  const inactiva = await page.request.get(`${API}/api/unidades/8`, { headers })
  expect(inactiva.status()).toBe(200)
  const cuerpo = (await inactiva.json()).data
  expect(cuerpo).toMatchObject({ codigo: 'UO-008', nombre: 'Operaciones', estado: 'Inactiva' })
  expect(cuerpo.fecha_supresion).not.toBeNull()
  expect(cuerpo).not.toHaveProperty('auditoria')

  expect((await page.request.get(`${API}/api/unidades/9999`, { headers })).status()).toBe(404)
  expect([404, 405]).toContain((await page.request.delete(`${API}/api/unidades/1`, { headers })).status())

  // Escrituras y opciones: el backend verifica el rol aunque se salte la interfaz.
  expect((await page.request.post(`${API}/api/unidades`, { headers, data: {} })).status()).toBe(403)
  expect((await page.request.put(`${API}/api/unidades/1`, { headers, data: {} })).status()).toBe(403)
  expect((await page.request.patch(`${API}/api/unidades/1/estado`, { headers, data: { estado: 'Inactiva' } })).status()).toBe(403)
  expect((await page.request.get(`${API}/api/unidades/opciones`, { headers })).status()).toBe(403)
})

test('Administrador: alterna el filtro de estado entre activas, inactivas y todas, lo conserva en la URL y ve la auditoría', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirUnidades(page)

  await expect(page.getByRole('button', { name: 'Nueva unidad' })).toBeVisible()
  await expect(page.getByLabel('Filtrar por estado')).toHaveValue('Activa')
  await expect(page.getByText('8 unidades encontradas')).toBeVisible()
  await expect(page.getByRole('columnheader', { name: 'Acciones' })).toBeVisible()
  await expect(page.getByRole('button', { name: /eliminar/i })).toHaveCount(0)

  await page.getByLabel('Filtrar por estado').selectOption('Inactiva')
  await expect(page).toHaveURL(/estado=Inactiva/)
  await expect(page.getByText('1 unidad encontrada')).toBeVisible()
  await expect.poll(() => nombresDelArbol(page)).toEqual(['Operaciones'])
  await expect(filaDe(page, 'Operaciones').getByText('Inactiva')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Reactivar Operaciones' })).toBeVisible()

  await page.reload()
  await expect(page.getByLabel('Filtrar por estado')).toHaveValue('Inactiva')
  await expect.poll(() => nombresDelArbol(page)).toEqual(['Operaciones'])

  await page.getByLabel('Filtrar por estado').selectOption('Todas')
  await expect(page.getByText('9 unidades encontradas')).toBeVisible()

  await page.getByLabel('Filtrar por estado').selectOption('Activa')
  await expect(page).not.toHaveURL(/estado=/)
  await expect(page.getByText('8 unidades encontradas')).toBeVisible()

  // El Administrador sí recibe la auditoría en el detalle.
  const detalle = await page.request.get(`${API}/api/unidades/8`, { headers: await cabeceras(page) })
  const auditoria = (await detalle.json()).data.auditoria
  expect(auditoria.created_by.nombre).toBeTruthy()
  expect(auditoria.updated_by).toBeNull()
})

test('Administrador: crea una unidad con validación por campo, ve "Ninguna" deshabilitada y la encuentra en el árbol con su sangría', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirUnidades(page)

  await page.getByRole('button', { name: 'Nueva unidad' }).click()
  const formulario = page.getByRole('form', FORMULARIO_NUEVA)
  await expect(formulario).toBeVisible()
  await expect(formulario.getByRole('textbox', { name: 'Nombre' })).toBeFocused()
  await expect(formulario.getByLabel('Estado')).toHaveCount(0) // el estado no se elige
  await expect(formulario.getByRole('option', { name: 'Ninguna (unidad raíz)' })).toBeDisabled() // ya existe una raíz activa

  // Formulario vacío: aviso general y errores junto a cada campo.
  await formulario.getByRole('button', { name: 'Guardar unidad' }).click()
  await expect(formulario.getByText('Revisa los campos marcados antes de guardar.')).toBeVisible()
  await expect(formulario.getByText('El nombre es obligatorio.')).toBeVisible()
  await expect(formulario.getByText('Elige el tipo de unidad.')).toBeVisible()
  await expect(formulario.getByText('Elige la unidad padre: ya existe una unidad raíz activa.')).toBeVisible()

  // Las unidades candidatas llegan en orden jerárquico y con su código.
  await expect(formulario.getByRole('option', { name: 'Finanzas (UO-006)' })).toBeAttached()
  await expect(formulario.getByRole('option', { name: 'Operaciones (UO-008)' })).toHaveCount(0) // inactiva: no se ofrece

  await formulario.getByRole('textbox', { name: 'Nombre' }).fill('  Presupuestos  ')
  await formulario.getByRole('combobox', { name: 'Unidad padre' }).selectOption({ value: '6' }) // Finanzas
  await formulario.getByRole('combobox', { name: 'Tipo de unidad' }).selectOption({ label: 'Sección' })
  await formulario.getByRole('button', { name: 'Guardar unidad' }).click()

  await expect(page.getByText('Unidad creada.')).toBeVisible()
  await expect(page.getByRole('form', FORMULARIO_NUEVA)).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Nueva unidad' })).toBeFocused()
  await expect(page.getByText('9 unidades encontradas')).toBeVisible()

  // Aparece bajo Finanzas (nivel 2, sangría de 48 px), con el siguiente código.
  await expect.poll(async () => {
    const nombres = await nombresDelArbol(page)
    return nombres.indexOf('Presupuestos') - nombres.indexOf('Finanzas')
  }).toBe(1)
  await expect(sangriaDe(page, 'Presupuestos')).toHaveCSS('padding-left', '48px')
  await expect(filaDe(page, 'Presupuestos').getByText('UO-010')).toBeVisible()
  await expect(filaDe(page, 'Presupuestos').getByText('Sección')).toBeVisible()
  await expect(filaDe(page, 'Presupuestos').getByText('Finanzas')).toBeVisible() // unidad padre
})

test('Administrador: edita nombre y tipo, y reubica una unidad con toda su rama sin que el selector ofrezca la propia unidad ni sus dependientes', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirUnidades(page)

  // Editar Ventas: cambiar el nombre y el tipo.
  await page.getByRole('button', { name: 'Editar Ventas' }).click()
  let formulario = page.getByRole('form', { name: 'Editar unidad UO-007' })
  await expect(formulario).toBeVisible()
  await expect(formulario.getByRole('textbox', { name: 'Nombre' })).toHaveValue('Ventas')
  await expect(formulario.getByRole('combobox', { name: 'Unidad padre' })).toHaveValue('1')
  await expect(formulario.getByRole('group', { name: 'Datos de la unidad' })).toContainText('UO-007')
  await expect(formulario.getByRole('group', { name: 'Datos de la unidad' })).toContainText('Creada por')
  await formulario.getByRole('textbox', { name: 'Nombre' }).fill('Ventas nacionales')
  await formulario.getByRole('combobox', { name: 'Tipo de unidad' }).selectOption({ label: 'Área' })
  await formulario.getByRole('button', { name: 'Guardar unidad' }).click()
  await expect(page.getByText('Unidad actualizada.')).toBeVisible()
  await expect(filaDe(page, 'Ventas nacionales').getByText('Área')).toBeVisible()
  await expect(filaDe(page, 'Ventas nacionales').getByText('UO-007')).toBeVisible() // el código no cambia

  // Restablecerla.
  await page.getByRole('button', { name: 'Editar Ventas nacionales' }).click()
  formulario = page.getByRole('form', { name: 'Editar unidad UO-007' })
  await formulario.getByRole('textbox', { name: 'Nombre' }).fill('Ventas')
  await formulario.getByRole('combobox', { name: 'Tipo de unidad' }).selectOption({ label: 'Departamento' })
  await formulario.getByRole('button', { name: 'Guardar unidad' }).click()
  await expect(filaDe(page, 'Ventas').getByText('Departamento')).toBeVisible()

  // Reubicar Tecnología bajo Finanzas: el selector no ofrece la propia unidad ni sus dependientes.
  await page.getByRole('button', { name: 'Editar Tecnología' }).click()
  formulario = page.getByRole('form', { name: 'Editar unidad UO-003' })
  await expect(formulario).toBeVisible()
  await expect(formulario.getByRole('option', { name: 'Tecnología (UO-003)' })).toHaveCount(0)
  await expect(formulario.getByRole('option', { name: 'Inteligencia de negocio (UO-004)' })).toHaveCount(0)
  await expect(formulario.getByRole('option', { name: 'Desarrollo (UO-005)' })).toHaveCount(0)
  await expect(formulario.getByRole('option', { name: 'Finanzas (UO-006)' })).toBeAttached()
  await formulario.getByRole('combobox', { name: 'Unidad padre' }).selectOption({ value: '6' })
  await formulario.getByRole('button', { name: 'Guardar unidad' }).click()
  await expect(page.getByText('Unidad actualizada.')).toBeVisible()

  // La rama se mueve con ella y conserva su estructura: Finanzas → Tecnología (→ sus dos áreas), Presupuestos.
  await expect.poll(() => nombresDelArbol(page)).toEqual([
    'Gerencia general',
    'Talento humano',
    'Reclutamiento',
    'Finanzas',
    'Tecnología',
    'Inteligencia de negocio',
    'Desarrollo',
    'Presupuestos',
    'Ventas',
  ])
  await expect(sangriaDe(page, 'Tecnología')).toHaveCSS('padding-left', '48px')
  await expect(sangriaDe(page, 'Inteligencia de negocio')).toHaveCSS('padding-left', '72px')
  await expect(filaDe(page, 'Tecnología').getByRole('cell', { name: 'Finanzas' })).toBeVisible()

  // Restaurar Tecnología bajo la raíz.
  await page.getByRole('button', { name: 'Editar Tecnología' }).click()
  formulario = page.getByRole('form', { name: 'Editar unidad UO-003' })
  await formulario.getByRole('combobox', { name: 'Unidad padre' }).selectOption({ value: '1' })
  await formulario.getByRole('button', { name: 'Guardar unidad' }).click()
  await expect(sangriaDe(page, 'Tecnología')).toHaveCSS('padding-left', '24px')
  await expect.poll(async () => (await nombresDelArbol(page)).slice(3, 7)).toEqual(['Tecnología', 'Inteligencia de negocio', 'Desarrollo', 'Finanzas'])

})

test('Administrador: edita una unidad inactiva, guarda sin cambios y ve el conflicto de una edición concurrente', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirUnidades(page)

  // Editar una unidad inactiva (sigue inactiva y muestra su fecha de supresión).
  await page.getByLabel('Filtrar por estado').selectOption('Inactiva')
  await page.getByRole('button', { name: 'Editar Operaciones' }).click()
  let formulario = page.getByRole('form', { name: 'Editar unidad UO-008' })
  await expect(formulario.getByRole('group', { name: 'Datos de la unidad' })).toContainText('Fecha de supresión')
  await expect(formulario.getByRole('group', { name: 'Datos de la unidad' })).not.toContainText('Fecha de supresión—')
  await formulario.getByRole('textbox', { name: 'Nombre' }).fill('Operaciones y logística')
  await formulario.getByRole('button', { name: 'Guardar unidad' }).click()
  await expect(page.getByText('Unidad actualizada.')).toBeVisible()
  await expect(filaDe(page, 'Operaciones y logística').getByText('Inactiva')).toBeVisible()
  await page.getByRole('button', { name: 'Editar Operaciones y logística' }).click()
  await page.getByRole('form', { name: 'Editar unidad UO-008' }).getByRole('textbox', { name: 'Nombre' }).fill('Operaciones')
  await page.getByRole('form', { name: 'Editar unidad UO-008' }).getByRole('button', { name: 'Guardar unidad' }).click()
  await expect(nombreDe(page, 'Operaciones')).toBeVisible()
  await page.getByLabel('Filtrar por estado').selectOption('Activa')

  // Guardar sin cambios: se cierra sin error y no sube la versión.
  const idFinanzas = await idDeLaUnidad(page, 'Finanzas')
  const versionAntes = (await (await page.request.get(`${API}/api/unidades/${idFinanzas}`, { headers: await cabeceras(page) })).json()).data.version
  await page.getByRole('button', { name: 'Editar Finanzas' }).click()
  await page.getByRole('form', { name: 'Editar unidad UO-006' }).getByRole('button', { name: 'Guardar unidad' }).click()
  await expect(page.getByRole('form', { name: 'Editar unidad UO-006' })).toHaveCount(0)
  await expect(page.getByRole('alert')).toHaveCount(0)
  const versionDespues = (await (await page.request.get(`${API}/api/unidades/${idFinanzas}`, { headers: await cabeceras(page) })).json()).data.version
  expect(versionDespues).toBe(versionAntes)

  // Edición concurrente: otra persona guarda primero; al guardar se avisa y "Recargar" trae la versión vigente.
  await page.getByRole('button', { name: 'Editar Finanzas' }).click()
  formulario = page.getByRole('form', { name: 'Editar unidad UO-006' })
  await expect(formulario.getByRole('textbox', { name: 'Nombre' })).toHaveValue('Finanzas')
  const concurrente = await page.request.put(`${API}/api/unidades/${idFinanzas}`, {
    headers: await cabeceras(page),
    data: await cuerpoDePut(page, idFinanzas, { nombre: 'Finanzas corporativas' }),
  })
  expect(concurrente.status()).toBe(200)
  await formulario.getByRole('textbox', { name: 'Nombre' }).fill('Finanzas del grupo')
  await formulario.getByRole('button', { name: 'Guardar unidad' }).click()
  await expect(formulario.getByText('La unidad cambió desde que la abriste. Recarga para ver la versión actual.')).toBeVisible()
  await formulario.getByRole('button', { name: 'Recargar unidad' }).click()
  await expect(formulario.getByRole('textbox', { name: 'Nombre' })).toHaveValue('Finanzas corporativas', { timeout: 15_000 })

  // Con la versión vigente se puede guardar; se restablece el nombre original.
  await formulario.getByRole('textbox', { name: 'Nombre' }).fill('Finanzas')
  await formulario.getByRole('button', { name: 'Guardar unidad' }).click()
  await expect(page.getByText('Unidad actualizada.')).toBeVisible()
  await expect(nombreDe(page, 'Finanzas')).toBeVisible()
  await expect.poll(() => nombresDelArbol(page)).not.toContain('Finanzas corporativas')
})

test('Administrador: Desactivar queda deshabilitado con su motivo, desactiva y reactiva con las reglas y el Usuario ve el cambio', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirUnidades(page)

  // Con unidades dependientes activas o posiciones vigentes, Desactivar está deshabilitado y el motivo es visible.
  const talento = page.getByRole('button', { name: 'Desactivar Talento humano' })
  await expect(talento).toBeDisabled()
  await expect(talento).toHaveAccessibleDescription('No se puede desactivar: tiene 1 unidad dependiente activa y 3 posiciones ocupadas o vacantes.')
  await expect(filaDe(page, 'Talento humano').getByText('tiene 1 unidad dependiente activa y 3 posiciones ocupadas o vacantes')).toBeVisible()
  const tecnologia = page.getByRole('button', { name: 'Desactivar Tecnología' })
  await expect(tecnologia).toBeDisabled()
  await expect(tecnologia).toHaveAccessibleDescription('No se puede desactivar: tiene 2 unidades dependientes activas.')
  await expect(page.getByRole('button', { name: 'Desactivar Gerencia general' })).toBeDisabled()
  await expect(page.getByRole('button', { name: 'Desactivar Ventas' })).toBeEnabled()

  // Desactivar Ventas pide confirmación; Cancelar no cambia nada.
  await page.getByRole('button', { name: 'Desactivar Ventas' }).click()
  const dialogo = page.getByRole('alertdialog', { name: '¿Desactivar la unidad "Ventas"?' })
  await expect(dialogo).toBeVisible()
  await dialogo.getByRole('button', { name: 'Cancelar' }).click()
  await expect(dialogo).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Desactivar Ventas' })).toBeFocused()
  await expect(page.getByText('9 unidades encontradas')).toBeVisible() // las 8 sembradas y la creada antes

  // Confirmar: sale del listado por defecto.
  await page.getByRole('button', { name: 'Desactivar Ventas' }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Desactivar' }).click()
  await expect(page.getByText('Unidad desactivada.')).toBeVisible()
  await expect(nombreDe(page, 'Ventas')).toHaveCount(0)
  await expect(page.getByText('8 unidades encontradas')).toBeVisible()

  // Aparece en "Inactiva" con el botón Reactivar y su fecha de supresión; el Usuario no la ve.
  await page.getByLabel('Filtrar por estado').selectOption('Inactiva')
  await expect.poll(() => nombresDelArbol(page)).toEqual(['Ventas', 'Operaciones']) // hermanas por código: UO-007 antes de UO-008
  await expect(page.getByRole('button', { name: 'Reactivar Ventas' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Desactivar Ventas' })).toHaveCount(0)
  const idVentas = await idDeLaUnidad(page, 'Ventas')
  const enLaApi = await page.request.get(`${API}/api/unidades/${idVentas}`, { headers: await cabeceras(page) })
  expect((await enLaApi.json()).data.fecha_supresion).not.toBeNull()
  const comoUsuario = await page.request.get(`${API}/api/unidades?estado=Todas`, { headers: await cabecerasDe(page, USUARIO.email) })
  const nombresDelUsuario = (await comoUsuario.json()).data.map((u: { nombre: string }) => u.nombre)
  expect(nombresDelUsuario).not.toContain('Ventas')
  expect(nombresDelUsuario).not.toContain('Operaciones')

  // Reactivar actúa sin confirmación.
  await page.getByRole('button', { name: 'Reactivar Ventas' }).click()
  await expect(page.getByText('Unidad reactivada.')).toBeVisible()
  await expect(page.getByRole('alertdialog')).toHaveCount(0)
  await expect.poll(() => nombresDelArbol(page)).toEqual(['Operaciones'])
  await page.getByLabel('Filtrar por estado').selectOption('Activa')
  await expect(nombreDe(page, 'Ventas')).toBeVisible()
  const reactivada = await page.request.get(`${API}/api/unidades/${idVentas}`, { headers: await cabeceras(page) })
  expect((await reactivada.json()).data).toMatchObject({ estado: 'Activa', fecha_supresion: null })
})

test('Administrador: el servidor rechaza con cantidades si algo cambió, y no se reactiva una unidad bajo un padre inactivo', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirUnidades(page)
  const headers = await cabeceras(page)

  // Condición de carrera: mientras el diálogo está abierto, otra persona agrega una dependiente activa a Ventas.
  const idVentas = await idDeLaUnidad(page, 'Ventas')
  await page.getByRole('button', { name: 'Desactivar Ventas', exact: true }).click()
  const dialogo = page.getByRole('alertdialog', { name: '¿Desactivar la unidad "Ventas"?' })
  await expect(dialogo).toBeVisible()
  const nueva = await page.request.post(`${API}/api/unidades`, {
    headers,
    data: { nombre: 'Ventas región norte', tipo_unidad_id: 4, unidad_padre_id: idVentas },
  })
  expect(nueva.status()).toBe(201)
  const idRegion = (await nueva.json()).data.id
  await dialogo.getByRole('button', { name: 'Desactivar' }).click()
  await expect(dialogo.getByText('No se puede desactivar la unidad: tiene 1 unidad dependiente activa.')).toBeVisible()
  await dialogo.getByRole('button', { name: 'Cancelar' }).click()

  // El listado se actualizó: Desactivar Ventas ya aparece deshabilitado con su motivo.
  await expect(page.getByRole('button', { name: 'Desactivar Ventas', exact: true })).toBeDisabled()
  await expect(page.getByRole('button', { name: 'Desactivar Ventas', exact: true })).toHaveAccessibleDescription('No se puede desactivar: tiene 1 unidad dependiente activa.')
  const retirada = await page.request.patch(`${API}/api/unidades/${idRegion}/estado`, { headers, data: { estado: 'Inactiva' } })
  expect(retirada.status()).toBe(200)

  // Secuencia con el padre: desactivar la Sección UO-010, luego Finanzas, y no poder reactivar UO-010 mientras Finanzas esté inactiva.
  await page.reload()
  await page.getByRole('button', { name: 'Desactivar Presupuestos' }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Desactivar' }).click()
  await expect(page.getByText('Unidad desactivada.')).toBeVisible()
  await page.getByRole('button', { name: 'Desactivar Finanzas' }).click() // solo le quedan dependientes inactivas
  await page.getByRole('alertdialog').getByRole('button', { name: 'Desactivar' }).click()
  await expect(page.getByText('Unidad desactivada.')).toBeVisible()
  await expect(nombreDe(page, 'Finanzas')).toHaveCount(0)

  await page.getByLabel('Filtrar por estado').selectOption('Inactiva')
  await page.getByRole('button', { name: 'Reactivar Presupuestos' }).click()
  await expect(page.getByRole('alert')).toContainText('No se puede reactivar la unidad: su unidad padre está inactiva.')
  await expect(filaDe(page, 'Presupuestos').getByText('Inactiva')).toBeVisible()

  // Reactivar el padre primero permite reactivar la hija; nada se reactiva en cascada.
  await page.getByRole('button', { name: 'Reactivar Finanzas' }).click()
  await expect(page.getByText('Unidad reactivada.')).toBeVisible()
  const hija = await page.request.get(`${API}/api/unidades/${await idDeLaUnidad(page, 'Presupuestos')}`, { headers })
  expect((await hija.json()).data.estado).toBe('Inactiva')
  await page.getByRole('button', { name: 'Reactivar Presupuestos' }).click()
  await expect(page.getByText('Unidad reactivada.')).toBeVisible()
})

/** Contraste WCAG entre el color del texto y el fondo efectivo (el del primer ancestro no transparente). */
async function contrasteDe(localizador: Locator): Promise<number> {
  return localizador.evaluate((el) => {
    const lienzo = document.createElement('canvas').getContext('2d', { willReadFrequently: true })!
    const aRgb = (css: string): [number, number, number, number] => {
      lienzo.clearRect(0, 0, 1, 1)
      lienzo.fillStyle = '#000'
      lienzo.fillStyle = css
      lienzo.fillRect(0, 0, 1, 1)
      const [r, g, b, a] = lienzo.getImageData(0, 0, 1, 1).data
      return [r, g, b, a / 255]
    }
    const luminancia = ([r, g, b]: number[]) => {
      const [R, G, B] = [r, g, b].map((c) => {
        const v = c / 255
        return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4
      })
      return 0.2126 * R + 0.7152 * G + 0.0722 * B
    }
    let fondo: [number, number, number, number] = [255, 255, 255, 1]
    for (let nodo: Element | null = el; nodo; nodo = nodo.parentElement) {
      const color = aRgb(getComputedStyle(nodo).backgroundColor)
      if (color[3] > 0) {
        fondo = color
        break
      }
    }
    const texto = aRgb(getComputedStyle(el).color)
    const [claro, oscuro] = [luminancia(texto), luminancia(fondo)].sort((a, b) => b - a)
    return (claro + 0.05) / (oscuro + 0.05)
  })
}

test('Administrador: foco visible con teclado, contraste AA en ambos temas y el foco vuelve a quien abrió el formulario o el diálogo', async ({ page }) => {
  test.setTimeout(90_000)
  await iniciarSesion(page, ADMIN.email)
  await abrirUnidades(page)
  await expect(nombreDe(page, 'Gerencia general')).toBeVisible()

  const grosorDelFoco = () => page.evaluate(() => (document.activeElement ? getComputedStyle(document.activeElement).outlineWidth : null))
  const idConFoco = () => page.evaluate(() => document.activeElement?.id)

  // Orden de tabulación del encabezado y los filtros: Nueva unidad → búsqueda → estado → primera fila.
  await page.getByLabel('Buscar unidad').focus()
  expect(await idConFoco()).toBe('busqueda')
  await page.keyboard.press('Shift+Tab')
  await expect(page.getByRole('button', { name: 'Nueva unidad' })).toBeFocused()
  expect(await grosorDelFoco()).toBe('2px')
  await page.keyboard.press('Tab')
  expect(await idConFoco()).toBe('busqueda')
  expect(await grosorDelFoco()).toBe('2px')
  await page.keyboard.press('Tab')
  expect(await idConFoco()).toBe('filtro-estado')
  expect(await grosorDelFoco()).toBe('2px')
  await page.keyboard.press('Tab')
  await expect(page.getByRole('button', { name: 'Editar Gerencia general' })).toBeFocused()
  expect(await grosorDelFoco()).toBe('2px')
  await page.keyboard.press('Tab') // Desactivar Gerencia general está deshabilitado: el foco salta a la fila siguiente
  await expect(page.getByRole('button', { name: 'Editar Talento humano' })).toBeFocused()

  // Con el teclado se abre el formulario, el foco pasa al nombre y al cancelar vuelve al botón que lo abrió.
  await page.getByRole('button', { name: 'Editar Presupuestos' }).focus()
  await page.keyboard.press('Enter')
  await expect(page.getByRole('textbox', { name: 'Nombre' })).toBeFocused()
  expect(await grosorDelFoco()).toBe('2px')
  await page.keyboard.press('Tab')
  expect(await idConFoco()).toBe('unidad-padre')
  await page.keyboard.press('Tab')
  expect(await idConFoco()).toBe('unidad-tipo')
  await page.getByRole('button', { name: 'Cancelar' }).click()
  await expect(page.getByRole('button', { name: 'Editar Presupuestos' })).toBeFocused()

  // El diálogo atrapa el foco, Escape lo cierra y el foco vuelve al botón Desactivar.
  await page.getByRole('button', { name: 'Desactivar Presupuestos' }).focus()
  await page.keyboard.press('Enter')
  const dialogo = page.getByRole('alertdialog')
  await expect(dialogo.getByRole('button', { name: 'Cancelar' })).toBeFocused()
  await page.keyboard.press('Tab')
  await expect(dialogo.getByRole('button', { name: 'Desactivar' })).toBeFocused()
  await page.keyboard.press('Tab')
  await expect(dialogo.getByRole('button', { name: 'Cancelar' })).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(dialogo).toHaveCount(0)
  await expect(page.getByRole('button', { name: 'Desactivar Presupuestos' })).toBeFocused()

  // Contraste (WCAG AA, 4.5:1 en texto) del árbol, los badges, el motivo, el formulario y el diálogo en ambos temas.
  for (const tema of ['claro', 'oscuro']) {
    if (tema === 'oscuro') {
      await page.getByRole('button', { name: 'Cambiar a tema oscuro' }).click()
      await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
    }
    await page.getByLabel('Filtrar por estado').selectOption('Todas')
    await expect(page.getByText(/unidades encontradas/)).toBeVisible()
    const fila = filaDe(page, 'Talento humano')
    const pares: [string, Locator][] = [
      ['código', fila.getByText('UO-002')],
      ['nombre', fila.getByRole('rowheader')],
      ['tipo', fila.getByText('Departamento', { exact: true })],
      ['unidad padre', fila.getByRole('cell', { name: 'Gerencia general' })],
      ['badge Activa', fila.getByText('Activa', { exact: true })],
      ['badge Inactiva', filaDe(page, 'Operaciones').getByText('Inactiva', { exact: true })],
      ['motivo', fila.getByText(/No se puede desactivar/)],
      ['encabezado', page.getByRole('columnheader', { name: 'Nombre' })],
      ['botón Reactivar', page.getByRole('button', { name: 'Reactivar Operaciones' })],
      ['resumen', page.getByText(/unidades encontradas/)],
      ['nota', page.getByText(/Una unidad solo se puede desactivar/)],
    ]
    for (const [nombre, localizador] of pares) {
      expect(await contrasteDe(localizador), `${nombre} (${tema})`).toBeGreaterThanOrEqual(4.5)
    }

    await page.getByRole('button', { name: 'Editar Talento humano' }).click()
    const formulario = page.getByRole('form', { name: 'Editar unidad UO-002' })
    await expect(formulario.getByRole('group', { name: 'Datos de la unidad' })).toBeVisible()
    await formulario.getByRole('textbox', { name: 'Nombre' }).fill('')
    await formulario.getByRole('button', { name: 'Guardar unidad' }).click()
    const paresDelFormulario: [string, Locator][] = [
      ['título', formulario.getByRole('heading', { name: 'Editar unidad UO-002' })],
      ['etiqueta', formulario.getByText('Nombre', { exact: true })],
      ['dato de solo lectura', formulario.getByText('UO-002', { exact: true })],
      ['título de dato', formulario.getByText('Fecha de creación')],
      ['error del campo', formulario.getByText('El nombre es obligatorio.')],
      ['alerta', formulario.getByText('Revisa los campos marcados antes de guardar.')],
      ['botón Guardar', formulario.getByRole('button', { name: 'Guardar unidad' })],
      ['botón Cancelar', formulario.getByRole('button', { name: 'Cancelar' })],
    ]
    for (const [nombre, localizador] of paresDelFormulario) {
      expect(await contrasteDe(localizador), `${nombre} (${tema})`).toBeGreaterThanOrEqual(4.5)
    }
    await formulario.getByRole('button', { name: 'Cancelar' }).click()

    await page.getByRole('button', { name: 'Desactivar Presupuestos' }).click()
    const confirmacion = page.getByRole('alertdialog')
    for (const [nombre, localizador] of [
      ['título del diálogo', confirmacion.getByRole('heading')],
      ['descripción del diálogo', confirmacion.getByText(/no podrá recibir nuevas posiciones/)],
      ['botón Desactivar del diálogo', confirmacion.getByRole('button', { name: 'Desactivar' })],
    ] as [string, Locator][]) {
      expect(await contrasteDe(localizador), `${nombre} (${tema})`).toBeGreaterThanOrEqual(4.5)
    }
    await page.keyboard.press('Escape')
  }
})

test('Administrador: sin scroll horizontal de la página a 320 y 1440 px, con niveles profundos y el formulario en línea', async ({ page }) => {
  test.setTimeout(90_000)
  await iniciarSesion(page, ADMIN.email)
  const headers = await cabeceras(page)

  // Una rama profunda con nombres de 150 caracteres: seis niveles más bajo Presupuestos (nivel 2).
  let padre = await idDeLaUnidad(page, 'Presupuestos')
  for (let nivel = 1; nivel <= 6; nivel++) {
    const creada = await page.request.post(`${API}/api/unidades`, {
      headers,
      data: { nombre: `${nivel}`.repeat(150), tipo_unidad_id: 4, unidad_padre_id: padre },
    })
    expect(creada.status()).toBe(201)
    padre = (await creada.json()).data.id
  }

  const sinScroll = () => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)

  for (const ancho of [320, 1440]) {
    await page.setViewportSize({ width: ancho, height: 900 })
    await page.goto('/unidades?estado=Todas')
    await expect(page.getByText(/unidades encontradas/)).toBeVisible()
    await expect(page.getByRole('table', { name: 'Unidades organizacionales' })).toBeVisible()
    await expect(sangriaDe(page, '6'.repeat(150))).toHaveCSS('padding-left', '192px') // nivel 8
    expect(await sinScroll()).toBe(true)

    // A 320 px la tabla se desplaza dentro de su contenedor; a 1440 px cabe completa.
    const contenedor = page.getByRole('table', { name: 'Unidades organizacionales' }).locator('xpath=..')
    const desborda = await contenedor.evaluate((el) => el.scrollWidth > el.clientWidth)
    expect(desborda).toBe(ancho === 320)

    // Formulario en línea para crear y para editar la unidad más profunda.
    await page.getByRole('button', { name: 'Nueva unidad' }).click()
    const nuevo = page.getByRole('form', { name: 'Nueva unidad' })
    await nuevo.getByRole('textbox', { name: 'Nombre' }).fill('n'.repeat(150))
    await nuevo.getByRole('button', { name: 'Guardar unidad' }).click() // errores en los demás campos
    await expect(nuevo.getByText('Revisa los campos marcados antes de guardar.')).toBeVisible()
    expect(await sinScroll()).toBe(true)
    await nuevo.getByRole('button', { name: 'Cancelar' }).click()

    await page.getByRole('button', { name: `Editar ${'6'.repeat(150)}` }).click()
    const edicion = page.getByRole('form', { name: /Editar unidad UO-/ })
    await expect(edicion.getByRole('group', { name: 'Datos de la unidad' })).toBeVisible()
    await expect(edicion.getByRole('combobox', { name: 'Unidad padre' })).toBeVisible()
    expect(await sinScroll()).toBe(true)
    await edicion.getByRole('button', { name: 'Cancelar' }).click()
  }
})
