import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e (PersonalSeeder): tenant 1 con las categorías Contratos y Certificados (Activas)
// y «Evaluaciones antiguas» (Inactiva). usuario@hrms.test es Colaborador.
const PASSWORD = 'password-de-prueba'

// Los nombres son únicos por ejecución para que la prueba sea repetible sobre una base ya usada.
const SUFIJO = String(Date.now()).slice(-6)
const NOMBRE = `Seguridad social ${SUFIJO}`
const NOMBRE_CORREGIDO = `Seguridad social corregida ${SUFIJO}`

async function iniciarSesion(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible()
}

test.describe.configure({ mode: 'serial' })

test('Administrador: crea, rechaza un duplicado, edita, desactiva y reactiva una categoría', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await page.getByRole('link', { name: 'Categorías documentales', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Categorías documentales', level: 1 })).toBeVisible()

  // Solo las activas, ordenadas por nombre; sin Eliminar.
  await expect(page.getByRole('row', { name: /Contratos/ })).toBeVisible({ timeout: 20_000 })
  await expect(page.getByRole('row', { name: /Certificados/ })).toBeVisible()
  await expect(page.getByText('Evaluaciones antiguas')).toHaveCount(0)
  await expect(page.getByRole('button', { name: /eliminar|borrar/i })).toHaveCount(0)

  // Nueva categoría.
  await page.getByRole('button', { name: 'Nueva categoría' }).first().click()
  await page.getByRole('textbox', { name: 'Nombre' }).fill(NOMBRE)
  await page.getByRole('button', { name: 'Guardar' }).click()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(page.getByText('Categoría creada.')).toBeVisible()
  await expect(page.getByRole('row', { name: new RegExp(NOMBRE) })).toBeVisible()

  // Duplicado con otras mayúsculas y espacios: el error aparece junto al campo y el diálogo se conserva.
  await page.getByRole('button', { name: 'Nueva categoría' }).first().click()
  await page.getByRole('textbox', { name: 'Nombre' }).fill(`  ${NOMBRE.toUpperCase()}  `)
  await page.getByRole('button', { name: 'Guardar' }).click()
  await expect(page.getByText('Ya existe una categoría con ese nombre.')).toBeVisible()
  await expect(page.getByRole('textbox', { name: 'Nombre' })).toHaveValue(`  ${NOMBRE.toUpperCase()}  `)
  await page.getByRole('button', { name: 'Cancelar' }).click()

  // Editar el nombre.
  await page.getByRole('button', { name: `Editar ${NOMBRE}` }).click()
  await page.getByRole('textbox', { name: 'Nombre' }).fill(NOMBRE_CORREGIDO)
  await page.getByRole('button', { name: 'Guardar' }).click()
  await expect(page.getByText('Categoría actualizada.')).toBeVisible()
  await expect(page.getByRole('row', { name: new RegExp(NOMBRE_CORREGIDO) })).toBeVisible()

  // Desactivar con confirmación: sale del filtro Activas.
  await page.getByRole('button', { name: `Desactivar ${NOMBRE_CORREGIDO}` }).click()
  await expect(page.getByRole('alertdialog')).toContainText('Sus documentos se conservarán')
  await page.getByRole('button', { name: 'Desactivar categoría' }).click()
  await expect(page.getByText('Categoría desactivada. Sus documentos se conservan.')).toBeVisible()
  await expect(page.getByRole('row', { name: new RegExp(NOMBRE_CORREGIDO) })).toHaveCount(0)

  // Aparece entre las inactivas y se reactiva.
  await page.getByLabel('Estado').selectOption('Inactiva')
  await expect(page).toHaveURL(/estado=Inactiva/)
  await expect(page.getByRole('row', { name: new RegExp(NOMBRE_CORREGIDO) })).toBeVisible()
  await page.getByRole('button', { name: `Reactivar ${NOMBRE_CORREGIDO}` }).click()
  await page.getByRole('button', { name: 'Reactivar categoría' }).click()
  await expect(page.getByText(/Categoría reactivada/)).toBeVisible()

  await page.getByLabel('Estado').selectOption('Activa')
  await expect(page.getByRole('row', { name: new RegExp(NOMBRE_CORREGIDO) })).toBeVisible()
})

test('Colaborador: no ve la entrada de navegación y la ruta le niega el acceso', async ({ page }) => {
  await iniciarSesion(page, 'usuario@hrms.test')

  await expect(page.getByRole('link', { name: 'Categorías documentales', exact: true })).toHaveCount(0)
  await page.goto('/categorias-documentales')
  await expect(page.getByText('No tienes permiso para ver esta información')).toBeVisible()
})

for (const ancho of [320, 1440]) {
  test(`sin scroll horizontal a ${ancho}px en el listado y sus diálogos`, async ({ page }) => {
    await page.setViewportSize({ width: ancho, height: 900 })
    await iniciarSesion(page, 'admin@hrms.test')
    await page.goto('/categorias-documentales')
    await expect(page.getByRole('row', { name: /Contratos/ })).toBeVisible({ timeout: 20_000 })

    const desborda = () => page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)
    expect(await desborda(), `listado a ${ancho}px`).toBe(false)

    await page.getByRole('button', { name: 'Editar Contratos' }).click()
    await expect(page.getByRole('dialog')).toBeVisible()
    expect(await desborda(), `diálogo a ${ancho}px`).toBe(false)
    await page.keyboard.press('Escape')
    await expect(page.getByRole('button', { name: 'Editar Contratos' })).toBeFocused()

    await page.getByRole('button', { name: 'Desactivar Contratos' }).click()
    await expect(page.getByRole('alertdialog')).toBeVisible()
    expect(await desborda(), `confirmación a ${ancho}px`).toBe(false)
    await page.keyboard.press('Escape')
  })
}
