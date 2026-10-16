import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e (PlanFormacionSeeder + PersonalSeeder) y adaptador de prueba del entorno e2e
// (Infrastructure/Adapters/Pruebas/ColaboradoresVigentesDePrueba: Ana, Luis y María tienen vínculo vigente):
//   admin@hrms.test es el Administrador y el aprobador designado; usuario@hrms.test es Ana Núñez Paredes.
// Las pruebas son seriales: el administrador crea, envía y aprueba un plan nuevo y después Ana lo ve en «Mi plan».
const PASSWORD = 'password-de-prueba'
const ANIO = new Date().getFullYear()
const NOMBRE_PLAN = `Plan E2E ${Date.now() % 100000}`

async function iniciarSesion(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible({ timeout: 30_000 })
}

test.describe.configure({ mode: 'serial', timeout: 120_000 })

test('Administrador: crea un plan con el asistente, lo envía a revisión y el aprobador lo aprueba', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await page.getByRole('link', { name: 'Plan anual', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Plan de formación anual', level: 1 })).toBeVisible()
  // Los cinco planes sembrados, uno por estado.
  const tabla = page.getByRole('table', { name: 'Planes de formación' })
  await expect(tabla.getByRole('row')).not.toHaveCount(1)

  // Paso 1 · Datos generales
  await page.getByRole('link', { name: 'Nuevo plan' }).click()
  await page.getByRole('textbox', { name: 'Nombre del plan', exact: true }).fill(NOMBRE_PLAN)
  await page.getByRole('textbox', { name: 'Año', exact: true }).fill(String(ANIO))
  await page.getByRole('button', { name: 'Siguiente' }).click()

  // Paso 2 · Acciones formativas (una acción completa)
  await page.getByRole('button', { name: 'Agregar acción' }).click()
  const dialogo = page.getByRole('dialog', { name: 'Agregar acción formativa' })
  await dialogo.getByRole('textbox', { name: 'Nombre de la acción', exact: true }).fill('Taller E2E de liderazgo')
  await dialogo.getByRole('combobox', { name: 'Competencias objetivo' }).fill('Lid')
  await dialogo.getByRole('option', { name: /Liderazgo/ }).first().click()
  await dialogo.getByRole('combobox', { name: 'Tipo' }).selectOption('Virtual')
  await dialogo.getByLabel('Fecha de inicio').fill(`${ANIO}-11-02`)
  await dialogo.getByLabel('Fecha de fin').fill(`${ANIO}-11-06`)
  await dialogo.getByRole('textbox', { name: 'Duración en horas', exact: true }).fill('8')
  await dialogo.getByRole('textbox', { name: 'Formador', exact: true }).fill('Ps. Marco Salazar')
  await dialogo.getByRole('textbox', { name: 'Capacidad máxima', exact: true }).fill('5')
  await dialogo.getByRole('textbox', { name: 'Costo (USD)', exact: true }).fill('120')
  await dialogo.getByRole('button', { name: 'Guardar acción' }).click()
  await expect(page.getByText('Taller E2E de liderazgo')).toBeVisible()
  // Aún sin participantes: la acción figura como incompleta hasta el paso 3.
  await expect(page.getByText('Incompleta', { exact: true })).toBeVisible()
  await page.getByRole('button', { name: 'Siguiente' }).click()

  // Paso 3 · Participantes y aprobador
  await page.getByRole('searchbox', { name: 'Buscar colaborador' }).fill('Ana')
  await page.getByRole('button', { name: 'Agregar participante: Ana Núñez Paredes' }).click()
  await expect(page.getByText('Capacidad usada: 1/5')).toBeVisible()
  await page.getByRole('combobox', { name: 'Aprobador del plan' }).selectOption({ label: 'Administrador de prueba' })
  await page.getByRole('button', { name: 'Crear plan en Borrador' }).click()

  // El plan queda en Borrador en la tabla; enviarlo a revisión es una acción aparte.
  await expect(page.getByRole('heading', { name: 'Plan de formación anual', level: 1 })).toBeVisible({ timeout: 30_000 })
  await page.getByRole('searchbox', { name: 'Buscar por nombre' }).fill(NOMBRE_PLAN)
  const fila = page.getByRole('row', { name: new RegExp(NOMBRE_PLAN) })
  await expect(fila).toContainText('Borrador')
  await fila.getByRole('button', { name: `Enviar a revisión: ${NOMBRE_PLAN}` }).click()
  await expect(fila).toContainText('Revisión')

  // El aprobador designado (el mismo administrador) aprueba tras confirmar.
  await fila.getByRole('button', { name: `Aprobar: ${NOMBRE_PLAN}` }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Aprobar', exact: true }).click()
  await expect(fila).toContainText('Aprobado')

  // Detalle con historial; no existe ninguna opción de eliminar.
  await fila.getByRole('link', { name: `Ver detalle: ${NOMBRE_PLAN}` }).click()
  await expect(page.getByRole('heading', { name: NOMBRE_PLAN, level: 1 })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Historial' })).toBeVisible()
  await expect(page.getByText('Borrador → Revisión')).toBeVisible()
  await expect(page.getByText('Revisión → Aprobado')).toBeVisible()
  await expect(page.getByRole('button', { name: /Eliminar/ })).toHaveCount(0)
})

test('Administrador: ve la acción en la lista y en el calendario con filtros comunes', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await page.getByRole('link', { name: 'Plan anual', exact: true }).click()
  await page.getByRole('link', { name: 'Ver acciones formativas' }).click()

  await page.getByRole('tab', { name: 'Lista' }).click()
  await page.getByRole('combobox', { name: 'Estado del plan' }).selectOption('Aprobado')
  const tabla = page.getByRole('table', { name: 'Acciones formativas' })
  await expect(tabla).toContainText('Taller E2E de liderazgo')

  await page.getByRole('tab', { name: 'Calendario' }).click()
  await expect(page.getByRole('combobox', { name: 'Estado del plan' })).toHaveValue('Aprobado')
  await expect(page.getByRole('table', { name: /Calendario de/ })).toBeVisible()
})

test('Colaborador: ve la acción aprobada en «Mi plan de formación» y no accede a la administración', async ({ page }) => {
  await iniciarSesion(page, 'usuario@hrms.test')
  await page.getByRole('link', { name: 'Plan anual', exact: true }).click()

  await expect(page.getByRole('heading', { name: 'Mi plan de formación', level: 1 })).toBeVisible()
  await expect(page.getByRole('progressbar', { name: '% del plan completado' })).toBeVisible()
  await expect(page.getByRole('article', { name: 'Taller E2E de liderazgo' })).toBeVisible()
  await expect(page.getByRole('region', { name: 'Próximas actividades' })).toBeVisible()
  await expect(page.getByRole('link', { name: /Nuevo plan|Ver acciones formativas/ })).toHaveCount(0)

  // La administración es solo del Administrador.
  await page.goto('/plan-formacion/nuevo')
  await expect(page.getByText('No tienes permisos para ver esta pantalla')).toBeVisible()
})

test('Colaborador sin ficha: ve un mensaje informativo', async ({ page }) => {
  await iniciarSesion(page, 'sinficha@hrms.test')
  await page.getByRole('link', { name: 'Plan anual', exact: true }).click()

  await expect(page.getByText('Aún no tienes acciones formativas asignadas')).toBeVisible()
  await expect(page.getByRole('article')).toHaveCount(0)
})
