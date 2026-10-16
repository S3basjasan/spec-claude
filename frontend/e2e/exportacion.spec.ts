import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e (PersonalSeeder): EMP-001 Ana con datos completos; usuario@hrms.test es su Colaborador
// con permiso de exportación; colaborador2@hrms.test (Luis) no tiene permiso de exportación; sinpermiso@hrms.test no tiene
// ninguno.
const PASSWORD = 'password-de-prueba'

async function iniciarSesion(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible()
}

test('Administrador: exporta la ficha de un colaborador y recibe un PDF con el nombre seguro', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await page.goto('/colaboradores')
  await page.getByLabel('Buscar colaborador').fill('Núñez Paredes')
  await page.getByRole('link', { name: /^Ver ficha de Ana/ }).click()

  const descarga = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Exportar ficha' }).click()
  const archivo = await descarga

  expect(archivo.suggestedFilename()).toMatch(/^Ficha_EMP-\d+\.pdf$/)
  await expect(page.getByText(/no se agregó a Documentos/i)).toBeVisible()
})

test('Colaborador con permiso: exporta su ficha sin depender de la consulta por pantalla', async ({ page }) => {
  await iniciarSesion(page, 'usuario@hrms.test')
  await page.getByRole('link', { name: 'Exportar mi ficha' }).click()
  await expect(page.getByRole('heading', { name: 'Exportar mi ficha', level: 1 })).toBeVisible()

  const descarga = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Exportar ficha' }).click()
  expect((await descarga).suggestedFilename()).toMatch(/^Ficha_EMP-\d+\.pdf$/)
})

test('Colaborador sin permiso de exportación: el servidor deniega y la pantalla lo explica', async ({ page }) => {
  await iniciarSesion(page, 'colaborador2@hrms.test')
  await page.goto('/exportar-mi-ficha')

  await page.getByRole('button', { name: 'Exportar ficha' }).click()
  await expect(page.getByText('No tienes permiso para exportar tu ficha')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Exportar ficha' })).toHaveCount(0)
})
