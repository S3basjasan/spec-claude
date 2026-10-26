import { execFileSync } from 'node:child_process'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { expect, test, type Page } from '@playwright/test'

// Datos de GP-02 (backend/database/seeders/IngresosSeeder.php sobre PersonalSeeder, PuestosSeeder y UnidadesSeeder):
// puesto compartido PU-002 con posiciones del tenant 1. Para ser repetible, cada prueba crea sus propias fichas por la
// interfaz y las posiciones que necesita con artisan (el servidor de pruebas conserva datos entre ejecuciones).
const PASSWORD = 'password-de-prueba'
const BACKEND = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../backend')
const ESPACIO = String(Date.now()).slice(-8)
let contador = 0

function php(codigo: string): string {
  return execFileSync('php', ['artisan', 'tinker', '--env=e2e', '--execute', codigo], { cwd: BACKEND, encoding: 'utf8' })
}

const POSICION = '\\App\\Modules\\Estructura\\Infrastructure\\Persistence\\Eloquent\\Models\\PosicionModel'

function crearPosiciones(cantidad: number): void {
  php(
    `$b = ${POSICION}::query()->where('tenant_id', 1)->whereNotNull('unidad_organizacional_id')->orderBy('id')->first();` +
      `for ($i = 0; $i < ${cantidad}; $i++) { ${POSICION}::query()->create(['puesto_id' => $b->puesto_id, ` +
      `'unidad_organizacional_id' => $b->unidad_organizacional_id, 'estado' => 'Vacante', 'tenant_id' => 1]); } echo 'ok';`,
  )
}

/** Fecha de calendario del tenant (Guayaquil, UTC-5) desplazada `dias` días. */
function fecha(dias = 0): string {
  return new Date(Date.now() - 5 * 3600 * 1000 + dias * 86_400_000).toISOString().slice(0, 10)
}

function identificacion(): string {
  contador += 1
  return `${ESPACIO}${String(contador).padStart(2, '0')}`
}

/** Ficha Desvinculada: un vínculo cerrado ayer en una posición propia. Devuelve el id de la ficha. */
function sembrarDesvinculada(ident: string): number {
  const salida = php(
    `$f = \\App\\Modules\\GestionPersonal\\Infrastructure\\Persistence\\Eloquent\\Models\\ColaboradorModel::factory()->create(` +
      `['tenant_id' => 1, 'numero_identificacion' => '${ident}', 'nombres' => 'Reingreso', 'apellidos' => 'Prueba ${ident}']);` +
      `$b = ${POSICION}::query()->where('tenant_id', 1)->whereNotNull('unidad_organizacional_id')->first();` +
      `$p = ${POSICION}::query()->create(['puesto_id' => $b->puesto_id, 'unidad_organizacional_id' => $b->unidad_organizacional_id, 'estado' => 'Vacante', 'tenant_id' => 1]);` +
      `$v = \\Illuminate\\Support\\Facades\\DB::table('vinculos_laborales')->insertGetId(['tenant_id' => 1, 'colaborador_id' => $f->id, ` +
      `'fecha_ingreso' => '${fecha(-400)}', 'fecha_salida' => '${fecha(-1)}', 'tipo_contrato_clave' => 'temporal', 'version' => 1, ` +
      `'operacion_id' => (string) \\Illuminate\\Support\\Str::uuid(), 'operacion_hash' => str_repeat('e', 64), 'created_by' => 1, 'updated_by' => 1, ` +
      `'created_at' => now(), 'updated_at' => now()]);` +
      `\\Illuminate\\Support\\Facades\\DB::table('asignaciones_laborales')->insert(['tenant_id' => 1, 'vinculo_id' => $v, 'posicion_id' => $p->id, ` +
      `'fecha_inicio' => '${fecha(-400)}', 'fecha_fin' => '${fecha(-1)}', 'version' => 1, 'created_by' => 1, 'updated_by' => 1, ` +
      `'created_at' => now(), 'updated_at' => now()]); echo 'FICHA=' . $f->id;`,
  )
  const coincidencia = salida.match(/FICHA=(\d+)/)
  if (!coincidencia) throw new Error(`No se pudo sembrar la ficha desvinculada: ${salida}`)
  return Number(coincidencia[1])
}

async function iniciarSesion(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible()
}

/** Crea una ficha por la interfaz y devuelve su identificación; termina en el detalle. */
async function crearFicha(page: Page, nombres: string): Promise<string> {
  const ident = identificacion()
  await page.goto('/colaboradores/nuevo')
  await page.getByLabel('Número de identificación').fill(ident)
  await page.getByLabel('Nombres').fill(nombres)
  await page.getByLabel('Apellidos').fill(`Ingreso ${ident}`)
  await page.getByRole('button', { name: 'Guardar' }).click()
  await expect(page.getByRole('heading', { name: `${nombres} Ingreso ${ident}`, level: 1 })).toBeVisible()
  return ident
}

async function elegirPrimeraPosicion(page: Page): Promise<number> {
  await page.getByRole('combobox', { name: 'Posición' }).click()
  const opcion = page.getByRole('listbox').getByRole('option').first()
  const etiqueta = (await opcion.textContent()) ?? ''
  await opcion.click()
  const id = etiqueta.match(/\((\d+)\)\s*$/)
  if (!id) throw new Error(`La opción no trae identificador: ${etiqueta}`)
  return Number(id[1])
}

async function token(page: Page): Promise<string> {
  return (await page.evaluate(() => localStorage.getItem('hrms.token'))) ?? ''
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(() => {
  crearPosiciones(6)
})

test('Administrador: registra un ingreso actual y ve el estado Activo con puesto, unidad y trayectoria', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await crearFicha(page, 'Actual')

  await page.getByRole('link', { name: 'Registrar ingreso' }).click()
  await expect(page.getByRole('heading', { name: 'Registrar ingreso', level: 1 })).toBeVisible()

  // Sin datos: errores por campo y nada se crea.
  await page.getByRole('button', { name: 'Confirmar ingreso' }).click()
  await expect(page.getByText('Este campo es obligatorio.').first()).toBeVisible()

  await page.getByLabel('Tipo de contrato').selectOption('indefinido')
  await elegirPrimeraPosicion(page)
  await expect(page.getByLabel('Datos de la posición')).toContainText('Jefe de talento humano')
  await page.getByRole('button', { name: 'Confirmar ingreso' }).click()

  await expect(page.getByText('Ingreso registrado.')).toBeVisible()
  await expect(page.getByText('Activo').first()).toBeVisible()
  await expect(page.getByText('Jefe de talento humano').first()).toBeVisible()
  await expect(page.getByText(/Contrato: Indefinido/)).toBeVisible()
  await expect(page.getByRole('link', { name: 'Registrar ingreso' })).toBeVisible()
})

test('Administrador: programa un ingreso futuro y la ficha sigue Sin vínculo vigente', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await crearFicha(page, 'Programado')

  await page.getByRole('link', { name: 'Registrar ingreso' }).click()
  await page.getByLabel('Fecha de ingreso').fill(fecha(30))
  await expect(page.getByText('Ingreso programado').first()).toBeVisible()
  await page.getByLabel('Tipo de contrato').selectOption('plazo_fijo')
  await elegirPrimeraPosicion(page)
  await page.getByRole('button', { name: 'Confirmar ingreso' }).click()

  await expect(page.getByText('Ingreso programado registrado.')).toBeVisible()
  await expect(page.getByText('Sin vínculo vigente').first()).toBeVisible()
  await expect(page.getByText(`Desde el ${fecha(30)} · Plazo fijo`)).toBeVisible()
})

async function crearFichaAparte(page: Page, posicionId: number): Promise<void> {
  const ident = identificacion()
  const cabeceras = { Authorization: `Bearer ${await token(page)}`, Accept: 'application/json', 'Content-Type': 'application/json' }
  const API = 'http://127.0.0.1:8001'

  const ficha = await page.request.post(`${API}/api/colaboradores`, {
    headers: { ...cabeceras, 'Idempotency-Key': crypto.randomUUID() },
    data: { tipo_identificacion: 'Cedula', numero_identificacion: ident, nombres: 'Ocupante', apellidos: `Previo ${ident}` },
  })
  expect(ficha.status()).toBe(201)
  const fichaId = (await ficha.json()).data.id as number

  const ingreso = await page.request.post(`${API}/api/colaboradores/${fichaId}/ingresos`, {
    headers: { ...cabeceras, 'Idempotency-Key': crypto.randomUUID() },
    data: { fecha_ingreso: fecha(0), tipo_contrato: 'indefinido', posicion_id: posicionId },
  })
  expect(ingreso.status()).toBe(201)
}

test('Administrador: confirma contra una posición recién ocupada y recibe el motivo sin perder lo escrito', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await crearFicha(page, 'Rechazo')
  await page.getByRole('link', { name: 'Registrar ingreso' }).click()
  await page.getByLabel('Tipo de contrato').selectOption('servicios_profesionales')
  const posicion = await elegirPrimeraPosicion(page)

  await crearFichaAparte(page, posicion)

  await page.getByRole('button', { name: 'Confirmar ingreso' }).click()

  await expect(page.getByRole('alert').filter({ hasText: /posición está ocupada/ }).first()).toBeVisible()
  await expect(page.getByLabel('Tipo de contrato')).toHaveValue('servicios_profesionales')
})

test('Administrador: registra un reingreso de una ficha Desvinculada conservando la historia', async ({ page }) => {
  const ident = identificacion()
  const fichaId = sembrarDesvinculada(ident)
  await iniciarSesion(page, 'admin@hrms.test')

  await page.goto(`/colaboradores/${fichaId}`)
  await expect(page.getByText('Desvinculado').first()).toBeVisible()
  await page.getByRole('link', { name: 'Registrar reingreso' }).click()
  await expect(page.getByRole('heading', { name: 'Registrar reingreso', level: 1 })).toBeVisible()

  await page.getByLabel('Fecha de ingreso').fill(fecha(0))
  await page.getByLabel('Tipo de contrato').selectOption('indefinido')
  await elegirPrimeraPosicion(page)
  await page.getByRole('button', { name: 'Confirmar ingreso' }).click()

  await expect(page.getByText('Ingreso registrado.')).toBeVisible()
  await expect(page.getByText('Activo').first()).toBeVisible()
  const hechos = page.locator('li:has(time)')
  await expect(hechos).toHaveCount(2)
  await expect(hechos.nth(0)).toContainText('Reingreso')
  await expect(hechos.nth(1)).toContainText('Ingreso')
})

test('Colaborador: no ve la acción ni accede al formulario de ingreso', async ({ page }) => {
  await iniciarSesion(page, 'usuario@hrms.test')

  await page.getByRole('link', { name: 'Mi ficha', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Mi ficha', level: 1 })).toBeVisible()
  await expect(page.getByRole('link', { name: /Registrar (re)?ingreso/ })).toHaveCount(0)

  await page.goto('/colaboradores/1/ingreso')
  await expect(page.getByText('No tienes permiso para ver esta información')).toBeVisible()
})

for (const ancho of [320, 1440]) {
  test(`el formulario de ingreso no genera scroll horizontal a ${ancho}px`, async ({ page }) => {
    await page.setViewportSize({ width: ancho, height: 900 })
    await iniciarSesion(page, 'admin@hrms.test')
    await crearFicha(page, 'Ancho')
    await page.getByRole('link', { name: 'Registrar ingreso' }).click()
    await expect(page.getByRole('heading', { name: 'Registrar ingreso', level: 1 })).toBeVisible()
    await elegirPrimeraPosicion(page)

    const desborda = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)
    expect(desborda, `ingreso a ${ancho}px`).toBe(false)
  })
}
