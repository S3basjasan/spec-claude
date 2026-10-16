import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e (PersonalSeeder):
//   Tenant 1 «Altavista S.A.»: EMP-001 Ana Núñez Paredes (completa), EMP-002 Luis Núñez Andrade, EMP-003 María Pérez Soto,
//   EMP-004 José Ángel Mora, EMP-005 Carla Vera Lema. Tenant 2: Rosa Chávez Díaz y Pablo Rivas Ortiz (no deben verse).
//   usuario@hrms.test está asociado a EMP-001 con permiso de consulta; sinficha@hrms.test no tiene ficha.
// Las pruebas son seriales: la primera crea una ficha y la edita.
const PASSWORD = 'password-de-prueba'

// El servidor de pruebas puede conservar fichas de ejecuciones previas: la identificación nueva es única por ejecución.
const IDENTIFICACION_NUEVA = String(Date.now()).slice(-10)

async function iniciarSesion(page: Page, email: string, destino: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: destino })).toBeVisible()
}

test.describe.configure({ mode: 'serial' })

test('Administrador: lista solo su tenant, busca sin tildes, crea, rechaza un duplicado, abre y corrige una ficha', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test', 'Inicio')
  await page.getByRole('link', { name: 'Colaboradores', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Colaboradores', level: 1 })).toBeVisible()

  // Las fichas del tenant 1 (al menos las cinco sembradas), ordenadas por apellidos; ninguna del tenant 2.
  const filas = page.getByRole('row').filter({ hasText: 'EMP-' })
  await expect(filas.first()).toBeVisible()
  expect(await filas.count()).toBeGreaterThanOrEqual(5)
  const total = await filas.count()
  await expect(filas.first()).toContainText('José Ángel Mora')
  await expect(page.getByText('Rosa Chávez Díaz')).toHaveCount(0)

  // Búsqueda sin distinguir tildes ni mayúsculas, conservada en la URL.
  await page.getByLabel('Buscar colaborador').fill('NUNEZ')
  await expect(page).toHaveURL(/q=NUNEZ/)
  await expect(filas).toHaveCount(2)
  await page.getByLabel('Buscar colaborador').fill('zzzz')
  await expect(page.getByText('Ningún colaborador coincide con la búsqueda')).toBeVisible()
  await page.getByRole('button', { name: 'Limpiar filtros' }).click()
  await expect(filas).toHaveCount(total)

  // Nueva ficha mínima.
  await page.getByRole('link', { name: 'Nueva ficha' }).click()
  await expect(page.getByText('Se asigna al guardar')).toBeVisible()
  await page.getByLabel('Número de identificación').fill(IDENTIFICACION_NUEVA)
  await page.getByLabel('Nombres').fill('Elena')
  await page.getByLabel('Apellidos').fill('Zambrano Ruiz')
  await page.getByRole('button', { name: 'Guardar' }).click()

  await expect(page.getByRole('heading', { name: 'Elena Zambrano Ruiz', level: 1 })).toBeVisible()
  await expect(page.getByText('Ficha creada.')).toBeVisible()
  await expect(page.getByText(/^EMP-\d{3,}$/)).toBeVisible()
  await expect(page.getByText('Sin vínculo vigente').first()).toBeVisible()
  await expect(page.getByText('No registrado').first()).toBeVisible()
  await expect(page.getByText('Todavía no hay hechos laborales registrados para esta persona.')).toBeVisible()
  await expect(page.getByRole('button', { name: /eliminar|desactivar/i })).toHaveCount(0)

  // Duplicado: misma identificación con espacios exteriores.
  await page.goto('/colaboradores/nuevo')
  await page.getByLabel('Número de identificación').fill(` ${IDENTIFICACION_NUEVA} `)
  await page.getByLabel('Nombres').fill('Otra')
  await page.getByLabel('Apellidos').fill('Persona')
  await page.getByRole('button', { name: 'Guardar' }).click()
  await expect(page.getByRole('link', { name: 'Abrir ficha existente' })).toBeVisible()
  await expect(page.getByLabel('Nombres')).toHaveValue('Otra')

  // Corrección: edita el nombre y conserva el código.
  await page.getByRole('link', { name: 'Abrir ficha existente' }).click()
  const codigo = (await page.getByText(/^EMP-\d{3,}$/).textContent()) ?? ''
  await page.getByRole('link', { name: 'Editar' }).click()
  await expect(page.getByLabel('Nombres')).toHaveValue('Elena')
  await page.getByLabel('Nombres').fill('Elena María')
  await page.getByLabel('Nacionalidad').fill('Ecuatoriana')
  await page.getByRole('button', { name: 'Guardar' }).click()

  await expect(page.getByRole('heading', { name: 'Elena María Zambrano Ruiz', level: 1 })).toBeVisible()
  await expect(page.getByText('Ficha actualizada.')).toBeVisible()
  await expect(page.getByText('Ecuatoriana')).toBeVisible()
  await expect(page.getByText(codigo, { exact: true })).toBeVisible()
})

test('Colaborador: consulta su ficha en solo lectura y no accede al directorio', async ({ page }) => {
  await iniciarSesion(page, 'usuario@hrms.test', 'Inicio')

  await expect(page.getByRole('link', { name: 'Colaboradores', exact: true })).toHaveCount(0)
  await page.getByRole('link', { name: 'Mi ficha', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Mi ficha', level: 1 })).toBeVisible()
  await expect(page.getByText('Ana Núñez Paredes')).toBeVisible()
  await expect(page.getByText('0102030405')).toBeVisible()
  await expect(page.getByRole('link', { name: 'Editar' })).toHaveCount(0)

  await page.goto('/colaboradores')
  await expect(page.getByText('No tienes permiso para ver esta información')).toBeVisible()
})

test('Colaborador sin ficha asociada: se le informa', async ({ page }) => {
  await iniciarSesion(page, 'sinficha@hrms.test', 'Inicio')

  await page.getByRole('link', { name: 'Mi ficha', exact: true }).click()
  await expect(page.getByText('Tu cuenta no tiene una ficha asociada')).toBeVisible()
})

for (const ancho of [320, 1440]) {
  test(`sin scroll horizontal a ${ancho}px en el listado, el formulario y el detalle`, async ({ page }) => {
    await page.setViewportSize({ width: ancho, height: 900 })
    await iniciarSesion(page, 'admin@hrms.test', 'Inicio')

    for (const ruta of ['/colaboradores', '/colaboradores/nuevo']) {
      await page.goto(ruta)
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
      const desborda = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)
      expect(desborda, `${ruta} a ${ancho}px`).toBe(false)
    }

    await page.goto('/colaboradores')
    await page.getByRole('link', { name: /^Ver ficha de/ }).first().click()
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
    const desborda = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)
    expect(desborda, `detalle a ${ancho}px`).toBe(false)
  })
}
