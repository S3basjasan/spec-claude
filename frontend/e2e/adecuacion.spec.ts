import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e (PuestosSeeder, PersonalSeeder, RutasSeeder y AdecuacionesSeeder):
//   admin@hrms.test es el Administrador y usuario@hrms.test es Ana Núñez Paredes. En el entorno e2e solo Ana Núñez Paredes,
//   Luis Núñez Andrade y María Pérez Soto tienen vínculo laboral vigente (ColaboradoresVigentesDePrueba). Ana está inscrita y
//   Activa en las dos rutas Vigentes sembradas, así que el administrador debe elegir cuál usar. AdecuacionesSeeder deja
//   ADE-0001…0004 (una Lista de Ana, una En desarrollo de Ana, una No lista de Luis y una Incompleta de María).
// El flujo principal: el administrador genera una adecuación con los tiempos de cierre que el diagnóstico pida y después Ana
// consulta «Mi adecuación». El número de brechas depende de los datos sembrados, por eso los tiempos se llenan en bloque.
const PASSWORD = 'password-de-prueba'

async function iniciarSesion(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible({ timeout: 30_000 })
}

async function abrirAdecuacion(page: Page) {
  await page.getByRole('link', { name: 'Adecuación', exact: true }).click()
}

test.describe.configure({ mode: 'serial', timeout: 120_000 })

test('Administrador: genera una adecuación con los tiempos de cierre y queda registrada con su código', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await abrirAdecuacion(page)

  await expect(page.getByRole('heading', { name: 'Adecuación a puesto futuro', level: 1 })).toBeVisible()
  await expect(page.getByRole('note')).toContainText('Esta evaluación es una guía de desarrollo; no implica la promesa de un puesto.')
  await expect(page.getByRole('tab', { name: 'Diagnóstico' })).toBeVisible()

  // Selección: Ana tiene dos inscripciones Activas, por lo que el administrador elige cuál usar.
  const combo = page.getByRole('combobox', { name: 'Colaborador' })
  await combo.click()
  await page.getByRole('option', { name: /Ana Núñez Paredes/ }).click()
  const objetivo = page.getByLabel('Puesto objetivo')
  await expect(objetivo.locator('optgroup[label="Ruta de carrera"] option')).toHaveCount(2, { timeout: 30_000 })
  await objetivo.selectOption({ index: 1 })

  // Diagnóstico: resumen y los tres paneles en el orden del tablero.
  await expect(page.getByRole('region', { name: 'Resumen' })).toBeVisible({ timeout: 30_000 })
  const titulos = await page.getByRole('heading', { level: 2 }).allTextContents()
  expect(titulos.slice(0, 3)).toEqual(['Experiencia', 'Conocimientos', 'Competencias'])
  await expect(page.getByRole('region', { name: 'Conocimientos' })).toContainText('no asigna grado')

  // Cada brecha pide su tiempo de cierre; el registro se bloquea hasta completarlos.
  const registrar = page.getByRole('button', { name: 'Registrar esta evaluación' })
  const campos = page.getByRole('spinbutton', { name: /Tiempo estimado de cierre en meses/ })
  const cantidad = await campos.count()
  if (cantidad > 0) {
    await expect(registrar).toBeDisabled()
    for (let i = 0; i < cantidad; i += 1) await campos.nth(i).fill('6')
  }
  await expect(registrar).toBeEnabled()

  // Los botones del plan de desarrollo siguen deshabilitados mientras no exista esa feature.
  await expect(page.getByRole('button', { name: 'Crear plan de desarrollo' })).toBeDisabled()
  await expect(page.getByRole('button', { name: 'Incluir en Programa de Talentos' })).toBeDisabled()

  await page.getByLabel('Observaciones').fill('Evaluación E2E')
  await registrar.click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Registrar' }).click()

  await expect(page.getByText(/Evaluación registrada como ADE-\d{4}\./)).toBeVisible({ timeout: 30_000 })
  await expect(page.getByText('Cada evaluación es un registro inmutable: no se edita ni se elimina.')).toBeVisible()
})

test('Colaborador: Ana consulta «Mi adecuación» sin pestañas de gestión ni puntajes', async ({ page }) => {
  await iniciarSesion(page, 'usuario@hrms.test')
  await abrirAdecuacion(page)

  await expect(page.getByRole('heading', { name: /^Mi adecuación a / , level: 1 })).toBeVisible({ timeout: 30_000 })
  await expect(page.getByRole('note')).toContainText('Esta evaluación es una guía de desarrollo; no implica la promesa de un puesto.')
  await expect(page.getByRole('note')).toContainText('Solo ves tu propia adecuación.')

  // Las tres tarjetas, en el orden del tablero, y lo que le falta.
  const titulos = await page.getByRole('heading', { level: 2 }).allTextContents()
  expect(titulos.slice(0, 3)).toEqual(['Experiencia', 'Conocimientos', 'Competencias'])
  await expect(page.getByRole('region', { name: 'Lo que me falta' })).toBeVisible()
  await expect(page.getByText('Por ahora no estás incluido en un Programa de Talentos.')).toBeVisible()

  // Nunca ve pestañas de gestión, puntajes ni rankings.
  await expect(page.getByRole('tab')).toHaveCount(0)
  await expect(page.getByText(/puntaje|ranking/i)).toHaveCount(0)
  await expect(page.getByRole('link', { name: 'Mi ruta de carrera' })).toBeVisible()
})

test('Colaborador: «Ver mi adecuación» en Mi ruta de carrera lleva a su adecuación', async ({ page }) => {
  await iniciarSesion(page, 'usuario@hrms.test')
  await page.getByRole('link', { name: 'Rutas de carrera', exact: true }).click()

  const enlace = page.getByRole('link', { name: 'Ver mi adecuación' }).first()
  await expect(enlace).toBeVisible({ timeout: 30_000 })
  await enlace.click()

  await expect(page).toHaveURL(/\/adecuacion$/)
  await expect(page.getByRole('heading', { name: /^Mi adecuación a /, level: 1 })).toBeVisible({ timeout: 30_000 })
})
