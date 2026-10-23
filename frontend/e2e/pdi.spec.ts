import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e (PdisSeeder): admin@hrms.test es el Administrador y usuario@hrms.test es Ana Núñez Paredes.
// PdisSeeder deja PDI-0001 (Mejora en Borrador de Ana, sin acuerdo), PDI-0002 (Carrera de Luis con acuerdo del responsable),
// PDI-0003 (Talento Acordado de María) y dos planes Cerrados (PDI-0004 de Luis y PDI-0005 de Ana). Ana ya tiene un Mejora abierto,
// por eso el asistente crea un plan de Talento para ella.
const PASSWORD = 'password-de-prueba'

async function iniciarSesion(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('navigation', { name: 'Navegación principal' })).toBeVisible({ timeout: 30_000 })
}

async function abrirPdi(page: Page) {
  const enlace = page.getByRole('link', { name: /^(PDI|Mi plan de desarrollo)$/ })
  if (!(await enlace.isVisible())) await page.getByRole('button', { name: 'Talento y desarrollo' }).click()
  await enlace.click()
}

async function abrirPlan(page: Page, codigo: string) {
  await page.getByRole('link', { name: `Abrir ${codigo}` }).click()
  await expect(page.getByRole('navigation', { name: 'PDI' }).getByText(codigo)).toBeVisible({ timeout: 30_000 })
}

// Código del plan que crea el segundo test (depende de cuántos planes siembra PdisSeeder).
let codigoNuevo = ''

test.describe.configure({ mode: 'serial', timeout: 120_000 })

test('Administrador: la lista muestra los planes sembrados y se filtra por tipo y estado', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await abrirPdi(page)

  await expect(page.getByRole('heading', { level: 1, name: 'Plan de Desarrollo Individual (PDI)' })).toBeVisible()
  await expect(page.getByRole('table').getByRole('link', { name: 'Abrir PDI-0001' })).toBeVisible({ timeout: 30_000 })
  await expect(page.getByRole('table').getByRole('link', { name: 'Abrir PDI-0005' })).toBeVisible()

  await page.getByLabel('Tipo').selectOption('Talento')
  await expect(page.getByRole('link', { name: 'Abrir PDI-0003' })).toBeVisible({ timeout: 30_000 })
  await expect(page.getByRole('link', { name: 'Abrir PDI-0001' })).toHaveCount(0)

  await page.getByRole('button', { name: 'Limpiar filtros' }).click()
  await page.getByLabel('Estado').selectOption('Cerrado')
  await expect(page.getByRole('link', { name: 'Abrir PDI-0004' })).toBeVisible({ timeout: 30_000 })
  await expect(page.getByRole('link', { name: 'Abrir PDI-0001' })).toHaveCount(0)
})

test('Administrador: crea un plan de Talento para Ana con una acción y un hito y queda en Borrador', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await page.goto('/pdi/nuevo')

  // Paso 1: colaborador, tipo, puesto objetivo y objetivo.
  await page.getByRole('combobox', { name: 'Colaborador' }).click()
  await page.getByRole('option', { name: /Ana Núñez Paredes/ }).click()
  await page.getByLabel('Tipo de PDI').selectOption('Talento')
  await page.getByLabel('Puesto objetivo').selectOption({ index: 1 })
  await page.getByLabel('Objetivo del plan').fill('Preparar a Ana para el Programa de Talentos.')
  await page.getByRole('button', { name: 'Continuar' }).click()

  // Paso 2: si la precarga no trajo compromisos se agrega uno de Meta a mano; luego, una acción.
  if ((await page.getByRole('region', { name: 'Compromiso 1' }).count()) === 0) {
    await page.getByRole('button', { name: 'Agregar compromiso' }).click()
  }
  const compromiso = page.getByRole('region', { name: 'Compromiso 1' })
  await compromiso.getByLabel('Dimensión').selectOption('Meta')
  await compromiso.getByLabel('Descripción', { exact: true }).fill('Presentar una propuesta de mejora de procesos.')
  await compromiso.getByLabel('Descripción de la acción').first().fill('Elaborar la propuesta con apoyo de la jefatura.')
  await compromiso.getByLabel('Tipo de acción').first().selectOption('Otra')
  await page.getByRole('button', { name: 'Continuar' }).click()

  // Paso 3: hito y creación.
  await page.getByRole('button', { name: 'Agregar hito' }).click()
  await page.getByLabel('Descripción del hito').fill('Primera revisión')
  await page.getByLabel('Fecha', { exact: true }).fill(await page.getByLabel('Inicio del periodo').inputValue())
  await page.getByRole('button', { name: 'Crear PDI (Borrador)' }).click()

  const migas = page.getByRole('navigation', { name: 'PDI' })
  const codigo = migas.getByText(/^PDI-\d{4}$/)
  await expect(codigo).toBeVisible({ timeout: 30_000 })
  codigoNuevo = (await codigo.textContent()) ?? ''
  await expect(page.getByText('Borrador').first()).toBeVisible()
})

test('Flujo principal: Ana no ve el Borrador hasta el acuerdo, lo acepta y el administrador lo ve Acordado', async ({ browser }) => {
  const admin = await (await browser.newContext()).newPage()
  const ana = await (await browser.newContext()).newPage()

  await iniciarSesion(ana, 'usuario@hrms.test')
  await abrirPdi(ana)
  await expect(ana.getByRole('heading', { level: 1, name: 'Mi plan de desarrollo' })).toBeVisible({ timeout: 30_000 })
  await expect(ana.getByText('PDI-0001')).toHaveCount(0)

  await iniciarSesion(admin, 'admin@hrms.test')
  await abrirPdi(admin)
  await abrirPlan(admin, 'PDI-0001')
  await admin.getByRole('button', { name: 'Confirmar acuerdo' }).click()
  await expect(admin.getByText('Acuerdo confirmado. El colaborador ya puede ver el plan.')).toBeVisible({ timeout: 30_000 })

  await ana.reload()
  const tarjetas = ana.getByRole('region', { name: 'Mis planes' })
  await expect(tarjetas.getByText('PDI-0001')).toBeVisible({ timeout: 30_000 })
  await tarjetas.getByRole('button', { name: /PDI-0001/ }).click()
  await ana.getByRole('checkbox', { name: /Revisé los compromisos/ }).check()
  await ana.getByRole('button', { name: 'Aceptar plan' }).click()
  await expect(ana.getByText('Plan aceptado. Ahora está Acordado.')).toBeVisible({ timeout: 30_000 })

  await admin.reload()
  await expect(admin.getByText('Acordado').first()).toBeVisible({ timeout: 30_000 })
  await expect(admin.getByRole('button', { name: 'Confirmar acuerdo' })).toHaveCount(0)
})

test('Colaborador: devuelve con comentario un plan con acuerdo y el administrador ve el comentario', async ({ browser }) => {
  const admin = await (await browser.newContext()).newPage()
  const ana = await (await browser.newContext()).newPage()

  await iniciarSesion(admin, 'admin@hrms.test')
  await abrirPdi(admin)
  await abrirPlan(admin, codigoNuevo)
  await admin.getByRole('button', { name: 'Confirmar acuerdo' }).click()
  await expect(admin.getByText('Acuerdo confirmado. El colaborador ya puede ver el plan.')).toBeVisible({ timeout: 30_000 })

  await iniciarSesion(ana, 'usuario@hrms.test')
  await abrirPdi(ana)
  await expect(ana.getByRole('region', { name: 'Mis planes' }).getByText(codigoNuevo)).toBeVisible({ timeout: 30_000 })
  await ana.getByRole('region', { name: 'Mis planes' }).getByRole('button', { name: new RegExp(codigoNuevo) }).click()
  await ana.getByRole('button', { name: 'Devolver con comentario' }).click()
  const dialogo = ana.getByRole('dialog')
  await dialogo.getByLabel('Comentario').fill('Necesito más tiempo para la propuesta.')
  await dialogo.getByRole('button', { name: 'Devolver plan' }).click()
  await expect(ana.getByText('Plan devuelto con tu comentario.')).toBeVisible({ timeout: 30_000 })
  await expect(ana.getByRole('region', { name: 'Mis planes' }).getByText(codigoNuevo)).toHaveCount(0)

  await admin.reload()
  await expect(admin.getByText('Necesito más tiempo para la propuesta.')).toBeVisible({ timeout: 30_000 })
})

test('Administrador: cierra un plan Acordado con el resultado de cada compromiso y se ofrece una nueva adecuación', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await abrirPdi(page)
  await abrirPlan(page, 'PDI-0003')

  await page.getByRole('tab', { name: 'Compromisos y acciones' }).click()
  // La acción vinculada deriva su estado del Plan de Formación: se desvincula para poder cumplirla a mano.
  await page.getByRole('button', { name: 'Desvincular' }).click()
  await expect(page.getByRole('button', { name: 'Desvincular' })).toHaveCount(0, { timeout: 30_000 })
  // Todas las acciones deben quedar Cumplidas o Canceladas antes de cerrar.
  const estados = page.getByLabel('Estado de la acción')
  const cantidad = await estados.count()
  for (let i = 0; i < cantidad; i += 1) {
    const estado = estados.nth(i)
    if ((await estado.inputValue()) === 'Cumplida') continue
    const respuesta = page.waitForResponse((r) => r.url().includes('/api/pdi-acciones/') && r.request().method() === 'PATCH')
    await estado.selectOption('Cumplida')
    await respuesta
    await expect(estado).toHaveValue('Cumplida', { timeout: 30_000 })
  }
  await page.getByRole('tab', { name: 'Resumen' }).click()
  await page.getByRole('button', { name: 'Cerrar', exact: true }).click()
  const dialogo = page.getByRole('dialog')
  const resultados = dialogo.getByLabel(/^Resultado del compromiso/)
  for (let i = 0; i < (await resultados.count()); i += 1) await resultados.nth(i).selectOption('Cerrada')
  await dialogo.getByLabel('Conclusión (opcional)').fill('Cumplió los compromisos del programa.')
  await dialogo.getByRole('button', { name: 'Cerrar plan' }).click()

  await expect(page.getByText('Plan cerrado.')).toBeVisible({ timeout: 30_000 })
  await expect(page.getByText('Brecha cerrada').first()).toBeVisible()
  await expect(page.getByRole('link', { name: 'Generar nueva adecuación' })).toBeVisible()
})
