import { expect, test, type Page } from '@playwright/test'

// Datos sembrados (CategoriasReconocimientoSeeder): 20 categorías, 17 activas y 3 inactivas, con 1 a 5 indicadores.
// admin@hrms.test es Administrador y usuario@hrms.test es un Usuario sin rol de administración.
const PASSWORD = 'password-de-prueba'

// Los nombres son únicos por ejecución para que la prueba sea repetible sobre una base ya usada.
const SUFIJO = String(Date.now()).slice(-6)
const NOMBRE = `Reconocimiento de equipo ${SUFIJO}`
const NOMBRE_CORREGIDO = `Reconocimiento de equipo corregido ${SUFIJO}`

async function iniciarSesion(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  // La página de aterrizaje cambia con el menú; basta con que ya esté dentro de la aplicación.
  await expect(page.getByRole('navigation', { name: 'Navegación principal' })).toBeVisible()
}

async function irACategorias(page: Page) {
  await page.goto('/compensaciones/categorias')
}

async function buscar(page: Page, texto: string) {
  await page.getByRole('searchbox').fill(texto)
  await expect(page).toHaveURL(new RegExp(`q=${encodeURIComponent(texto).replace(/%20/g, '(\\+|%20)')}`))
}

test.describe.configure({ mode: 'serial' })

test('Administrador: crea, lista, edita, desactiva y activa una categoría', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await irACategorias(page)
  await expect(page.getByRole('heading', { name: 'Categorías de reconocimiento', level: 1 })).toBeVisible()

  // Solo las activas, ordenadas por código, de 15 en 15; sin Eliminar.
  await expect(page.getByRole('row', { name: /CAT-001/ })).toBeVisible({ timeout: 20_000 })
  await expect(page.getByRole('row')).toHaveCount(16) // encabezado + 15
  await expect(page.getByRole('button', { name: /eliminar|borrar/i })).toHaveCount(0)

  // Nueva categoría con dos indicadores.
  await page.getByRole('button', { name: 'Nueva categoría' }).first().click()
  await page.getByLabel('Nombre', { exact: true }).fill(NOMBRE)
  await page.getByLabel('Descripción').fill('Reconoce el trabajo conjunto que facilita el logro de objetivos.')
  await expect(page.getByLabel('Competencia asociada').locator('option')).not.toHaveCount(1)
  await page.getByLabel('Competencia asociada').selectOption({ index: 1 })
  await page.getByLabel('Puntuación base').fill('120')
  await page.getByRole('radio', { name: 'Trofeo' }).check({ force: true })
  await page.getByLabel('Indicador 1', { exact: true }).fill('Comparte información útil con su equipo')
  await page.getByRole('button', { name: 'Agregar indicador' }).click()
  await page.getByLabel('Indicador 2', { exact: true }).fill('Reconoce los aportes de otras personas')
  await page.getByRole('checkbox', { name: 'Todas las unidades' }).check()
  await page.getByRole('button', { name: 'Crear categoría' }).click()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(page.getByText(new RegExp(`Se creó la categoría «${NOMBRE}» \\(CAT-\\d{3,}\\)`))).toBeVisible()

  // Un nombre repetido con otras mayúsculas y espacios se rechaza junto al campo y conserva lo escrito.
  await page.getByRole('button', { name: 'Nueva categoría' }).first().click()
  await page.getByLabel('Nombre', { exact: true }).fill(`  ${NOMBRE.toUpperCase()}  `)
  await page.getByLabel('Descripción').fill('Descripción suficiente para pasar la validación.')
  await page.getByLabel('Competencia asociada').selectOption({ index: 1 })
  await page.getByLabel('Puntuación base').fill('50')
  await page.getByRole('radio', { name: 'Estrella' }).check({ force: true })
  await page.getByLabel('Indicador 1', { exact: true }).fill('Indicador de prueba suficiente')
  await page.getByRole('checkbox', { name: 'Todas las unidades' }).check()
  await page.getByRole('button', { name: 'Crear categoría' }).click()
  await expect(page.getByText('Ya existe una categoría con ese nombre.')).toBeVisible()
  await expect(page.getByLabel('Nombre', { exact: true })).toHaveValue(`  ${NOMBRE.toUpperCase()}  `)
  await page.getByRole('button', { name: 'Cancelar' }).click()
  await page.getByRole('button', { name: 'Descartar cambios' }).click()

  // Se encuentra por búsqueda (la página 1 solo trae las primeras 15).
  await buscar(page, NOMBRE)
  await expect(page.getByRole('row', { name: new RegExp(NOMBRE) })).toBeVisible()

  // Editar la puntuación y el nombre.
  await page.getByRole('button', { name: `Editar ${NOMBRE}` }).click()
  await expect(page.getByLabel('Indicador 2', { exact: true })).toHaveValue('Reconoce los aportes de otras personas')
  await page.getByLabel('Nombre', { exact: true }).fill(NOMBRE_CORREGIDO)
  await page.getByLabel('Puntuación base').fill('200')
  await page.getByRole('button', { name: 'Guardar cambios' }).click()
  await expect(page.getByText(`Se guardaron los cambios de «${NOMBRE_CORREGIDO}».`)).toBeVisible()
  await buscar(page, NOMBRE_CORREGIDO)
  await expect(page.getByRole('row', { name: new RegExp(`${NOMBRE_CORREGIDO}.*200`) })).toBeVisible()

  // Ver: datos, indicadores en viñetas y auditoría de solo lectura.
  await page.getByRole('button', { name: `Ver ${NOMBRE_CORREGIDO}` }).click()
  const detalle = page.getByRole('dialog', { name: NOMBRE_CORREGIDO })
  await expect(detalle.getByText('Reconoce los aportes de otras personas')).toBeVisible()
  await expect(detalle.getByText('Auditoría')).toBeVisible()
  await detalle.getByRole('button', { name: 'Cerrar', exact: true }).last().click()

  // Desactivar con confirmación: sale del filtro Activos y aparece entre los inactivos.
  await page.getByRole('button', { name: `Desactivar ${NOMBRE_CORREGIDO}` }).click()
  await expect(page.getByRole('alertdialog')).toContainText('no podrá usarse en nuevos hitos ni desafíos')
  await page.getByRole('alertdialog').getByRole('button', { name: 'Desactivar' }).click()
  await expect(page.getByText(`Se desactivó la categoría «${NOMBRE_CORREGIDO}».`)).toBeVisible()
  await expect(page.getByRole('row', { name: new RegExp(NOMBRE_CORREGIDO) })).toHaveCount(0)

  await page.getByLabel('Estado').selectOption('Inactivo')
  await expect(page).toHaveURL(/estado=Inactivo/)
  await expect(page.getByRole('row', { name: new RegExp(NOMBRE_CORREGIDO) })).toBeVisible()

  // Activar no pide confirmación.
  await page.getByRole('button', { name: `Activar ${NOMBRE_CORREGIDO}` }).click()
  await expect(page.getByText(`Se activó la categoría «${NOMBRE_CORREGIDO}».`)).toBeVisible()
  await expect(page.getByRole('alertdialog')).toHaveCount(0)
})

// 01b.2: sin cambios en los parámetros, los puntos por colaborador valen lo mismo que la categoría activa más alta (200 en
// los datos sembrados); ese es el máximo permitido de la puntuación base.
test('Administrador: la puntuación base respeta el máximo permitido y no deja activar una categoría por encima', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await irACategorias(page)
  await expect(page.getByRole('row', { name: /CAT-001/ })).toBeVisible({ timeout: 20_000 })

  // El formulario muestra el máximo junto al campo y rechaza un valor mayor sin guardar ni perder lo escrito.
  await page.getByRole('button', { name: 'Nueva categoría' }).first().click()
  await expect(page.getByText('Máximo: 200 puntos.')).toBeVisible()
  await page.getByLabel('Nombre', { exact: true }).fill(`Tope de puntuación ${SUFIJO}`)
  await page.getByLabel('Descripción').fill('Comprueba que la puntuación no supera el máximo permitido.')
  await expect(page.getByLabel('Competencia asociada').locator('option')).not.toHaveCount(1)
  await page.getByLabel('Competencia asociada').selectOption({ index: 1 })
  await page.getByLabel('Puntuación base').fill('201')
  await page.getByRole('radio', { name: 'Trofeo' }).check({ force: true })
  await page.getByLabel('Indicador 1', { exact: true }).fill('Indicador de prueba suficiente')
  await page.getByRole('checkbox', { name: 'Todas las unidades' }).check()
  await page.getByRole('button', { name: 'Crear categoría' }).click()
  await expect(page.getByText('La puntuación base debe estar entre 1 y 200.')).toBeVisible()
  await expect(page.getByLabel('Puntuación base')).toHaveValue('201')
  await expect(page.getByRole('dialog')).toBeVisible()
  await page.getByRole('button', { name: 'Cancelar' }).click()
  await page.getByRole('button', { name: 'Descartar cambios' }).click()

  // Activar una inactiva con 300 puntos (sembrada) se rechaza y sigue inactiva.
  await page.getByLabel('Estado').selectOption('Inactivo')
  await expect(page).toHaveURL(/estado=Inactivo/)
  await page.getByRole('button', { name: 'Activar Reconocimiento a la trayectoria' }).click()
  await expect(page.getByRole('alert')).toContainText('Baja primero la puntuación a 200 o menos.')
  await expect(page.getByRole('button', { name: 'Activar Reconocimiento a la trayectoria' })).toBeVisible()
})

// 01b.4: los datos sembrados ubican a usuario@hrms.test (Ana) en «Gerencia de Talento Humano».
const UNIDAD_DE_ANA = 'Gerencia de Talento Humano'
const OTRA_UNIDAD = 'Gerencia de Tecnología'
const NOMBRE_POR_UNIDAD = `Reconocimiento por unidad ${SUFIJO}`

async function cerrarSesion(page: Page) {
  await page.context().clearCookies()
  await page.goto('/login')
  await page.evaluate(() => {
    window.localStorage.clear()
    window.sessionStorage.clear()
  })
}

test('Gestor asigna una categoría a una unidad: el colaborador de otra unidad no la ve y el de esa unidad sí', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await irACategorias(page)
  await expect(page.getByRole('row', { name: /CAT-001/ })).toBeVisible({ timeout: 20_000 })

  // Sin unidades no se guarda: el error aparece junto al selector y lo escrito se conserva.
  await page.getByRole('button', { name: 'Nueva categoría' }).first().click()
  await page.getByLabel('Nombre', { exact: true }).fill(NOMBRE_POR_UNIDAD)
  await page.getByLabel('Descripción').fill('Se asigna solo a una unidad para comprobar la visibilidad.')
  await expect(page.getByLabel('Competencia asociada').locator('option')).not.toHaveCount(1)
  await page.getByLabel('Competencia asociada').selectOption({ index: 1 })
  await page.getByLabel('Puntuación base').fill('50')
  await page.getByRole('radio', { name: 'Trofeo' }).check({ force: true })
  await page.getByLabel('Indicador 1', { exact: true }).fill('Indicador de prueba suficiente')
  await page.getByRole('button', { name: 'Crear categoría' }).click()
  await expect(page.getByText('Elige al menos una unidad.')).toBeVisible()
  await expect(page.getByLabel('Nombre', { exact: true })).toHaveValue(NOMBRE_POR_UNIDAD)

  // Se asigna solo a otra unidad.
  await page.getByRole('checkbox', { name: OTRA_UNIDAD }).check()
  await expect(page.getByText('La verán los colaboradores de 1 unidad.')).toBeVisible()
  await page.getByRole('button', { name: 'Crear categoría' }).click()
  await expect(page.getByText(new RegExp(`Se creó la categoría «${NOMBRE_POR_UNIDAD}»`))).toBeVisible()
  await buscar(page, NOMBRE_POR_UNIDAD)
  await expect(page.getByRole('row', { name: new RegExp(`${NOMBRE_POR_UNIDAD}.*${OTRA_UNIDAD}`) })).toBeVisible()

  // Ana, de otra unidad, no la ve.
  await cerrarSesion(page)
  await iniciarSesion(page, 'usuario@hrms.test')
  await page.goto(`/compensaciones/categorias?q=${encodeURIComponent(NOMBRE_POR_UNIDAD)}`)
  await expect(page.getByText('Sin resultados')).toBeVisible({ timeout: 20_000 })
  await expect(page.getByRole('row', { name: new RegExp(NOMBRE_POR_UNIDAD) })).toHaveCount(0)

  // El Gestor agrega la unidad de Ana.
  await cerrarSesion(page)
  await iniciarSesion(page, 'admin@hrms.test')
  await page.goto(`/compensaciones/categorias?q=${encodeURIComponent(NOMBRE_POR_UNIDAD)}`)
  await page.getByRole('button', { name: `Editar ${NOMBRE_POR_UNIDAD}` }).click()
  await expect(page.getByRole('checkbox', { name: OTRA_UNIDAD })).toBeChecked()
  await page.getByRole('checkbox', { name: UNIDAD_DE_ANA }).check()
  await page.getByRole('button', { name: 'Guardar cambios' }).click()
  await expect(page.getByText(`Se guardaron los cambios de «${NOMBRE_POR_UNIDAD}».`)).toBeVisible()

  // Ahora Ana sí la ve, sin columna de unidades.
  await cerrarSesion(page)
  await iniciarSesion(page, 'usuario@hrms.test')
  await page.goto(`/compensaciones/categorias?q=${encodeURIComponent(NOMBRE_POR_UNIDAD)}`)
  await expect(page.getByRole('row', { name: new RegExp(NOMBRE_POR_UNIDAD) })).toBeVisible({ timeout: 20_000 })
  await expect(page.getByRole('columnheader', { name: 'Unidades' })).toHaveCount(0)
})

test('Usuario: consulta el catálogo activo sin controles de gestión', async ({ page }) => {
  await iniciarSesion(page, 'usuario@hrms.test')
  await irACategorias(page)

  await expect(page.getByRole('row', { name: /CAT-001/ })).toBeVisible({ timeout: 20_000 })
  await expect(page.getByRole('button', { name: 'Nueva categoría' })).toHaveCount(0)
  await expect(page.getByRole('button', { name: /^(Editar|Activar|Desactivar) / })).toHaveCount(0)
  await expect(page.getByLabel('Estado')).toHaveCount(0)

  // Aunque la URL pida otro estado, solo ve las activas.
  await page.goto('/compensaciones/categorias?estado=Todos')
  await expect(page.getByRole('row', { name: /CAT-001/ })).toBeVisible({ timeout: 20_000 })
  await expect(page.getByRole('row', { name: /Inactivo/ })).toHaveCount(0)

  await page.getByRole('button', { name: 'Ver Trabajo en equipo' }).click()
  const detalle = page.getByRole('dialog', { name: 'Trabajo en equipo' })
  await expect(detalle.getByText('Indicadores cualitativos')).toBeVisible()
  await expect(detalle.getByText('Auditoría')).toHaveCount(0)
})

for (const ancho of [320, 1440]) {
  test(`sin scroll horizontal a ${ancho}px en el listado y sus diálogos`, async ({ page }) => {
    await page.setViewportSize({ width: ancho, height: 900 })
    await iniciarSesion(page, 'admin@hrms.test')
    await page.goto('/compensaciones/categorias')
    await expect(page.getByText('CAT-001').first()).toBeVisible({ timeout: 20_000 })

    const desborda = () => page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)
    expect(await desborda()).toBe(false)

    await page.getByRole('button', { name: 'Nueva categoría' }).first().click()
    await expect(page.getByRole('dialog', { name: 'Nueva categoría' })).toBeVisible()
    expect(await desborda()).toBe(false)
  })
}
