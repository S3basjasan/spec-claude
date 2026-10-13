import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e (PuestosSeeder, sobre las siete competencias de CompetenciasSeeder):
//   PU-001 Analista de datos (Activo, sin posiciones)
//   PU-002 Jefe de talento humano (Activo, 2 Ocupadas y 1 Vacante)
//   PU-003 Desarrollador de software (Activo, 2 Suprimidas)
//   PU-004 Ejecutivo de gestión comercial (Activo, con la competencia inactiva COMP-0007)
//   PU-005 Asistente contable (Inactivo, 1 Suprimida)
//   PU-006 Coordinador de operaciones (Inactivo, sin posiciones)
// Las pruebas son seriales: las que cambian datos los restablecen al terminar.
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

async function abrirPuestos(page: Page) {
  await page.getByRole('link', { name: 'Puestos', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Puestos', level: 1 })).toBeVisible()
}

async function token(page: Page): Promise<string | null> {
  return page.evaluate(() => localStorage.getItem('hrms.token'))
}

async function cabeceras(page: Page) {
  return { Authorization: `Bearer ${await token(page)}`, Accept: 'application/json' }
}

/** Botones de fila del listado: su nombre accesible empieza con el código del puesto. */
const filas = (page: Page) => page.getByRole('button', { name: /PU-\d{3}/ })

test.describe.configure({ mode: 'serial' })

test('Usuario: consulta los puestos activos, busca por código y por nombre sin tildes y despliega el detalle completo', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  await abrirPuestos(page)

  await expect(filas(page)).toHaveCount(4) // solo los activos
  await expect(filas(page).first()).toContainText('PU-001') // orden por nombre: Analista de datos
  await expect(filas(page).first()).toContainText('Analista de datos')
  await expect(filas(page).first()).toContainText('Activo')
  await expect(page.getByText('Asistente contable')).toHaveCount(0) // inactivo: oculto al Usuario
  await expect(page.getByLabel('Filtrar por estado')).toHaveCount(0)
  await expect(page.getByRole('link', { name: 'Nuevo puesto' })).toHaveCount(0)
  await expect(page.getByRole('button', { name: /activar|desactivar|eliminar/i })).toHaveCount(0)

  // Búsqueda por código.
  await page.getByLabel('Buscar puesto').fill('pu-002')
  await expect(page).toHaveURL(/q=pu-002/)
  await expect(filas(page)).toHaveCount(1)
  await expect(filas(page).first()).toContainText('Jefe de talento humano')

  // Búsqueda por nombre sin distinguir tildes ni mayúsculas.
  await page.getByLabel('Buscar puesto').fill('GESTION')
  await expect(filas(page)).toHaveCount(1)
  await expect(filas(page).first()).toContainText('Ejecutivo de gestión comercial')

  await page.reload()
  await expect(page.getByLabel('Buscar puesto')).toHaveValue('GESTION')
  await expect(filas(page)).toHaveCount(1)

  // Sin coincidencias.
  await page.getByLabel('Buscar puesto').fill('zzzz')
  await expect(page.getByText('Ningún puesto coincide con la búsqueda.')).toBeVisible()
  await page.getByRole('button', { name: 'Limpiar filtros' }).click()
  await expect(filas(page)).toHaveCount(4)

  // Detalle completo de la primera fila.
  await filas(page).first().click()
  const region = page.getByRole('region', { name: 'Información de Analista de datos' })
  await expect(region.getByRole('heading', { name: 'Misión' })).toBeVisible()
  await expect(region).toContainText('Transformar los datos de la organización en información confiable')
  await expect(region.getByRole('listitem')).toHaveCount(7) // 4 funciones + 3 requisitos
  await expect(region.getByRole('listitem').first()).toContainText('Recopilar, limpiar y consolidar datos')
  await expect(region.getByRole('listitem').first()).toContainText('Esencial')
  await expect(region.getByRole('heading', { level: 4 })).toHaveText(['Formación', 'Experiencia', 'Capacitación'])
  await expect(region.getByRole('row')).toHaveCount(5) // encabezado + 4 competencias
  await expect(region.getByRole('rowheader', { name: 'Pensamiento analítico' })).toBeVisible()
  await expect(region.getByLabel('Grado A')).toBeVisible()
  await expect(region.getByRole('button', { name: /editar|desactivar|activar/i })).toHaveCount(0)

  // Abrir otra fila cierra la primera.
  await filas(page).nth(1).click()
  await expect(page.getByRole('region', { name: 'Información de Desarrollador de software' })).toBeVisible()
  await expect(region).toHaveCount(0)
})

test('Usuario: no ve inactivos aunque lo pida, pero sí consulta uno por su identificador y no puede eliminar', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  const headers = await cabeceras(page)

  const lista = await page.request.get(`${API}/api/puestos?estado=Todos`, { headers })
  expect((await lista.json()).meta.total).toBe(4)

  const inactivo = await page.request.get(`${API}/api/puestos/5`, { headers })
  expect(inactivo.status()).toBe(200)
  const cuerpo = (await inactivo.json()).data
  expect(cuerpo).toMatchObject({ codigo: 'PU-005', nombre: 'Asistente contable', estado: 'Inactivo' })
  expect(cuerpo.funciones.length).toBeGreaterThan(0)

  expect((await page.request.get(`${API}/api/puestos/9999`, { headers })).status()).toBe(404)
  expect([404, 405]).toContain((await page.request.delete(`${API}/api/puestos/1`, { headers })).status())

  // Escrituras: el backend verifica el rol aunque se salte la interfaz.
  expect((await page.request.post(`${API}/api/puestos`, { headers, data: {} })).status()).toBe(403)
  expect((await page.request.put(`${API}/api/puestos/1`, { headers, data: {} })).status()).toBe(403)
  expect((await page.request.get(`${API}/api/puestos/competencias-disponibles`, { headers })).status()).toBe(403)

  await page.goto('/puestos/nuevo')
  await expect(page.getByRole('heading', { name: 'No tienes permisos para esta acción' })).toBeVisible()
})

test('Administrador: alterna el filtro de estado entre activos, inactivos y todos y lo conserva en la URL', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirPuestos(page)

  await expect(page.getByRole('link', { name: 'Nuevo puesto' })).toBeVisible()
  await expect(page.getByLabel('Filtrar por estado')).toHaveValue('Activo')
  await expect(filas(page)).toHaveCount(4)

  await page.getByLabel('Filtrar por estado').selectOption('Inactivo')
  await expect(page).toHaveURL(/estado=Inactivo/)
  await expect(filas(page)).toHaveCount(2)
  await expect(filas(page).first()).toContainText('Asistente contable')
  await expect(filas(page).first()).toContainText('Inactivo')

  await page.reload()
  await expect(page.getByLabel('Filtrar por estado')).toHaveValue('Inactivo')
  await expect(filas(page)).toHaveCount(2)

  await page.getByLabel('Filtrar por estado').selectOption('Todos')
  await expect(filas(page)).toHaveCount(6)

  await page.getByLabel('Filtrar por estado').selectOption('Activo')
  await expect(page).not.toHaveURL(/estado=/)
  await expect(filas(page)).toHaveCount(4)

  // El detalle de un puesto inactivo avisa que no puede usarse para nuevas posiciones.
  await page.getByLabel('Filtrar por estado').selectOption('Inactivo')
  await filas(page).first().click()
  const region = page.getByRole('region', { name: 'Información de Asistente contable' })
  await expect(region).toContainText('Este puesto está inactivo')
  await expect(region.getByRole('button', { name: /eliminar/i })).toHaveCount(0)
})

test('Administrador: el puesto con una competencia inactiva la muestra señalada y con su grado', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirPuestos(page)

  await filas(page).filter({ hasText: 'Ejecutivo de gestión comercial' }).click()
  const region = page.getByRole('region', { name: 'Información de Ejecutivo de gestión comercial' })
  await expect(region.getByRole('rowheader', { name: /Adaptabilidad/ })).toContainText('Inactiva')
  await expect(region.getByLabel('Grado C')).toBeVisible()
})

test('Administrador: crea un puesto completo con validación por campo, reordena sus funciones y lo ve en el listado', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirPuestos(page)

  await page.getByRole('link', { name: 'Nuevo puesto' }).click()
  await expect(page.getByRole('heading', { name: 'Nuevo puesto', level: 1 })).toBeVisible()
  await expect(page.getByLabel('Código')).toHaveValue('Se asigna al guardar')

  // Formulario incompleto: aviso general y errores junto a cada campo.
  await page.getByRole('button', { name: 'Guardar puesto' }).click()
  await expect(page.getByText('Revisa los campos marcados antes de guardar.')).toBeVisible()
  await expect(page.getByText('Ingresa el nombre del puesto.')).toBeVisible()
  await expect(page.getByText('Elige una competencia.')).toBeVisible()

  await page.getByLabel('Nombre', { exact: true }).fill('Especialista en nómina')
  await page.getByLabel('Descripción', { exact: true }).fill('Gestiona el pago de sueldos y beneficios.')
  await page.getByLabel('Misión', { exact: true }).fill('Asegurar que cada persona reciba su pago completo y a tiempo.')

  // Funciones: una repetida se rechaza; sin ninguna esencial también.
  const funcion = (n: number) => page.getByRole('textbox', { name: `Descripción de la función ${n}`, exact: true })
  await funcion(1).fill('Calcular la nómina mensual.')
  await page.getByRole('button', { name: 'Agregar función' }).click()
  await funcion(2).fill('  CALCULAR la nómina mensual.  ')
  await page.getByRole('combobox', { name: 'Tipo de la función 1' }).selectOption('Complementaria')

  await page.getByRole('textbox', { name: 'Descripción del requisito 1', exact: true }).fill('Título en Contabilidad o afines.')
  await page.getByRole('textbox', { name: 'Descripción del requisito 2', exact: true }).fill('2 años en nómina.')

  await page.getByRole('combobox', { name: 'Competencia 1' }).fill('adapt')
  await expect(page.getByText('Ninguna competencia coincide')).toBeVisible() // la inactiva no se ofrece
  await page.getByRole('combobox', { name: 'Competencia 1' }).fill('lider')
  await page.getByRole('option', { name: /Liderazgo/ }).click()
  await page.getByRole('combobox', { name: 'Grado requerido' }).selectOption('A')
  await expect(page.getByText(/COMP-0004 · Cardinal/)).toBeVisible()

  await page.getByRole('button', { name: 'Guardar puesto' }).click()
  await expect(page.getByText('Esta función está repetida.')).toBeVisible()
  await expect(page.getByText('Incluye al menos una función esencial.')).toBeVisible()

  // Se corrige: segunda función distinta y esencial, tercera función, y se reordena con Subir.
  await funcion(2).fill('Conciliar aportes a la seguridad social.')
  await page.getByRole('combobox', { name: 'Tipo de la función 2' }).selectOption('Esencial')
  await page.getByRole('button', { name: 'Agregar función' }).click()
  await funcion(3).fill('Archivar los roles de pago.')
  await page.getByRole('button', { name: 'Subir función 3' }).click()
  await expect(page.getByText('Función movida a la posición 2 de 3.')).toBeVisible()
  await expect(funcion(2)).toHaveValue('Archivar los roles de pago.')
  await expect(funcion(3)).toHaveValue('Conciliar aportes a la seguridad social.')
  await expect(page.getByRole('button', { name: 'Subir función 1' })).toBeDisabled()
  await expect(page.getByRole('button', { name: 'Bajar función 3' })).toBeDisabled()

  await page.getByRole('button', { name: 'Guardar puesto' }).click()
  await expect(page).toHaveURL(/\/puestos$/)
  await expect(page.getByText('Puesto creado.')).toBeVisible()

  await page.getByLabel('Buscar puesto').fill('nomina')
  await expect(filas(page)).toHaveCount(1)
  await expect(filas(page).first()).toContainText('Especialista en nómina')
  await expect(filas(page).first()).toContainText(/PU-\d{3}/)
  await expect(filas(page).first()).toContainText('Activo')

  // El detalle conserva el orden definido por la persona, con numeración desde 1.
  await filas(page).first().click()
  const region = page.getByRole('region', { name: 'Información de Especialista en nómina' })
  await expect(region.getByRole('listitem').nth(0)).toContainText('Calcular la nómina mensual.')
  await expect(region.getByRole('listitem').nth(1)).toContainText('Archivar los roles de pago.')
  await expect(region.getByRole('listitem').nth(2)).toContainText('Conciliar aportes a la seguridad social.')
  await expect(region.getByRole('rowheader', { name: 'Liderazgo' })).toBeVisible()
  await expect(region.getByLabel('Grado A')).toBeVisible()
})

/** Cuerpo de un PUT a partir del detalle que devuelve la API, con los cambios indicados. */
async function cuerpoDePut(page: Page, id: number, cambios: Record<string, unknown> = {}) {
  const respuesta = await page.request.get(`${API}/api/puestos/${id}`, { headers: await cabeceras(page) })
  const d = (await respuesta.json()).data
  return {
    nombre: d.nombre,
    descripcion: d.descripcion,
    mision: d.mision,
    funciones: d.funciones.map((f: { descripcion: string; tipo: string }) => ({ descripcion: f.descripcion, tipo: f.tipo })),
    requisitos: d.requisitos.map((r: { tipo: string; descripcion: string }) => ({ tipo: r.tipo, descripcion: r.descripcion })),
    competencias: d.competencias.map((c: { competencia_id: number; grado_requerido: string }) => ({
      competencia_id: c.competencia_id,
      grado_requerido: c.grado_requerido,
    })),
    version: d.version,
    ...cambios,
  }
}

async function idDelPuesto(page: Page, texto: string): Promise<number> {
  const lista = await page.request.get(`${API}/api/puestos?estado=Todos&q=${encodeURIComponent(texto)}`, { headers: await cabeceras(page) })
  return (await lista.json()).data[0].id
}

test('Administrador: edita el puesto creado (nombre, orden de funciones, requisito y competencia) y ve los cambios en el detalle', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirPuestos(page)

  await page.getByLabel('Buscar puesto').fill('nomina')
  await filas(page).first().click()
  await page.getByRole('link', { name: 'Editar Especialista en nómina' }).click()

  await expect(page.getByRole('heading', { name: 'Editar puesto', level: 1 })).toBeVisible()
  await expect(page.getByLabel('Código')).toHaveValue(/PU-\d{3}/)
  await expect(page.getByLabel('Código')).toHaveAttribute('readonly', '')
  await expect(page.getByLabel('Nombre', { exact: true })).toHaveValue('Especialista en nómina')
  const funcion = (n: number) => page.getByRole('textbox', { name: `Descripción de la función ${n}`, exact: true })
  await expect(funcion(2)).toHaveValue('Archivar los roles de pago.')
  await expect(page.getByRole('combobox', { name: 'Competencia 1' })).toHaveValue('Liderazgo')

  await page.getByLabel('Nombre', { exact: true }).fill('Especialista en nómina y beneficios')
  await page.getByRole('button', { name: 'Subir función 2' }).click()
  await expect(funcion(1)).toHaveValue('Archivar los roles de pago.')
  await page.getByRole('button', { name: 'Agregar requisito' }).click()
  await page.getByRole('textbox', { name: 'Descripción del requisito 3', exact: true }).fill('Curso de legislación laboral.')
  await page.getByRole('button', { name: 'Agregar competencia' }).click()
  await page.getByRole('combobox', { name: 'Competencia 2' }).fill('trabajo')
  await page.getByRole('option', { name: /Trabajo en equipo/ }).click()
  await page.getByRole('combobox', { name: 'Grado requerido' }).last().selectOption('B')
  await page.getByRole('button', { name: 'Quitar competencia 1' }).click()

  await page.getByRole('button', { name: 'Guardar puesto' }).click()
  await expect(page).toHaveURL(/\/puestos$/)
  await expect(page.getByText('Puesto actualizado.')).toBeVisible()

  await page.getByLabel('Buscar puesto').fill('beneficios')
  await expect(filas(page)).toHaveCount(1)
  await filas(page).first().click()
  const region = page.getByRole('region', { name: 'Información de Especialista en nómina y beneficios' })
  await expect(region.getByRole('listitem').nth(0)).toContainText('Archivar los roles de pago.')
  await expect(region.getByRole('listitem').nth(1)).toContainText('Calcular la nómina mensual.')
  await expect(region.getByText('Curso de legislación laboral.')).toBeVisible()
  await expect(region.getByRole('rowheader', { name: 'Trabajo en equipo' })).toBeVisible()
  await expect(region.getByRole('rowheader', { name: 'Liderazgo' })).toHaveCount(0)
})

test('Administrador: edita un puesto inactivo (sigue inactivo) y uno con una competencia inactiva (grado bloqueado)', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirPuestos(page)

  // Puesto inactivo: se puede editar y sigue inactivo.
  await page.getByLabel('Filtrar por estado').selectOption('Inactivo')
  await filas(page).filter({ hasText: 'Asistente contable' }).click()
  await page.getByRole('link', { name: 'Editar Asistente contable' }).click()
  await expect(page.getByText('Este puesto está inactivo')).toBeVisible()
  await page.getByLabel('Misión', { exact: true }).fill('Apoyar el registro contable con exactitud.')
  await page.getByRole('button', { name: 'Guardar puesto' }).click()
  await expect(page).toHaveURL(/\/puestos$/)
  await page.getByLabel('Filtrar por estado').selectOption('Inactivo')
  await expect(filas(page).filter({ hasText: 'Asistente contable' })).toContainText('Inactivo')

  // Puesto con una competencia inactiva: grado bloqueado, solo se puede dejar o quitar.
  await page.getByLabel('Filtrar por estado').selectOption('Activo')
  await filas(page).filter({ hasText: 'Ejecutivo de gestión comercial' }).click()
  await page.getByRole('link', { name: 'Editar Ejecutivo de gestión comercial' }).click()
  await expect(page.getByText('Adaptabilidad')).toBeVisible()
  await expect(page.getByText('Inactiva', { exact: true })).toBeVisible()
  await expect(page.getByLabel('Grado C')).toBeVisible()
  await expect(page.getByText(/La competencia está inactiva: solo puedes dejarla como está o quitarla./)).toBeVisible()
  // Las competencias van por nombre: «Adaptabilidad» (inactiva) es la fila 1 y no tiene selector; las activas sí.
  await expect(page.getByRole('combobox', { name: 'Competencia 1' })).toHaveCount(0)
  await expect(page.getByRole('combobox', { name: 'Competencia 2' })).toHaveValue('Comunicación efectiva')
  await page.getByLabel('Misión', { exact: true }).fill('Generar y mantener relaciones con clientes de su cartera.')
  await page.getByRole('button', { name: 'Guardar puesto' }).click()
  await expect(page).toHaveURL(/\/puestos$/)

  await filas(page).filter({ hasText: 'Ejecutivo de gestión comercial' }).click()
  const region = page.getByRole('region', { name: 'Información de Ejecutivo de gestión comercial' })
  await expect(region.getByRole('rowheader', { name: /Adaptabilidad/ })).toContainText('Inactiva')
  await expect(region.getByLabel('Grado C')).toBeVisible()

  // El servidor también rechaza cambiar el grado de una competencia inactiva (aunque se salte la interfaz).
  const id = await idDelPuesto(page, 'gestion comercial')
  const cuerpo = await cuerpoDePut(page, id)
  const competencias = cuerpo.competencias.map((c: { competencia_id: number; grado_requerido: string }) =>
    c.competencia_id === 7 ? { ...c, grado_requerido: 'A' } : c,
  )
  const rechazo = await page.request.put(`${API}/api/puestos/${id}`, { headers: await cabeceras(page), data: { ...cuerpo, competencias } })
  expect(rechazo.status()).toBe(422)
  expect(Object.keys((await rechazo.json()).errors).some((clave) => clave.endsWith('.grado_requerido'))).toBe(true)
})

test('Administrador: guarda sin cambios sin error y detecta una edición concurrente', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  const id = await idDelPuesto(page, 'beneficios')

  // Sin cambios reales: vuelve al listado sin error y sin subir la versión.
  const antes = (await (await page.request.get(`${API}/api/puestos/${id}`, { headers: await cabeceras(page) })).json()).data
  await page.goto(`/puestos/${id}/editar`)
  await expect(page.getByLabel('Nombre', { exact: true })).toHaveValue('Especialista en nómina y beneficios')
  await page.getByRole('button', { name: 'Guardar puesto' }).click()
  await expect(page).toHaveURL(/\/puestos$/)
  await expect(page.getByRole('alert')).toHaveCount(0)
  const despues = (await (await page.request.get(`${API}/api/puestos/${id}`, { headers: await cabeceras(page) })).json()).data
  expect(despues.version).toBe(antes.version)

  // Edición concurrente: otra persona guarda primero, y el segundo guardado se rechaza.
  await page.goto(`/puestos/${id}/editar`)
  await expect(page.getByLabel('Nombre', { exact: true })).toHaveValue('Especialista en nómina y beneficios')
  const otra = await page.request.put(`${API}/api/puestos/${id}`, {
    headers: await cabeceras(page),
    data: await cuerpoDePut(page, id, { mision: 'Cambiada por otra persona.' }),
  })
  expect(otra.status()).toBe(200)

  await page.getByLabel('Descripción', { exact: true }).fill('Descripción que se quiere guardar.')
  await page.getByRole('button', { name: 'Guardar puesto' }).click()
  await expect(page.getByText('El puesto cambió desde que lo abriste. Recarga para ver la versión actual.')).toBeVisible()

  await page.getByRole('button', { name: 'Recargar puesto' }).click()
  await expect(page.getByLabel('Misión', { exact: true })).toHaveValue('Cambiada por otra persona.')
  await expect(page.getByText('El puesto cambió desde que lo abriste.')).toHaveCount(0)
})

test('Administrador: desactiva con confirmación, ve el rechazo por posiciones en uso y reactiva sin confirmación; el Usuario deja de ver el desactivado', async ({
  page,
  browser,
}) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirPuestos(page)
  const fila = (nombre: string) => filas(page).filter({ hasText: nombre })

  try {
    // Desactivar un puesto sin posiciones: confirmación que lo nombra; Cancelar no cambia nada.
    await fila('Analista de datos').click()
    await page.getByRole('button', { name: 'Desactivar Analista de datos' }).click()
    const dialogo = page.getByRole('alertdialog', { name: '¿Desactivar el puesto "Analista de datos"?' })
    await expect(dialogo).toBeVisible()
    await dialogo.getByRole('button', { name: 'Cancelar' }).click()
    await expect(dialogo).toHaveCount(0)
    await expect(fila('Analista de datos')).toContainText('Activo')

    await page.getByRole('button', { name: 'Desactivar Analista de datos' }).click()
    await dialogo.getByRole('button', { name: 'Desactivar' }).click()
    await expect(fila('Analista de datos')).toHaveCount(0) // sale del listado por defecto (Activos)

    // Puesto con posiciones Ocupadas y Vacantes: el rechazo indica cuántas y el puesto sigue Activo.
    await fila('Jefe de talento humano').click()
    await page.getByRole('button', { name: 'Desactivar Jefe de talento humano' }).click()
    const rechazo = page.getByRole('alertdialog', { name: '¿Desactivar el puesto "Jefe de talento humano"?' })
    await rechazo.getByRole('button', { name: 'Desactivar' }).click()
    await expect(rechazo.getByText('No se puede desactivar el puesto: tiene 3 posiciones ocupadas o vacantes.')).toBeVisible()
    await rechazo.getByRole('button', { name: 'Cancelar' }).click()
    await expect(fila('Jefe de talento humano')).toContainText('Activo')

    // Puesto con solo posiciones Suprimidas: sí se puede desactivar.
    await fila('Desarrollador de software').click()
    await page.getByRole('button', { name: 'Desactivar Desarrollador de software' }).click()
    await page.getByRole('alertdialog').getByRole('button', { name: 'Desactivar' }).click()
    await expect(fila('Desarrollador de software')).toHaveCount(0)

    // El Usuario deja de verlos en el listado, pero consulta uno por su identificador.
    const contextoUsuario = await browser.newContext()
    const paginaUsuario = await contextoUsuario.newPage()
    await iniciarSesion(paginaUsuario, USUARIO.email)
    await abrirPuestos(paginaUsuario)
    await expect(filas(paginaUsuario).filter({ hasText: 'Analista de datos' })).toHaveCount(0)
    await expect(filas(paginaUsuario).filter({ hasText: 'Jefe de talento humano' })).toHaveCount(1)
    const detalleParaUsuario = await paginaUsuario.request.get(`${API}/api/puestos/1`, { headers: await cabeceras(paginaUsuario) })
    expect((await detalleParaUsuario.json()).data.estado).toBe('Inactivo')
    await contextoUsuario.close()

    // Reactivar: sin confirmación.
    await page.getByLabel('Filtrar por estado').selectOption('Inactivo')
    await fila('Analista de datos').click()
    await page.getByRole('button', { name: 'Activar Analista de datos' }).click()
    await expect(page.getByRole('alertdialog')).toHaveCount(0)
    await expect(fila('Analista de datos')).toHaveCount(0) // ya no es inactivo
    await page.getByLabel('Filtrar por estado').selectOption('Activo')
    await expect(fila('Analista de datos')).toContainText('Activo')
  } finally {
    // Deja los puestos como estaban aunque falle una comprobación intermedia.
    const headers = await cabeceras(page)
    for (const id of [1, 3]) {
      await page.request.patch(`${API}/api/puestos/${id}/estado`, { headers, data: { estado: 'Activo' } })
    }
  }
})

test('foco visible, tema oscuro, orden de tabulación y sin scroll horizontal a 320 y 1440 px en el listado, el detalle y el formulario con listas llenas', async ({
  page,
}) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirPuestos(page)

  const grosorDelFoco = (selector: ReturnType<Page['getByLabel']>) => selector.evaluate((el) => getComputedStyle(el).outlineWidth)

  await expect(filas(page).first()).toBeVisible() // el listado ya cargó
  await page.getByLabel('Buscar puesto').focus()
  expect(await grosorDelFoco(page.getByLabel('Buscar puesto'))).toBe('2px')
  // Con el teclado se llega a la primera fila (un botón desplegable) y muestra el anillo de foco.
  for (let i = 0; i < 6; i++) {
    await page.keyboard.press('Tab')
    if (await page.evaluate(() => document.activeElement?.hasAttribute('aria-expanded'))) break
  }
  const filaConFoco = await page.evaluate(() => {
    const el = document.activeElement
    return { expandida: el?.getAttribute('aria-expanded'), texto: el?.textContent ?? '', grosor: el ? getComputedStyle(el).outlineWidth : null }
  })
  expect(filaConFoco.expandida).toBe('false')
  expect(filaConFoco.texto).toMatch(/^PU-\d{3}/)
  expect(filaConFoco.grosor).toBe('2px')

  await page.getByRole('button', { name: 'Cambiar a tema oscuro' }).click()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')

  const sinScroll = () => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)

  for (const ancho of [320, 1440]) {
    await page.setViewportSize({ width: ancho, height: 900 })

    // Listado con el detalle abierto (incluida la tabla de competencias).
    await page.goto('/puestos')
    await page.getByLabel('Filtrar por estado').selectOption('Todos')
    await filas(page).first().click()
    await expect(page.getByRole('region').first()).toBeVisible()
    await expect(page.getByRole('table')).toBeVisible()
    expect(await sinScroll()).toBe(true)

    // Formulario con las tres listas en su máximo y textos largos.
    await page.goto('/puestos/nuevo')
    await expect(page.getByRole('heading', { name: 'Nuevo puesto', level: 1 })).toBeVisible()
    const nombre = page.getByLabel('Nombre', { exact: true })
    await nombre.focus()
    expect(await grosorDelFoco(nombre)).toBe('2px')

    // Orden de tabulación: Nombre → Descripción → Misión → primera función.
    await page.keyboard.press('Tab')
    expect(await page.evaluate(() => document.activeElement?.id)).toBe('descripcion')
    await page.keyboard.press('Tab')
    expect(await page.evaluate(() => document.activeElement?.id)).toBe('mision')
    await page.keyboard.press('Tab')
    expect(await page.evaluate(() => document.activeElement?.id)).toBe('funcion-1-tipo')

    await nombre.fill('n'.repeat(50))
    await page.getByLabel('Descripción', { exact: true }).fill('d'.repeat(200))
    await page.getByLabel('Misión', { exact: true }).fill('m'.repeat(200))
    for (let i = 0; i < 19; i++) await page.getByRole('button', { name: 'Agregar función' }).click()
    for (let i = 0; i < 18; i++) await page.getByRole('button', { name: 'Agregar requisito' }).click()
    for (let i = 0; i < 19; i++) await page.getByRole('button', { name: 'Agregar competencia' }).click()
    for (const n of [1, 10, 20]) await page.getByRole('textbox', { name: `Descripción de la función ${n}`, exact: true }).fill('f'.repeat(500))
    await expect(page.getByRole('button', { name: 'Agregar función' })).toBeDisabled()
    await expect(page.getByRole('button', { name: 'Agregar requisito' })).toBeDisabled()
    await expect(page.getByRole('button', { name: 'Agregar competencia' })).toBeDisabled()
    await expect(page.getByText('Máximo 20 elementos por lista.')).toHaveCount(3)
    expect(await sinScroll()).toBe(true)
  }
})
