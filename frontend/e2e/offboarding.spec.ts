import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { expect, test, type Page } from '@playwright/test'

// Offboarding de colaboradores (feature 021). Los datos de partida se siembran con artisan sobre la base E2E (--env=e2e,
// nunca la de desarrollo) y solo en el tenant 2: administrador admin2@hrms.test y colaboradora colaboradora-e2e@hrms.test
// (ficha Rosa Chávez) con vínculo vigente, política de Offboarding publicada y un respaldo disponible.
const PASSWORD = 'password-de-prueba'
const ADMIN = 'admin2@hrms.test'
const COLABORADORA = 'colaboradora-e2e@hrms.test'
const AQUI = path.dirname(fileURLToPath(import.meta.url))
const BACKEND = path.resolve(AQUI, '../../backend')

/** Fecha de calendario del tenant (Guayaquil, UTC-5) desplazada `dias` días. */
function fecha(dias = 0): string {
  return new Date(Date.now() - 5 * 3600 * 1000 + dias * 86_400_000).toISOString().slice(0, 10)
}

async function iniciarSesion(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page).toHaveURL(/\/$/)
}

async function cerrarSesion(page: Page) {
  await page.evaluate(() => window.localStorage.clear())
  await page.evaluate(() => window.sessionStorage.clear())
}

test.beforeAll(() => {
  const codigo = readFileSync(path.join(AQUI, 'support/sembrar-offboarding.php'), 'utf8')
  const salida = execFileSync('php', ['artisan', 'tinker', '--env=e2e', '--execute', codigo], { cwd: BACKEND, encoding: 'utf8' })
  if (!salida.includes('SEMBRADO-OK')) throw new Error(`No se pudieron sembrar los datos del E2E: ${salida}`)
})

test.describe.configure({ mode: 'serial' })

test('Administrador formaliza la salida viendo la política y completa el checklist; la evidencia es obligatoria donde se exige', async ({ page }) => {
  await iniciarSesion(page, ADMIN)
  await page.goto('/offboarding/formalizar')
  await expect(page.getByRole('heading', { name: 'Formalizar salida', level: 1 })).toBeVisible()

  await page.getByRole('searchbox', { name: /buscar colaborador/i }).fill('Rosa')
  await page.getByRole('combobox', { name: 'Colaborador' }).click()
  await page.getByRole('option', { name: /Rosa Chávez Díaz/ }).click()

  const politica = page.getByRole('region', { name: 'Política de offboarding' })
  await expect(politica).toContainText('Política vigente (versión 1)')
  await expect(politica).toContainText('Devolver equipos')
  await expect(politica).toContainText('Entrevista de salida')

  // Sin motivo ni fecha efectiva no se envía.
  await page.getByRole('button', { name: 'Formalizar salida' }).click()
  await expect(page.getByText('Elige el motivo administrativo.')).toBeVisible()
  await expect(page.getByText('Ingresa una fecha válida (AAAA-MM-DD).')).toBeVisible()

  await page.getByLabel('Motivo administrativo').selectOption('Renuncia')
  await page.getByLabel('Fecha efectiva').fill(fecha(0))
  await page.getByRole('button', { name: 'Formalizar salida' }).click()

  await expect(page).toHaveURL(/\/offboarding\/\d+$/)
  await expect(page.getByRole('heading', { name: 'Salida de Rosa Chávez Díaz', level: 1 })).toBeVisible()
  await expect(page.getByText('La salida quedó formalizada. El vínculo seguirá vigente hasta la fecha efectiva.')).toBeVisible()
  const tabla = page.getByRole('table', { name: 'Tareas del checklist' })
  await expect(tabla.getByText('Devolver equipos')).toBeVisible()
  await expect(tabla.getByText('Entrevista de salida', { exact: true })).toBeVisible()

  // Una salida viva por vínculo: volver a formalizarla se explica y no se permite.
  await page.goto('/offboarding/formalizar')
  await page.getByRole('searchbox', { name: /buscar colaborador/i }).fill('Rosa')
  await page.getByRole('combobox', { name: 'Colaborador' }).click()
  await page.getByRole('option', { name: /Rosa Chávez Díaz/ }).click()
  await expect(page.getByText('Esta persona ya tiene una salida programada.')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Formalizar salida' })).toBeDisabled()
  await page.getByRole('link', { name: 'Abrir el expediente' }).click()

  // La tarea sin evidencia obligatoria se completa; la que la exige no, sin un respaldo.
  await page.getByRole('button', { name: 'Completar la tarea «Cerrar accesos»' }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Registrar cumplimiento' }).click()
  await expect(tabla.getByText('Completada', { exact: true })).toBeVisible()

  await page.getByRole('button', { name: 'Completar la tarea «Devolver equipos»' }).click()
  const dialogo = page.getByRole('dialog')
  await dialogo.getByRole('button', { name: 'Registrar cumplimiento' }).click()
  await expect(dialogo.getByText('Esta tarea exige al menos un respaldo.')).toBeVisible()
  await dialogo.getByRole('checkbox', { name: 'Acta de devolución E2E' }).check()
  await dialogo.getByRole('button', { name: 'Registrar cumplimiento' }).click()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(tabla.getByText('Completada', { exact: true })).toHaveCount(2)
  await cerrarSesion(page)
})

test('Colaboradora ve su desvinculación con la entrevista como una tarea voluntaria, guarda avance y la presenta', async ({ page }) => {
  await iniciarSesion(page, COLABORADORA)
  await page.goto('/mi-desvinculacion')

  await expect(page.getByRole('heading', { name: 'Mi desvinculación', level: 1 })).toBeVisible()
  await expect(page.getByText('Renuncia voluntaria')).toBeVisible()
  await expect(page.getByText('Devolver equipos')).toBeVisible()
  await expect(page.getByText(/La entrevista de salida es voluntaria/)).toBeVisible()
  // No se le muestran datos administrativos.
  await expect(page.getByText('Acta de devolución E2E')).toHaveCount(0)

  await page.getByRole('link', { name: 'Responder la entrevista' }).click()
  await expect(page.getByRole('heading', { name: 'Entrevista de salida', level: 1 })).toBeVisible()
  await expect(page.getByText(/Tu participación es voluntaria y todas las preguntas son opcionales/)).toBeVisible()

  const desarrollo = page.getByRole('group', { name: /oportunidades de crecimiento/ })
  await desarrollo.getByRole('radio', { name: '2 · Insatisfecho' }).check()
  await page.getByRole('checkbox', { name: /Compensación/ }).check()
  await page.getByLabel('¿Desea compartir alguna observación adicional?').fill('Gracias por el apoyo recibido.')
  await page.getByRole('button', { name: 'Guardar avance' }).click()
  await expect(page.getByText('Avance guardado. Aún no presentaste la entrevista.')).toBeVisible()

  await page.getByRole('button', { name: 'Presentar la entrevista' }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Presentar' }).click()

  await expect(page).toHaveURL(/\/mi-desvinculacion$/)
  await expect(page.getByText('Entrevista presentada. Gracias por tu participación.')).toBeVisible()
  await expect(page.getByText('La entrevista ya no admite cambios.')).toBeVisible()
  await expect(page.getByRole('link', { name: 'Responder la entrevista' })).toHaveCount(0)
  await cerrarSesion(page)
})

test('Administrador aplica la salida, confirma el cierre con todo resuelto y no puede leer las respuestas sin el permiso específico', async ({ page }) => {
  await iniciarSesion(page, ADMIN)
  await page.goto('/offboarding')
  await expect(page.getByRole('heading', { name: 'Offboarding de colaboradores', level: 1 })).toBeVisible()
  const listado = page.getByRole('table', { name: 'Salidas formalizadas de la organización' })
  await expect(listado.getByText('Rosa Chávez Díaz')).toBeVisible()
  await expect(listado.getByText('Entrevista completada')).toBeVisible()
  await listado.getByRole('link', { name: /Abrir el expediente de Rosa Chávez Díaz/ }).click()

  // El cierre no se confirma mientras la salida no sea efectiva, aunque el checklist y la entrevista estén resueltos.
  await expect(page.getByText('La salida todavía no es efectiva.')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Confirmar cierre' })).toBeDisabled()

  await page.getByRole('button', { name: 'Aplicar la salida ahora' }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Aplicar la salida' }).click()
  await expect(page.getByText(/La salida es efectiva desde el/)).toBeVisible()
  // La finalización del vínculo no se presenta como cuenta deshabilitada.
  await expect(page.getByText('Tratamiento de la cuenta pendiente de verificar')).toBeVisible()

  await expect(page.getByText('Todo está resuelto: puedes confirmar el cierre.')).toBeVisible()
  await page.getByRole('button', { name: 'Confirmar cierre' }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Confirmar el cierre' }).click()
  await expect(page.getByText(/Cierre confirmado por/).first()).toBeVisible()
  await expect(page.getByRole('button', { name: 'Confirmar cierre' })).toHaveCount(0)

  await page.getByRole('button', { name: 'Ver respuestas originales' }).click()
  await expect(page.getByText('No tienes el permiso específico para consultar las respuestas de las entrevistas de salida.')).toBeVisible()
  await expect(page.getByText('Gracias por el apoyo recibido.')).toHaveCount(0)

  const historial = page.getByRole('region', { name: 'Historial' })
  await expect(historial.getByText('Salida aplicada')).toBeVisible()
  await expect(historial.getByText('Cierre confirmado')).toBeVisible()
  await cerrarSesion(page)
})
