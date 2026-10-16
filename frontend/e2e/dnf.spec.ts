import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e (DnfSeeder + PersonalSeeder) y adaptadores de prueba del entorno e2e
// (Infrastructure/Adapters/Pruebas: colaboradores Ana, Luis y María como evaluables, niveles deterministas y plan simulado):
//   DNF-0001 individual de Ana Núñez Paredes y DNF-0002 masiva de la primera unidad con Ana, Luis y María.
//   Última DNF de Ana (usuario@hrms.test): la masiva DNF-0002 → Crítica (nivel D contra A) y Media (nivel D contra C).
//   sinficha@hrms.test no tiene ficha, por lo que no tiene diagnóstico.
// Las pruebas son seriales: primero se consultan las DNF sembradas (colaboradores) y después el administrador genera DNF nuevas
// de Luis y de la unidad, que cambian la última DNF de Ana.
const PASSWORD = 'password-de-prueba'

async function iniciarSesion(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible({ timeout: 30_000 })
}

async function abrirDnf(page: Page) {
  await page.getByRole('link', { name: 'DNF', exact: true }).click()
}

test.describe.configure({ mode: 'serial', timeout: 120_000 })

test('Colaborador: ve sus brechas prioritarias, llega a los cursos sugeridos y no accede al historial', async ({ page }) => {
  await iniciarSesion(page, 'usuario@hrms.test')
  await abrirDnf(page)

  await expect(page.getByRole('heading', { name: 'Mis brechas formativas', level: 1 })).toBeVisible()
  const prioritarias = page.getByRole('region', { name: 'Mis brechas prioritarias' })
  await expect(prioritarias.getByRole('article')).toHaveCount(2)
  await expect(prioritarias.getByRole('article').first()).toContainText('Nivel actual: D · Requerido: A · Diferencia: -3')
  await expect(prioritarias.getByRole('article').first()).toContainText('Crítica')

  // Sin controles de administrador ni del prototipo.
  await expect(page.getByRole('button', { name: /Generar|Ver como/ })).toHaveCount(0)
  await expect(page.getByRole('link', { name: 'Ver historial' })).toHaveCount(0)

  // «Ver cursos sugeridos» lleva al catálogo formativo con la competencia abierta.
  await prioritarias.getByRole('link', { name: /Ver cursos sugeridos para/ }).first().click()
  await expect(page).toHaveURL(/\/formacion\?competencia=\d+/)

  // El historial es solo del administrador.
  await page.goto('/dnf/historial')
  await expect(page.getByText('No tienes permisos para ver esta pantalla')).toBeVisible()
})

test('Colaborador sin ficha ni diagnóstico: ve un mensaje informativo en lugar de tarjetas', async ({ page }) => {
  await iniciarSesion(page, 'sinficha@hrms.test')
  await abrirDnf(page)

  await expect(page.getByText('Aún no tienes un diagnóstico')).toBeVisible()
  await expect(page.getByRole('article')).toHaveCount(0)
})

test('Administrador: genera una DNF individual y ve el diagnóstico guardado, sin editar ni eliminar', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await abrirDnf(page)

  await expect(page.getByRole('heading', { name: 'Detección de necesidades formativas', level: 1 })).toBeVisible()
  await expect(page.getByRole('button', { name: /Diagnóstico/ })).toBeDisabled()

  // Paso 1: un colaborador con búsqueda.
  await page.getByRole('combobox', { name: 'Colaborador' }).fill('Luis')
  await page.getByRole('option', { name: /Luis Núñez Andrade/ }).click()
  await page.getByRole('button', { name: 'Detectar necesidades' }).click()

  // Paso 2: el diagnóstico queda guardado con su código y la tabla muestra la brecha de cada competencia.
  await expect(page.getByRole('heading', { name: 'Paso 2 · Diagnóstico: Luis Núñez Andrade' })).toBeVisible()
  await expect(page.getByText(/Diagnóstico guardado como DNF-\d{4}/)).toBeVisible()
  const tabla = page.getByRole('table', { name: 'Diagnóstico por competencia' })
  await expect(tabla).toBeVisible()
  await expect(tabla.getByRole('columnheader')).toHaveText([
    'Competencia', 'Nivel requerido', 'Nivel actual', 'Brecha (grados)', 'Brecha (%)', 'Prioridad', 'Estado',
  ])
  expect(await tabla.getByRole('row').count()).toBeGreaterThan(1)
  await expect(page.getByRole('img', { name: /Gráfico del perfil requerido/ })).toBeVisible()

  // Una DNF es inmutable: no hay acciones de edición ni eliminación.
  await expect(page.getByRole('button', { name: /Editar|Eliminar/ })).toHaveCount(0)

  // Se puede volver a la selección sin perder el diagnóstico.
  await page.getByRole('button', { name: 'Cambiar selección' }).click()
  await expect(page.getByRole('heading', { name: 'Paso 1 · ¿A quién quieres evaluar?' })).toBeVisible()
})

test('Administrador: genera una DNF masiva tras confirmar, ve el resumen por colaborador y abre uno', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await abrirDnf(page)

  await page.getByRole('radio', { name: /Por departamento/ }).check()
  await page.getByLabel('Departamento', { exact: true }).selectOption({ index: 1 })
  await page.getByRole('button', { name: 'Detectar necesidades' }).click()

  // La masiva pide confirmación e informa cuántos colaboradores se evaluarán.
  const dialogo = page.getByRole('alertdialog', { name: 'Generar DNF masiva' })
  await expect(dialogo).toContainText('Se evaluarán 3 colaboradores')
  await page.getByRole('button', { name: 'Cancelar' }).click()
  await expect(dialogo).toHaveCount(0)
  await page.getByRole('button', { name: 'Detectar necesidades' }).click()
  await page.getByRole('button', { name: 'Generar DNF' }).click()

  // Resumen por colaborador, con los excluidos informados.
  const resumen = page.getByRole('table', { name: 'Resumen por colaborador' })
  await expect(resumen).toBeVisible()
  await expect(resumen.getByRole('row')).toHaveCount(4)
  await expect(page.getByText('Se excluyeron 2 colaboradores de la evaluación:')).toBeVisible()
  await expect(page.getByText(/José Ángel Mora — No tiene puesto/)).toBeVisible()
  await expect(page.getByText(/Carla Vera Lema — No tiene vínculo laboral vigente/)).toBeVisible()

  // Al elegir un colaborador se ve su diagnóstico y se puede volver al resumen.
  await page.getByRole('button', { name: /Ver diagnóstico de Ana Núñez Paredes/ }).click()
  await expect(page.getByRole('table', { name: 'Diagnóstico por competencia' })).toBeVisible()
  await page.getByRole('button', { name: 'Volver al resumen' }).click()
  await expect(resumen).toBeVisible()
})

test('Administrador: filtra el historial y abre el detalle de solo lectura de una DNF', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await abrirDnf(page)
  await page.getByRole('link', { name: 'Ver historial' }).click()

  await expect(page.getByRole('heading', { name: 'Historial de DNF', level: 1 })).toBeVisible()
  const tabla = page.getByRole('table', { name: 'Historial de diagnósticos' })
  // Las dos sembradas más las generadas en las pruebas anteriores, de la más reciente a la más antigua.
  await expect(tabla.getByRole('row').nth(1)).toContainText('DNF-')
  expect(await tabla.getByRole('row').count()).toBeGreaterThanOrEqual(5)

  // Los filtros viven en la URL.
  await page.getByLabel('Prioridad más alta').selectOption('Critica')
  await expect(page).toHaveURL(/prioridad=Critica/)
  await expect(tabla.getByRole('row').nth(1)).toContainText('Crítica')

  // Detalle de solo lectura de la individual sembrada: sin acciones de edición ni eliminación.
  await page.getByRole('button', { name: 'Limpiar filtros' }).click()
  await page.getByLabel('Colaborador', { exact: true }).fill('Ana')
  await page.getByRole('option', { name: /Ana Núñez Paredes/ }).click()
  await page.getByRole('link', { name: 'Ver detalle de DNF-0001' }).click()
  await expect(page.getByRole('heading', { name: 'DNF-0001 · Ana Núñez Paredes', level: 1 })).toBeVisible()
  await expect(page.getByText('Detalle de solo lectura')).toBeVisible()
  await expect(page.getByText(/Generada por Administrador de prueba/)).toBeVisible()
  await expect(page.getByRole('table', { name: 'Diagnóstico por competencia' })).toBeVisible()
  await expect(page.getByRole('button', { name: /Editar|Eliminar/ })).toHaveCount(0)

  // Los filtros del diagnóstico también viven en la URL del detalle.
  await page.getByLabel('Filtrar por estado').selectOption('Cumple')
  await expect(page).toHaveURL(/estado=Cumple/)
})

test('Administrador: genera el borrador de plan una sola vez desde una DNF con brechas', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await page.goto('/dnf/1')

  await expect(page.getByRole('heading', { name: 'DNF-0001 · Ana Núñez Paredes', level: 1 })).toBeVisible()
  await page.getByRole('button', { name: 'Generar Plan de Formación' }).click()

  // Queda registrado el plan: sin botón y con el enlace al plan generado.
  await expect(page.getByText(/Esta DNF ya generó un plan de formación\./)).toBeVisible()
  await expect(page.getByRole('link', { name: 'Ver plan' })).toHaveAttribute('href', /\/plan-formacion\/\d+/)
  await expect(page.getByRole('button', { name: 'Generar Plan de Formación' })).toHaveCount(0)

  // El historial lo refleja y recargar mantiene el estado.
  await page.reload()
  await expect(page.getByText(/Esta DNF ya generó un plan de formación\./)).toBeVisible()
})

test('foco visible, tema oscuro y sin scroll horizontal a 320 y 1440 px en el diagnóstico, el historial y las tarjetas', async ({ page }) => {
  const sinScroll = () => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)

  await iniciarSesion(page, 'admin@hrms.test')
  await page.goto('/dnf/historial')
  await page.getByLabel('Prioridad más alta').focus()
  expect(await page.getByLabel('Prioridad más alta').evaluate((el) => getComputedStyle(el).outlineWidth)).toBe('2px')
  await page.getByRole('button', { name: 'Cambiar a tema oscuro' }).click()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')

  for (const ancho of [320, 1440]) {
    await page.setViewportSize({ width: ancho, height: 800 })
    await page.goto('/dnf/historial')
    await expect(page.getByRole('table', { name: 'Historial de diagnósticos' })).toBeVisible()
    expect(await sinScroll()).toBe(true)

    await page.goto('/dnf/1')
    await expect(page.getByRole('table', { name: 'Diagnóstico por competencia' })).toBeVisible()
    await expect(page.getByRole('img', { name: /Gráfico del perfil requerido/ })).toBeVisible()
    expect(await sinScroll()).toBe(true)

    await page.goto('/dnf')
    await expect(page.getByRole('heading', { name: 'Paso 1 · ¿A quién quieres evaluar?' })).toBeVisible()
    expect(await sinScroll()).toBe(true)
  }

  // Vista del colaborador.
  await page.getByRole('button', { name: 'Menú de usuario: Administrador de prueba' }).click()
  await page.getByRole('menuitem', { name: 'Cerrar sesión' }).click()
  await iniciarSesion(page, 'usuario@hrms.test')
  for (const ancho of [320, 1440]) {
    await page.setViewportSize({ width: ancho, height: 800 })
    await page.goto('/dnf')
    await expect(page.getByRole('region', { name: 'Mis brechas prioritarias' })).toBeVisible()
    expect(await sinScroll()).toBe(true)
  }
})
