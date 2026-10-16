import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e (CatalogoFormativoSeeder, sobre las siete competencias de CompetenciasSeeder):
//   Activas: Orientación a resultados (solo un curso archivado), Trabajo en equipo (2 cursos), Comunicación efectiva
//   (2 cursos y 1 micro-contenido), Liderazgo (1 curso con destino y 1 micro-contenido), Pensamiento analítico (sin
//   área ni recursos) y Gestión de proyectos (1 curso y 1 micro-contenido). Inactiva: Adaptabilidad (conserva 1 curso).
//   Cobertura: 4 de 6 activas = 67 %.
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
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible({ timeout: 30_000 })
}

async function abrirFormacion(page: Page) {
  await page.getByRole('link', { name: 'Formación', exact: true }).click()
}

async function cabeceras(page: Page) {
  const token = await page.evaluate(() => localStorage.getItem('hrms.token'))
  return { Authorization: `Bearer ${token}`, Accept: 'application/json' }
}

const matriz = (page: Page) => page.getByRole('table', { name: 'Matriz formativa de competencias' })
const filasDeLaMatriz = (page: Page) => matriz(page).getByRole('row')

test.describe.configure({ mode: 'serial', timeout: 120_000 })

test('Colaborador: ve las tarjetas por área, abre el detalle y no tiene controles de gestión', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  await abrirFormacion(page)

  await expect(page.getByRole('heading', { name: 'Catálogo formativo', level: 1 })).toBeVisible()

  // Con la DNF sembrada de este colaborador (feature 006) aparece «Recomendados», y luego los grupos por área
  // (alfabético) y "Sin área formativa" al final.
  await expect(page.getByRole('region', { name: 'Recomendados para cerrar mis brechas' })).toBeVisible()
  await expect(page.getByRole('heading', { level: 2 })).toHaveText([
    'Recomendados para cerrar mis brechas',
    'Desempeño y resultados',
    'Habilidades interpersonales',
    'Liderazgo y gestión',
    'Sin área formativa',
  ])

  // Las tarjetas por área quedan fuera de «Recomendados»: solo competencias activas, con el contador de cursos
  // disponibles (los archivados no cuentan).
  const recomendados = page.getByRole('region', { name: 'Recomendados para cerrar mis brechas' })
  const tarjeta = (nombre: RegExp) => page.getByRole('button', { name: nombre }).filter({ hasText: 'cursos disponibles' }).last()
  await expect(page.getByRole('button', { name: /cursos? disponibles?/ })).toHaveCount(6 + (await recomendados.getByRole('button').count()))
  await expect(page.getByText('Adaptabilidad')).toHaveCount(0)
  await expect(tarjeta(/Orientación a resultados/)).toContainText('0 cursos disponibles')
  await expect(tarjeta(/Comunicación efectiva/)).toContainText('2 cursos disponibles')
  // Sin Gestión de Personal no hay nivel registrado: los puntos van sin resaltar.
  await expect(tarjeta(/Comunicación efectiva/)).toContainText('Sin nivel registrado')

  // Sin controles de gestión ni cobertura.
  await expect(page.getByRole('button', { name: /Guardar|Asociar|Ver detalle|Gestionar áreas/ })).toHaveCount(0)
  await expect(page.getByText('Cobertura formativa')).toHaveCount(0)
  await expect(page.getByRole('button', { name: /Ver como/ })).toHaveCount(0)

  // Detalle de una competencia con curso con destino y micro-contenido sin destino.
  await page.getByRole('button', { name: /Liderazgo/ }).click()
  await expect(page).toHaveURL(/competencia=\d+/)
  await expect(page.getByRole('heading', { name: 'Liderazgo', level: 1 })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Cursos (1)' })).toBeVisible()
  await expect(page.getByRole('link', { name: 'Iniciar Liderazgo para jefaturas' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Micro-contenidos (1)' })).toBeVisible()
  await expect(page.getByText('Aptos para desarrollo de conocimientos, no de competencias conductuales.')).toBeVisible()
  const sinDestino = page.getByRole('button', { name: /Guía rápida de retroalimentación: Próximamente/ })
  await expect(sinDestino).toBeDisabled()
  await expect(sinDestino).toContainText('Próximamente')

  await page.getByRole('button', { name: 'Volver al catálogo' }).click()
  await expect(page.getByRole('heading', { name: 'Catálogo formativo', level: 1 })).toBeVisible()
})

test('Colaborador: el backend le niega la gestión aunque se salte la interfaz', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  const headers = await cabeceras(page)

  expect((await page.request.get(`${API}/api/catalogo-formativo/cobertura`, { headers })).status()).toBe(403)
  expect((await page.request.get(`${API}/api/catalogo-formativo/1/historial`, { headers })).status()).toBe(403)
  expect((await page.request.put(`${API}/api/catalogo-formativo/1`, { headers, data: {} })).status()).toBe(403)
  expect((await page.request.post(`${API}/api/catalogo-formativo/areas`, { headers, data: { nombre: 'X' } })).status()).toBe(403)

  // Una competencia inactiva no existe para el colaborador, y el listado nunca incluye inactivas.
  const lista = await page.request.get(`${API}/api/catalogo-formativo?estado=Todos&per_page=50`, { headers })
  expect((await lista.json()).meta.total).toBe(6)
})

test('Administrador: ve la cobertura y la matriz, filtra y consulta una competencia inactiva', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirFormacion(page)

  await expect(page.getByRole('heading', { name: 'Catálogo formativo de competencias', level: 1 })).toBeVisible()
  await expect(page.getByText('El diccionario de competencias es de solo lectura.')).toBeVisible()
  await expect(page.getByRole('progressbar', { name: 'Cobertura formativa' })).toHaveAttribute('aria-valuenow', '67')
  await expect(page.getByText('4 de 6 competencias activas')).toBeVisible()
  await expect(page.getByRole('button', { name: /Ver como/ })).toHaveCount(0)

  // Por defecto, solo activas: encabezado + 6 filas.
  await expect(filasDeLaMatriz(page)).toHaveCount(7)

  // "Ver sin cobertura": Orientación a resultados (solo archivados) y Pensamiento analítico (sin recursos).
  await page.getByRole('button', { name: 'Ver sin cobertura' }).click()
  await expect(page).toHaveURL(/cobertura=sin_oferta/)
  await expect(filasDeLaMatriz(page)).toHaveCount(3)
  await expect(matriz(page)).toContainText('Orientación a resultados')
  await expect(matriz(page)).toContainText('Pensamiento analítico')

  // Búsqueda sin tildes y filtros combinados, guardados en la URL.
  await page.getByRole('button', { name: 'Limpiar filtros' }).count()
  await page.goto('/formacion')
  await page.getByLabel('Buscar competencia').fill('GESTION')
  await expect(page).toHaveURL(/q=GESTION/)
  await expect(filasDeLaMatriz(page)).toHaveCount(2)
  await expect(matriz(page)).toContainText('Gestión de proyectos')
  await page.getByLabel('Buscar competencia').fill('zzzz')
  await expect(page.getByText('Ninguna competencia coincide con la búsqueda.')).toBeVisible()
  await page.getByRole('button', { name: 'Limpiar filtros' }).click()
  await expect(filasDeLaMatriz(page)).toHaveCount(7)

  // La competencia inactiva se ve solo con el filtro de estado y no permite asociar recursos nuevos.
  await page.getByLabel('Filtrar por estado de la competencia').selectOption('Inactivo')
  await expect(filasDeLaMatriz(page)).toHaveCount(2)
  await page.getByRole('button', { name: 'Ver detalle de Adaptabilidad' }).click()
  const panel = page.getByRole('dialog', { name: 'Adaptabilidad' })
  await expect(panel).toBeVisible()
  await expect(panel.getByText(/La competencia está inactiva: no se pueden asociar recursos nuevos/)).toBeVisible()
  await expect(panel.getByRole('button', { name: 'Asociar curso' })).toBeDisabled()
  await expect(panel.getByRole('button', { name: 'Asociar contenido' })).toBeDisabled()
  await expect(panel.getByText('Gestión del cambio personal')).toBeVisible() // el vínculo se conservó
  await page.keyboard.press('Escape')
  await expect(panel).toHaveCount(0)
})

test('Administrador: asocia un curso, guarda, ve el historial y lo quita con confirmación', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirFormacion(page)

  await page.getByRole('button', { name: 'Ver detalle de Pensamiento analítico' }).click()
  const panel = page.getByRole('dialog', { name: 'Pensamiento analítico' })
  await expect(panel).toBeVisible()
  await expect(panel.getByText('Estructura Empresarial · solo lectura')).toBeVisible()
  await expect(panel.getByText('Esta competencia aún no tiene cursos asociados.')).toBeVisible()
  await expect(panel.getByRole('button', { name: 'Guardar' })).toBeDisabled()

  // Asociar un curso disponible y asignar un área, y guardar de una vez.
  await panel.getByRole('button', { name: 'Asociar curso' }).click()
  await panel.getByLabel(/Análisis de datos con hojas de cálculo/).check()
  await panel.getByRole('button', { name: 'Asociar (1)' }).click()
  await expect(panel.getByText('Tienes cambios sin guardar.')).toBeVisible()
  await panel.getByLabel('Área formativa').selectOption({ label: 'Técnica y datos' })
  await panel.getByRole('button', { name: 'Guardar' }).click()
  await expect(panel.getByText('Cambios guardados.')).toBeVisible()
  await expect(panel.getByText('Asoció el curso "Análisis de datos con hojas de cálculo"')).toBeVisible()
  await expect(panel.getByText(/Cambió el área de "Sin área formativa" a "Técnica y datos"/)).toBeVisible()
  await expect(panel.getByText(/Última configuración: /)).toBeVisible()

  // La matriz y la cobertura reflejan el cambio: ahora 5 de 6 activas.
  await panel.getByRole('button', { name: 'Cerrar panel' }).click()
  await expect(page.getByRole('progressbar', { name: 'Cobertura formativa' })).toHaveAttribute('aria-valuenow', '83')
  await expect(matriz(page).getByRole('row', { name: /Pensamiento analítico/ })).toContainText('1 curso')

  // Restablecer: quitar el curso con confirmación (solo se elimina el vínculo) y el área.
  await page.getByRole('button', { name: 'Ver detalle de Pensamiento analítico' }).click()
  await panel.getByRole('button', { name: 'Quitar Análisis de datos con hojas de cálculo' }).click()
  const confirmacion = page.getByRole('alertdialog')
  await expect(confirmacion).toContainText('Solo se elimina el vínculo con la competencia')
  await confirmacion.getByRole('button', { name: 'Quitar' }).click()
  await panel.getByLabel('Área formativa').selectOption({ label: 'Sin área formativa' })
  await panel.getByRole('button', { name: 'Guardar' }).click()
  await expect(panel.getByText('Cambios guardados.')).toBeVisible()
  await panel.getByRole('button', { name: 'Cerrar panel' }).click()
  await expect(page.getByRole('progressbar', { name: 'Cobertura formativa' })).toHaveAttribute('aria-valuenow', '67')

  // El curso sigue existiendo: puede asociarse a otra competencia.
  const headers = await cabeceras(page)
  const recursos = await page.request.get(`${API}/api/catalogo-formativo/recursos-disponibles?tipo=curso`, { headers })
  expect((await recursos.json()).data.map((r: { nombre: string }) => r.nombre)).toContain('Análisis de datos con hojas de cálculo')
})

test('Administrador: cierra con cambios sin guardar solo tras confirmar y rechaza guardar en una competencia inactiva', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirFormacion(page)

  await page.getByRole('button', { name: 'Ver detalle de Pensamiento analítico' }).click()
  const panel = page.getByRole('dialog', { name: 'Pensamiento analítico' })
  await panel.getByRole('button', { name: 'Asociar curso' }).click()
  await panel.getByLabel(/Análisis de datos con hojas de cálculo/).check()
  await panel.getByRole('button', { name: 'Asociar (1)' }).click()

  await panel.getByRole('button', { name: 'Cerrar panel' }).click()
  const aviso = page.getByRole('alertdialog')
  await expect(aviso).toContainText('¿Descartar los cambios sin guardar?')
  await aviso.getByRole('button', { name: 'Seguir editando' }).click()
  await expect(panel).toBeVisible()
  await panel.getByRole('button', { name: 'Cerrar panel' }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Descartar cambios' }).click()
  await expect(panel).toHaveCount(0)

  // El backend aplica la regla aunque se salte la interfaz: recursos nuevos en una competencia inactiva → 422.
  const headers = await cabeceras(page)
  const todas = await page.request.get(`${API}/api/catalogo-formativo?estado=Inactivo`, { headers })
  const inactiva = (await todas.json()).data[0]
  const rechazo = await page.request.put(`${API}/api/catalogo-formativo/${inactiva.competencia_id}`, {
    headers,
    data: { area_formativa_id: null, curso_ids: [4], microcontenido_ids: [], version: 1 },
  })
  expect(rechazo.status()).toBe(422)
})

test('Administrador: crea y renombra áreas formativas, sin poder repetir el nombre ni eliminarlas', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirFormacion(page)

  await page.getByRole('button', { name: 'Gestionar áreas' }).click()
  const dialogo = page.getByRole('dialog', { name: 'Gestionar áreas formativas' })
  await expect(dialogo.getByRole('button', { name: /Eliminar/ })).toHaveCount(0)

  await dialogo.getByLabel('Nueva área formativa').fill('Área E2E')
  await dialogo.getByRole('button', { name: 'Crear área' }).click()
  await expect(dialogo.getByText('Área creada.')).toBeVisible()

  // Nombre repetido (sin distinguir mayúsculas ni espacios).
  await dialogo.getByLabel('Nueva área formativa').fill('  ÁREA e2e ')
  await dialogo.getByRole('button', { name: 'Crear área' }).click()
  await expect(dialogo.getByText('Ya existe un área formativa con ese nombre.')).toBeVisible()

  await dialogo.getByRole('button', { name: 'Renombrar Área E2E' }).click()
  await dialogo.getByLabel('Nombre del área').fill('Área E2E renombrada')
  await dialogo.getByRole('button', { name: 'Guardar nombre' }).click()
  await expect(dialogo.getByText('Nombre actualizado.')).toBeVisible()
  await expect(dialogo.getByText('Área E2E renombrada')).toBeVisible()

  await dialogo.getByRole('button', { name: 'Cerrar' }).click()
  await expect(page.getByLabel('Filtrar por área formativa')).toContainText('Área E2E renombrada')

  const headers = await cabeceras(page)
  const area = (await (await page.request.get(`${API}/api/catalogo-formativo/areas`, { headers })).json()).data.find(
    (a: { nombre: string }) => a.nombre === 'Área E2E renombrada',
  )
  expect([404, 405]).toContain((await page.request.delete(`${API}/api/catalogo-formativo/areas/${area.id}`, { headers })).status())
})

test('foco visible, tema oscuro y sin scroll horizontal a 320 y 1440 px en la matriz, el panel y las tarjetas', async ({ page }) => {
  const sinScroll = () => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)

  await iniciarSesion(page, ADMIN.email)
  await abrirFormacion(page)
  await page.getByLabel('Buscar competencia').focus()
  expect(await page.getByLabel('Buscar competencia').evaluate((el) => getComputedStyle(el).outlineWidth)).toBe('2px')
  await page.getByRole('button', { name: 'Cambiar a tema oscuro' }).click()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')

  for (const ancho of [320, 1440]) {
    await page.setViewportSize({ width: ancho, height: 800 })
    await page.goto('/formacion')
    await expect(matriz(page)).toBeVisible()
    expect(await sinScroll()).toBe(true)

    await page.getByRole('button', { name: 'Ver detalle de Comunicación efectiva' }).click()
    const panel = page.getByRole('dialog', { name: 'Comunicación efectiva' })
    await expect(panel.getByRole('button', { name: 'Guardar' })).toBeVisible()
    expect(await sinScroll()).toBe(true)
    const caja = await panel.boundingBox()
    expect(caja?.width ?? 0).toBeLessThanOrEqual(ancho)
    await page.keyboard.press('Escape')
    await expect(panel).toHaveCount(0)
  }

  // Vista del colaborador.
  await page.getByRole('button', { name: 'Menú de usuario: Administrador de prueba' }).click()
  await page.getByRole('menuitem', { name: 'Cerrar sesión' }).click()
  await iniciarSesion(page, USUARIO.email)
  for (const ancho of [320, 1440]) {
    await page.setViewportSize({ width: ancho, height: 800 })
    await page.goto('/formacion')
    await expect(page.getByRole('button', { name: /Comunicación efectiva/ })).toBeVisible()
    expect(await sinScroll()).toBe(true)
  }
})
