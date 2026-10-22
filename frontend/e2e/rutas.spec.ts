import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e (PuestosSeeder, PersonalSeeder y RutasSeeder):
//   admin@hrms.test es el Administrador y usuario@hrms.test es Ana Núñez Paredes. En el entorno e2e solo Ana Núñez Paredes,
//   Luis Núñez Andrade y María Pérez Soto tienen vínculo laboral vigente (ColaboradoresVigentesDePrueba), así que son las
//   únicas personas inscribibles. La siembra deja 4 rutas CAR-0001…0004 (Especialista y Gerencial Vigentes, Interáreas en
//   Borrador y Jóvenes profesionales Archivada) con Ana inscrita y Activa en las dos Vigentes.
// Las pruebas son seriales: el administrador crea su propia ruta, la publica, inscribe, registra el nivel alcanzado y retira;
// después Ana consulta «Mi ruta de carrera» sobre la ruta sembrada. No dependen del orden con las demás especificaciones.
const PASSWORD = 'password-de-prueba'
const SUFIJO = Date.now() % 100000
const RUTA = `E2E Ruta de carrera ${SUFIJO}`

async function iniciarSesion(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible({ timeout: 30_000 })
}

async function abrirRutas(page: Page) {
  await page.getByRole('link', { name: 'Rutas de carrera', exact: true }).click()
}

test.describe.configure({ mode: 'serial', timeout: 120_000 })

test('Administrador: ve las rutas sembradas agrupadas por tipo, el aviso «guía, no promesa» y filtra por nombre', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await abrirRutas(page)

  await expect(page.getByRole('heading', { name: 'Rutas de carrera', level: 1 })).toBeVisible()
  await expect(page.getByRole('note')).toContainText('Esta ruta es una guía de desarrollo; no implica la promesa de un puesto.')
  await expect(page.getByRole('region', { name: 'Especialista' }).getByRole('heading', { name: 'Carrera de especialista en datos' })).toBeVisible({ timeout: 30_000 })
  await expect(page.getByRole('region', { name: 'Gerencial' }).getByRole('heading', { name: 'Carrera gerencial' })).toBeVisible()
  await expect(page.getByRole('region', { name: 'Interáreas' })).toContainText('Borrador')
  await expect(page.getByRole('region', { name: 'Jóvenes profesionales' })).toContainText('Archivada')

  await page.getByRole('searchbox', { name: 'Buscar' }).fill('gerencial')
  await expect(page).toHaveURL(/q=gerencial/)
  await expect(page.getByRole('heading', { name: 'Carrera de especialista en datos' })).toHaveCount(0)
  await page.getByRole('button', { name: 'Limpiar filtros' }).click()
  await expect(page.getByRole('heading', { name: 'Carrera de especialista en datos' })).toBeVisible()
})

test('Administrador: crea una ruta, agrega niveles, ve el detalle informativo y la publica', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await abrirRutas(page)

  await page.getByRole('button', { name: 'Nueva ruta' }).click()
  const dialogo = page.getByRole('dialog', { name: 'Nueva ruta de carrera' })
  await dialogo.getByRole('textbox', { name: 'Nombre de la ruta', exact: true }).fill(RUTA)
  await dialogo.getByLabel('Departamento').selectOption({ index: 1 })
  await dialogo.getByRole('button', { name: 'Crear ruta' }).click()

  await expect(page).toHaveURL(/\/rutas\/\d+/)
  await expect(page.getByRole('heading', { name: RUTA, level: 1 })).toBeVisible()
  await expect(page.getByText('Ruta creada en estado Borrador. Agrega los niveles.')).toBeVisible()
  await expect(page.getByText('Esta ruta aún no tiene niveles.')).toBeVisible()

  // No se puede publicar con menos de 2 niveles.
  await page.getByLabel('Estado de la ruta').selectOption('Vigente')
  await expect(page.getByRole('alert')).toContainText('La ruta necesita al menos 2 niveles.')

  for (const indice of [1, 1]) {
    await page.getByLabel('Agregar nivel (puesto del Descriptivo de puestos)').selectOption({ index: indice })
    await page.getByRole('button', { name: 'Agregar nivel arriba' }).click()
    await expect(page.getByText(/Nivel agregado:/)).toBeVisible()
  }
  await expect(page.getByRole('list', { name: 'Escalera de niveles' }).getByRole('listitem')).toHaveCount(2)

  // Detalle del nivel: competencias con grado, conocimientos solo informativos y experiencia.
  await expect(page.getByRole('heading', { name: 'Competencias', level: 3 })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Conocimientos', level: 3 })).toBeVisible()
  await expect(page.getByText('Informativo', { exact: true })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Experiencia', level: 3 })).toBeVisible()
  await expect(page.getByText(/Escala de grados: A \(más alto\) a D \(más bajo\)/)).toBeVisible()

  // El nombre y la experiencia mínima son lo único propio del nivel.
  await page.getByRole('textbox', { name: 'Experiencia mínima (años)', exact: true }).fill('1,5')
  await page.getByRole('button', { name: 'Guardar nivel' }).click()
  await expect(page.getByText('Nivel actualizado.')).toBeVisible()

  await page.getByLabel('Estado de la ruta').selectOption('Vigente')
  await expect(page.getByText('Estado de la ruta: Vigente.')).toBeVisible()
  await expect(page.getByRole('heading', { name: RUTA, level: 1 }).locator('..').getByText('Vigente', { exact: true })).toBeVisible()
})

test('Administrador: inscribe, registra el nivel alcanzado (Finalizada), retira con motivo y consulta el historial', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await abrirRutas(page)
  await page.getByRole('link', { name: `Abrir ruta ${RUTA}` }).click()
  await page.getByRole('tab', { name: /Participantes/ }).click()

  await expect(page.getByText('Esta ruta no tiene participantes.')).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Cantera', level: 2 })).toBeVisible()

  const inscribir = async (nombre: string, actual: number, objetivo: number) => {
    await expect(page.getByRole('option', { name: nombre })).toBeAttached()
    await page.getByLabel('Colaborador', { exact: true }).selectOption({ label: nombre })
    await page.getByLabel('Nivel actual (opcional)').selectOption({ index: actual })
    await page.getByLabel('Nivel objetivo (opcional)').selectOption({ index: objetivo })
    await page.getByRole('button', { name: 'Inscribir en la ruta' }).click()
    await expect(page.getByText(`${nombre} fue inscrito en la ruta.`)).toBeVisible()
  }

  // Solo se ofrecen colaboradores con vínculo vigente.
  await expect(page.getByLabel('Colaborador', { exact: true }).getByRole('option')).toHaveCount(4)

  await inscribir('Luis Núñez Andrade', 1, 2)
  const fila = page.getByRole('row', { name: /Luis Núñez Andrade/ })
  await expect(fila).toContainText('Activa')

  // «Nivel alcanzado» pide confirmación y, al ser el último nivel, finaliza la inscripción.
  await page.getByRole('button', { name: 'Registrar el nivel alcanzado de Luis Núñez Andrade' }).click()
  const confirmar = page.getByRole('alertdialog', { name: 'Registrar nivel alcanzado' })
  await expect(confirmar).toContainText('el cambio real de puesto lo ejecuta Gestión de Personal')
  await confirmar.getByRole('button', { name: 'Registrar nivel alcanzado' }).click()
  await expect(page.getByText('Luis Núñez Andrade completó la ruta: la inscripción quedó Finalizada.')).toBeVisible()
  await expect(fila).toContainText('Finalizada')

  // Retirar exige el motivo.
  await inscribir('María Pérez Soto', 1, 2)
  await page.getByRole('button', { name: 'Retirar a María Pérez Soto de la ruta' }).click()
  const retiro = page.getByRole('dialog', { name: 'Retirar de la ruta' })
  await retiro.getByRole('button', { name: 'Retirar' }).click()
  await expect(retiro.getByText('El motivo del retiro es obligatorio.')).toBeVisible()
  await retiro.getByRole('textbox', { name: 'Motivo del retiro' }).fill('Cambió de área')
  await retiro.getByRole('button', { name: 'Retirar' }).click()
  await expect(page.getByText('María Pérez Soto salió de la ruta.')).toBeVisible()
  await expect(page.getByRole('row', { name: /María Pérez Soto/ })).toContainText('Retirada')

  // Todo queda en el historial, con usuario, valores y motivo.
  await page.getByRole('tab', { name: 'Historial' }).click()
  const historial = page.getByRole('table', { name: 'Historial de cambios de la ruta' })
  await expect(historial).toContainText('Inscripción retirada')
  await expect(historial).toContainText('Cambió de área')
  await expect(historial).toContainText('Nivel alcanzado')
  await expect(historial).toContainText('Cambio de estado')
})

test('Administrador: archivar una ruta con inscripciones activas pide confirmación con la cantidad', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await abrirRutas(page)
  await page.getByRole('link', { name: 'Abrir ruta Carrera gerencial' }).click()

  await page.getByLabel('Estado de la ruta').selectOption('Archivada')
  const dialogo = page.getByRole('alertdialog', { name: 'Archivar ruta' })
  await expect(dialogo).toContainText('inscripcion')
  await dialogo.getByRole('button', { name: 'Cancelar' }).click()
  await expect(page.getByLabel('Estado de la ruta')).toHaveValue('Vigente')
})

test('Colaborador: «Mi ruta de carrera» muestra su escalera, el aviso y no ofrece acciones de gestión', async ({ page }) => {
  await iniciarSesion(page, 'usuario@hrms.test')
  await abrirRutas(page)

  await expect(page.getByRole('heading', { name: 'Mi ruta de carrera', level: 1 })).toBeVisible()
  await expect(page.getByRole('note')).toContainText('no implica la promesa de un puesto')
  const ruta = page.getByRole('region', { name: 'Carrera de especialista en datos' })
  await expect(ruta).toBeVisible()
  await expect(ruta.getByRole('list', { name: 'Escalera de niveles' }).getByRole('listitem')).toHaveCount(4)
  await expect(ruta.getByRole('button', { name: /Estás aquí/ })).toBeVisible()
  await expect(ruta.getByRole('button', { name: /Objetivo/ })).toBeVisible()
  await expect(ruta.getByRole('heading', { name: 'Competencias', level: 3 })).toBeVisible()
  await expect(ruta.getByText('Informativo', { exact: true })).toBeVisible()

  // Rutas Vigentes disponibles (solo lectura) y nada de gestión ni de interés.
  await expect(page.getByRole('region', { name: 'Rutas disponibles' })).toBeVisible()
  await expect(page.getByRole('button', { name: /Nueva ruta|Inscribir|Retirar|Registrar mi interés/ })).toHaveCount(0)
  // Con la adecuación a puesto futuro (feature 020) disponible, «Ver mi adecuación» se ofrece en las inscripciones con nivel objetivo.
  await expect(page.getByRole('link', { name: 'Ver mi adecuación' }).first()).toHaveAttribute('href', '/adecuacion')
})

test('Colaborador: no puede abrir el editor de una ruta', async ({ page }) => {
  await iniciarSesion(page, 'usuario@hrms.test')
  await page.goto('/rutas/1')

  await expect(page.getByRole('alert')).toContainText('No tienes acceso a esta sección')
})
