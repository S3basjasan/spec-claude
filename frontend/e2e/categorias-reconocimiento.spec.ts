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
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible()
}

async function buscar(page: Page, texto: string) {
  await page.getByRole('searchbox').fill(texto)
  await expect(page).toHaveURL(new RegExp(`q=${encodeURIComponent(texto).replace(/%20/g, '(\\+|%20)')}`))
}

test.describe.configure({ mode: 'serial' })

test('Administrador: crea, lista, edita, desactiva y activa una categoría', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await page.getByRole('link', { name: 'Categorías de reconocimiento', exact: true }).click()
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
  await page.getByLabel('Indicador 1').fill('Comparte información útil con su equipo')
  await page.getByRole('button', { name: 'Agregar indicador' }).click()
  await page.getByLabel('Indicador 2').fill('Reconoce los aportes de otras personas')
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
  await page.getByLabel('Indicador 1').fill('Indicador de prueba suficiente')
  await page.getByRole('button', { name: 'Crear categoría' }).click()
  await expect(page.getByText('Ya existe una categoría con ese nombre.')).toBeVisible()
  await expect(page.getByLabel('Nombre', { exact: true })).toHaveValue(`  ${NOMBRE.toUpperCase()}  `.trim())
  await page.getByRole('button', { name: 'Cancelar' }).click()
  await page.getByRole('button', { name: 'Descartar cambios' }).click()

  // Se encuentra por búsqueda (la página 1 solo trae las primeras 15).
  await buscar(page, NOMBRE)
  await expect(page.getByRole('row', { name: new RegExp(NOMBRE) })).toBeVisible()

  // Editar la puntuación y el nombre.
  await page.getByRole('button', { name: `Editar ${NOMBRE}` }).click()
  await expect(page.getByLabel('Indicador 2')).toHaveValue('Reconoce los aportes de otras personas')
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

test('Usuario: consulta el catálogo activo sin controles de gestión', async ({ page }) => {
  await iniciarSesion(page, 'usuario@hrms.test')
  await page.getByRole('link', { name: 'Categorías de reconocimiento', exact: true }).click()

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
