import { expect, test, type Page } from '@playwright/test'

// admin@hrms.test es Administrador (el Gestor de Talento en el repositorio de prueba) y usuario@hrms.test es un Usuario.
const PASSWORD = 'password-de-prueba'

async function iniciarSesion(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('navigation', { name: 'Navegación principal' })).toBeVisible()
}

test('Gestor: cambia el plazo de anulación y ve el último cambio', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await page.goto('/compensaciones/categorias')

  // La pestaña «Parámetros» es visible para el Gestor.
  await page.getByRole('tab', { name: 'Parámetros' }).click()
  await expect(page).toHaveURL(/\/compensaciones\/parametros/)
  await expect(page.getByRole('heading', { name: 'Parámetros del módulo', level: 1 })).toBeVisible()

  const tarjeta = page.getByRole('region', { name: 'Plazo para anular un reconocimiento' })
  await expect(tarjeta).toBeVisible({ timeout: 20_000 })

  // El valor nuevo siempre es distinto del actual para que la prueba sea repetible sobre una base ya usada.
  const campo = tarjeta.getByLabel('Días')
  const actual = Number(await campo.inputValue())
  const nuevo = actual >= 30 ? 7 : actual + 1

  // Un valor fuera de rango se rechaza junto al campo y no se guarda.
  await campo.fill('31')
  await page.getByRole('button', { name: 'Guardar' }).click()
  await expect(tarjeta.getByText('Debe estar entre 1 y 30.')).toBeVisible()

  await campo.fill(String(nuevo))
  await page.getByRole('button', { name: 'Guardar' }).click()
  await expect(page.getByText('Los parámetros se guardaron correctamente.')).toBeVisible()

  await expect(tarjeta.getByText(new RegExp(`Último cambio: .*\\(de ${actual} a ${nuevo}\\)|Último cambio: .*\\(de valor por defecto a ${nuevo}\\)`))).toBeVisible()
  await expect(tarjeta.getByText(`Actual: ${nuevo}`)).toBeVisible()
})

test('Usuario: no ve la pestaña «Parámetros»', async ({ page }) => {
  await iniciarSesion(page, 'usuario@hrms.test')
  await page.goto('/compensaciones/categorias')

  await expect(page.getByRole('heading', { name: 'Categorías de reconocimiento', level: 1 })).toBeVisible()
  await expect(page.getByRole('tab', { name: 'Parámetros' })).toHaveCount(0)
})

for (const ancho of [320, 1440]) {
  test(`sin scroll horizontal a ${ancho}px en la pantalla de parámetros`, async ({ page }) => {
    await page.setViewportSize({ width: ancho, height: 900 })
    await iniciarSesion(page, 'admin@hrms.test')
    await page.goto('/compensaciones/parametros')
    await expect(page.getByRole('heading', { name: 'Sensibilidad del radar de clima' })).toBeVisible({ timeout: 20_000 })

    const desborda = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)
    expect(desborda).toBe(false)
  })
}
