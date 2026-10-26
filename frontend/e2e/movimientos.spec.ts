import { execFileSync } from 'node:child_process'
import { readFileSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { expect, test, type Page } from '@playwright/test'

// Movimientos y novedades de personal (feature 020). Los datos de partida se siembran con artisan sobre la base E2E
// (--env=e2e, nunca la de desarrollo) y solo en el tenant 2, para no alterar lo que esperan los E2E de políticas e
// ingresos del tenant 1: administrador admin2@hrms.test y colaboradora colaboradora-e2e@hrms.test (ficha Rosa Chávez).
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
  const codigo = readFileSync(path.join(AQUI, 'support/sembrar-movimientos.php'), 'utf8')
  const salida = execFileSync('php', ['artisan', 'tinker', '--env=e2e', '--execute', codigo], { cwd: BACKEND, encoding: 'utf8' })
  if (!salida.includes('SEMBRADO-OK')) throw new Error(`No se pudieron sembrar los datos del E2E: ${salida}`)
})

test.describe.configure({ mode: 'serial' })

test('Colaboradora presenta un permiso viendo antes las condiciones; el Administrador lo aprueba y ella consulta el resultado', async ({ page }) => {
  await iniciarSesion(page, COLABORADORA)
  await page.goto('/mis-solicitudes')
  await expect(page.getByRole('heading', { name: 'Mis solicitudes', level: 1 })).toBeVisible()
  await expect(page.getByText('Sin solicitudes')).toBeVisible()

  await page.getByRole('link', { name: 'Nueva solicitud' }).click()
  await expect(page.getByRole('heading', { name: 'Nueva solicitud', level: 1 })).toBeVisible()
  const condiciones = page.getByRole('region', { name: 'Condiciones aplicables: Permisos' })
  await expect(condiciones).toBeVisible()
  await expect(condiciones.getByText(/Versión 1/)).toBeVisible()
  await expect(page.getByText(/Al presentar se fija la versión de política vigente/)).toBeVisible()

  // La justificación es obligatoria según la política: se explica junto al campo y no se envía.
  await page.getByLabel('Fecha de inicio').fill(fecha(10))
  await page.getByLabel('Fecha de fin').fill(fecha(11))
  await page.getByRole('button', { name: 'Presentar solicitud' }).click()
  await expect(page.getByText('Este dato es obligatorio.')).toBeVisible()

  // La anticipación mínima la decide el servidor, en días calendario de la organización.
  await page.getByLabel('Fecha de inicio').fill(fecha(0))
  await page.getByLabel('Fecha de fin').fill(fecha(0))
  await page.getByLabel('Motivo o justificación').fill('Trámite personal')
  await page.getByRole('button', { name: 'Presentar solicitud' }).click()
  await expect(page.getByText('No se cumple la anticipación mínima.')).toBeVisible()
  await expect(page.getByLabel('Motivo o justificación')).toHaveValue('Trámite personal')

  await page.getByLabel('Fecha de inicio').fill(fecha(10))
  await page.getByLabel('Fecha de fin').fill(fecha(11))
  await page.getByRole('button', { name: 'Presentar solicitud' }).click()

  await expect(page).toHaveURL(/\/mis-solicitudes$/)
  await expect(page.getByText(/Solicitud presentada/)).toBeVisible()
  const tabla = page.getByRole('table', { name: 'Mis solicitudes' })
  await expect(tabla.getByText('Permiso')).toBeVisible()
  await expect(tabla.getByText('Presentada', { exact: true })).toBeVisible()
  await expect(tabla.getByText('Se conserva la versión 1 de la política con la que presentaste.')).toBeVisible()
  await cerrarSesion(page)

  // El Administrador la ve en su listado, la abre y la aprueba con justificación.
  await iniciarSesion(page, ADMIN)
  await page.goto('/movimientos-personal')
  await expect(page.getByRole('heading', { name: 'Movimientos y novedades', level: 1 })).toBeVisible()
  const listado = page.getByRole('table', { name: 'Movimientos y novedades de la organización' })
  await expect(listado.getByText('Rosa Chávez Díaz')).toBeVisible()
  await listado.getByRole('link', { name: /Abrir Permiso de Rosa Chávez Díaz/ }).first().click()

  await expect(page.getByRole('heading', { name: 'Permiso', level: 1 })).toBeVisible()
  await expect(page.getByRole('list', { name: 'Comprobaciones' })).toBeVisible()
  await page.getByRole('button', { name: 'Aprobar' }).click()
  const dialogo = page.getByRole('dialog', { name: 'Aprobar solicitud' })
  await dialogo.getByRole('button', { name: 'Aprobar' }).click()
  await expect(dialogo.getByText('Escribe la justificación.')).toBeVisible()
  await dialogo.getByLabel('Justificación').fill('Cumple con lo solicitado')
  await dialogo.getByRole('button', { name: 'Aprobar' }).click()
  await expect(page.getByText('Solicitud aprobada.')).toBeVisible()
  await expect(page.getByText('Aprobada', { exact: true }).first()).toBeVisible()
  await expect(page.getByRole('button', { name: 'Aprobar' })).toHaveCount(0)
  await cerrarSesion(page)

  // La Colaboradora consulta el resultado.
  await iniciarSesion(page, COLABORADORA)
  await page.goto('/mis-solicitudes')
  const mias = page.getByRole('table', { name: 'Mis solicitudes' })
  await expect(mias.getByText('Aprobada', { exact: true }).first()).toBeVisible()
  await expect(mias.getByText('Presentada', { exact: true })).toHaveCount(0)
  await cerrarSesion(page)
})

test('Administrador propone un ascenso y no puede aprobarlo sin una evaluación verificable; puede rechazarlo y la Colaboradora no lo ve', async ({ page }) => {
  await iniciarSesion(page, ADMIN)
  await page.goto('/movimientos-personal/proponer')
  await expect(page.getByRole('heading', { name: 'Proponer ascenso o traslado', level: 1 })).toBeVisible()
  await expect(page.getByRole('region', { name: 'Condiciones aplicables: Ascensos' })).toBeVisible()

  await page.getByRole('searchbox', { name: /buscar colaborador/i }).fill('Rosa')
  await page.getByRole('combobox', { name: 'Colaborador' }).click()
  await page.getByRole('option', { name: /Rosa Chávez Díaz/ }).click()

  await expect(page.getByLabel('Situación actual')).toBeVisible()
  await page.getByRole('combobox', { name: 'Posición destino' }).click()
  await page.getByRole('listbox').getByRole('option').first().click()
  await page.getByLabel('Fecha efectiva').fill(fecha(40))
  await page.getByLabel('Justificación').fill('Desempeño sostenido en el área')
  await page.getByRole('button', { name: 'Proponer movimiento' }).click()

  // El detalle separa la evidencia ausente de una evaluación válida: no hay control de aprobar.
  await expect(page.getByRole('heading', { name: 'Ascenso', level: 1 })).toBeVisible()
  await expect(page.getByText('Propuesta presentada. La posición actual no cambió.')).toBeVisible()
  await expect(page.getByText('Evaluación no disponible: no se puede aprobar hasta que exista evidencia verificable.')).toBeVisible()
  await expect(page.getByText('No se puede aprobar: la evaluación frente al puesto destino no es verificable.')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Aprobar' })).toHaveCount(0)
  await expect(page.getByText('Todavía no se aplicó: la posición actual no cambió.')).toBeVisible()

  await page.getByRole('button', { name: 'Rechazar' }).click()
  const dialogo = page.getByRole('dialog', { name: 'Rechazar solicitud' })
  await dialogo.getByLabel('Justificación').fill('Sin evidencia de evaluación frente al puesto destino')
  await dialogo.getByRole('button', { name: 'Rechazar' }).click()
  await expect(page.getByText('Solicitud rechazada.')).toBeVisible()
  await cerrarSesion(page)

  await iniciarSesion(page, COLABORADORA)
  await page.goto('/mis-solicitudes')
  await expect(page.getByRole('table', { name: 'Mis solicitudes' }).getByText('Ascenso')).toHaveCount(0)
  await cerrarSesion(page)
})

test('Administrador informa una ausencia ocurrida como hecho, sin aprobarla, y luego la justifica', async ({ page }) => {
  await iniciarSesion(page, ADMIN)
  await page.goto('/movimientos-personal')
  await page.getByRole('link', { name: 'Informar ausencia' }).click()

  await expect(page.getByRole('heading', { name: 'Informar ausencia ocurrida', level: 1 })).toBeVisible()
  await expect(page.getByText(/Estás informando un hecho ya ocurrido/)).toBeVisible()
  await expect(page.getByLabel('Tipo de solicitud')).toHaveCount(0)

  await page.getByRole('searchbox', { name: /buscar colaborador/i }).fill('Rosa')
  await page.getByRole('combobox', { name: 'Colaborador' }).click()
  await page.getByRole('option', { name: /Rosa Chávez Díaz/ }).click()

  // Una fecha futura no es una ausencia ocurrida.
  await page.getByLabel('Primer día de ausencia').fill(fecha(3))
  await page.getByLabel('Último día de ausencia').fill(fecha(3))
  await page.getByRole('button', { name: 'Informar ausencia' }).click()
  await expect(page.getByText('Una ausencia solo puede informarse una vez ocurrida.').first()).toBeVisible()

  await page.getByLabel('Primer día de ausencia').fill(fecha(-2))
  await page.getByLabel('Último día de ausencia').fill(fecha(-1))
  await page.getByLabel('Motivo o respaldo disponible').fill('Inasistencia sin aviso previo')
  await page.getByRole('button', { name: 'Informar ausencia' }).click()

  await expect(page.getByRole('heading', { name: 'Ausencia', level: 1 })).toBeVisible()
  await expect(page.getByText('Ausencia informada. Queda pendiente de revisión.')).toBeVisible()
  await expect(page.getByText('Hecho ocurrido (no es una autorización)')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Aprobar' })).toHaveCount(0)

  await page.getByRole('button', { name: 'Revisar o justificar' }).click()
  const dialogo = page.getByRole('dialog', { name: 'Revisar o justificar la ausencia' })
  await dialogo.getByLabel('Resultado').selectOption('Justificada')
  await dialogo.getByLabel('Observación').fill('Certificado médico presentado')
  await dialogo.getByRole('button', { name: 'Guardar' }).click()
  await expect(page.getByText('Ausencia registrada como revisada o justificada.')).toBeVisible()
  await expect(page.getByText('Justificada', { exact: true }).first()).toBeVisible()
  await cerrarSesion(page)
})
