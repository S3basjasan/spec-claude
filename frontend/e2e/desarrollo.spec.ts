import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e (PersonalSeeder): EMP-001 Ana Núñez Paredes con formación, experiencia y movilidad
// «Disponible»; EMP-003 María Pérez Soto sin datos de desarrollo (movilidad «No informada»).
// usuario@hrms.test está asociado a EMP-001 con permiso de consulta.
const PASSWORD = 'password-de-prueba'

// Los títulos son únicos por ejecución para que la prueba sea repetible sobre una base ya usada.
const SUFIJO = String(Date.now()).slice(-6)
const FORMACION = `Diplomado E2E ${SUFIJO}`
const CARGO = `Cargo E2E ${SUFIJO}`

async function iniciarSesion(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible()
}

test.describe.configure({ mode: 'serial' })

test('Administrador: agrega formación y experiencia, guarda movilidad y ve el rechazo de fin anterior al inicio', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await page.goto('/colaboradores')
  await page.getByLabel('Buscar colaborador').fill('Pérez')
  await page.getByRole('link', { name: /^Ver ficha de María/ }).click()
  await page.getByRole('tab', { name: 'Desarrollo' }).click()

  // Ficha sin datos: estados vacíos y movilidad no informada.
  await expect(page.getByText('Formación académica no registrada')).toBeVisible({ timeout: 20_000 })
  await expect(page.getByText('Experiencia anterior no registrada')).toBeVisible()
  await expect(page.getByText('No informada').first()).toBeVisible()

  // Una finalización anterior al inicio se rechaza y no guarda nada.
  await page.getByRole('button', { name: 'Agregar formación' }).click()
  await page.getByLabel('Nivel').fill('Tecnología')
  await page.getByLabel('Estado').selectOption('EnCurso')
  await page.getByLabel('Título o programa').fill(FORMACION)
  await page.getByLabel('Institución').fill('Instituto E2E')
  await page.getByLabel('Fecha de inicio').fill('2020-05-01')
  await page.getByLabel('Fecha de finalización').fill('2020-04-01')
  await page.getByRole('button', { name: 'Guardar formación' }).click()
  await expect(page.getByText('La fecha de finalización no puede ser anterior a la de inicio.')).toBeVisible()

  // Corregida la fecha, se guarda y aparece en la lista.
  await page.getByLabel('Fecha de finalización').fill('2021-04-01')
  await page.getByRole('button', { name: 'Guardar formación' }).click()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(page.getByRole('heading', { name: FORMACION })).toBeVisible()

  // Experiencia sin fecha final: «No registrado», sin marca de vigente.
  await page.getByRole('button', { name: 'Agregar experiencia' }).click()
  await page.getByLabel('Organización').fill('Empresa E2E')
  await page.getByLabel('Cargo').fill(CARGO)
  await page.getByLabel('Fecha de inicio').fill('2019-01-07')
  await page.getByRole('button', { name: 'Guardar experiencia' }).click()
  await expect(page.getByRole('heading', { name: CARGO })).toBeVisible()
  await expect(page.getByRole('region', { name: 'Experiencia anterior' }).getByText(/vigente|\bactual\b/i)).toHaveCount(0)

  // Movilidad.
  await page.getByRole('button', { name: 'Actualizar disponibilidad' }).click()
  await page.getByLabel('Disponible', { exact: true }).check()
  await page.getByRole('dialog').getByLabel('Observaciones').fill('Disponible para otras ciudades')
  await page.getByRole('button', { name: 'Guardar disponibilidad' }).click()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(page.getByText('Disponible para otras ciudades')).toBeVisible()
  await expect(page.getByText('Estar disponible no significa que exista un traslado aprobado.')).toBeVisible()

  // Nunca se ofrece eliminar.
  await expect(page.getByRole('button', { name: /eliminar|borrar/i })).toHaveCount(0)
})

test('Colaborador: abre Mi ficha → Desarrollo en solo lectura', async ({ page }) => {
  await iniciarSesion(page, 'usuario@hrms.test')
  await page.getByRole('link', { name: 'Mi ficha', exact: true }).click()
  await page.getByRole('tab', { name: 'Desarrollo' }).click()

  await expect(page.getByRole('heading', { name: 'Ingeniería de Sistemas' })).toBeVisible({ timeout: 20_000 })
  await expect(page.getByText('Disponible para traslado a otra ciudad.')).toBeVisible()
  await expect(page.getByRole('button', { name: /agregar|editar|actualizar|eliminar/i })).toHaveCount(0)
})

for (const ancho of [320, 1440]) {
  test(`sin scroll horizontal a ${ancho}px en la pestaña Desarrollo y sus diálogos`, async ({ page }) => {
    await page.setViewportSize({ width: ancho, height: 900 })
    await iniciarSesion(page, 'admin@hrms.test')
    await page.goto('/colaboradores')
    await page.getByRole('link', { name: /^Ver ficha de/ }).first().click()
    await page.getByRole('tab', { name: 'Desarrollo' }).click()
    await expect(page.getByRole('region', { name: 'Disponibilidad para movilidad' })).toBeVisible({ timeout: 20_000 })

    const desborda = () => page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)
    expect(await desborda(), `pestaña a ${ancho}px`).toBe(false)

    await page.getByRole('button', { name: 'Agregar formación' }).click()
    await expect(page.getByRole('dialog')).toBeVisible()
    expect(await desborda(), `diálogo a ${ancho}px`).toBe(false)
    await page.keyboard.press('Escape')
    await expect(page.getByRole('button', { name: 'Agregar formación' })).toBeFocused()
  })
}
