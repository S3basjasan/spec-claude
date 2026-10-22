import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e (PersonalSeeder): las siete cabeceras de políticas del tenant 1 y Permisos con la
// versión 1 vigente de ejemplo; los demás tipos quedan «sin configurar». usuario@hrms.test es Colaborador.
const PASSWORD = 'password-de-prueba'
const ANIO_SIGUIENTE = new Date().getFullYear() + 1

async function iniciarSesion(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible()
}

async function completarPublicacion(page: Page, motivo: string) {
  const dialogo = page.getByRole('dialog', { name: 'Revisar y publicar' })
  await expect(dialogo.getByText('Condiciones resultantes')).toBeVisible({ timeout: 20_000 })
  await dialogo.getByLabel('Motivo del cambio').fill(motivo)
  await dialogo.getByLabel('Quién revisó la configuración').fill('Ana Núñez')
  return dialogo
}

test.describe.configure({ mode: 'serial' })

test('Administrador: ve los tipos sin configurar, publica una política y programa, compara y retira otra versión', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await page.getByRole('link', { name: 'Políticas de personal' }).click()
  await expect(page.getByRole('heading', { name: 'Políticas de personal', level: 1 })).toBeVisible()

  // Siete tipos: Permisos vigente y el resto «sin configurar» (nunca «sin restricciones»).
  const tabla = page.getByRole('table', { name: /políticas de personal por tipo/i })
  await expect(tabla.getByRole('row')).toHaveCount(8)
  await expect(tabla.getByText('Política sin configurar')).toHaveCount(6)
  await expect(page.getByText(/sin restricciones/i)).toHaveCount(0)

  // Filtro por situación guardado en la URL.
  await page.getByLabel('Situación').selectOption('Vigente')
  await expect(tabla.getByRole('row')).toHaveCount(2)
  expect(page.url()).toContain('situacion=Vigente')
  await page.getByRole('button', { name: 'Limpiar filtros' }).click()

  // Licencias: crear, completar y publicar de inmediato.
  await page.getByRole('link', { name: 'Ver política de Licencias' }).click()
  await expect(page.getByRole('heading', { name: 'Licencias', level: 1 })).toBeVisible()
  await expect(page.getByText('Política sin configurar').first()).toBeVisible()
  await page.getByRole('button', { name: 'Crear borrador' }).click()

  await expect(page.getByRole('heading', { name: 'Borrador de Licencias' })).toBeVisible()
  await expect(page.getByRole('region', { name: 'Reglas fijas' })).toBeVisible()
  await page.getByLabel('Descripción').fill('Licencias con justificación y documento')
  await page.getByLabel('Permite nuevas solicitudes').selectOption('true')
  await page.getByLabel('Exige justificación').selectOption('true')
  await page.getByLabel('Exige documento de respaldo').selectOption('true')
  await page.getByLabel('Anticipación mínima (días calendario)').fill('3')

  // Una anticipación inválida se rechaza junto al campo.
  await page.getByLabel('Anticipación mínima (días calendario)').fill('-1')
  await page.getByRole('button', { name: 'Guardar borrador' }).click()
  await expect(page.getByText(/igual o mayor que 0/i)).toBeVisible()
  await page.getByLabel('Anticipación mínima (días calendario)').fill('3')

  await page.getByRole('button', { name: 'Revisar y publicar' }).click()
  let dialogo = await completarPublicacion(page, 'Política inicial de licencias')
  await expect(dialogo.getByText('Las solicitudes anteriores conservan sus condiciones.')).toBeVisible()
  await dialogo.getByRole('button', { name: 'Publicar versión' }).click()

  await expect(page.getByText('Versión 1 publicada.')).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Licencias', level: 1 })).toBeVisible()
  await expect(page.getByRole('region', { name: 'Condiciones vigentes' }).getByText('3 días calendario')).toBeVisible()
  await expect(page.getByText('Política sin configurar')).toHaveCount(0)

  // Una versión publicada no muestra controles de edición directa.
  await expect(page.getByRole('button', { name: /^editar/i })).toHaveCount(0)

  // Nuevo borrador desde la versión 1: se programa para el año siguiente.
  await page.getByRole('button', { name: 'Crear borrador a partir de esta versión' }).click()
  await expect(page.getByRole('heading', { name: 'Borrador de Licencias' })).toBeVisible()
  await page.getByLabel('Anticipación mínima (días calendario)').fill('9')
  await page.getByRole('button', { name: 'Revisar y publicar' }).click()
  dialogo = await completarPublicacion(page, 'Subir la anticipación')
  await expect(dialogo.getByText(/antes:/i)).toBeVisible()
  await dialogo.getByLabel('Programar un inicio').check()
  await dialogo.getByLabel('Fecha y hora de inicio').fill(`${ANIO_SIGUIENTE}-06-15T08:30`)
  await expect(dialogo.getByText(`Comenzará el 15/06/${ANIO_SIGUIENTE} 08:30.`)).toBeVisible({ timeout: 20_000 })
  await dialogo.getByRole('button', { name: 'Publicar versión' }).click()

  await expect(page.getByText('Versión 2 publicada.')).toBeVisible()
  const programada = page.getByRole('region', { name: 'Versión programada' })
  await expect(programada.getByText(`15/06/${ANIO_SIGUIENTE} 08:30`)).toBeVisible()
  await expect(page.getByRole('region', { name: 'Condiciones vigentes' }).getByText('3 días calendario')).toBeVisible()

  // Comparar la programada con la vigente.
  const version2 = page.getByRole('listitem').filter({ has: page.getByRole('heading', { name: 'Versión 2' }) })
  await version2.getByRole('button', { name: 'Comparar con la vigente' }).click()
  await expect(version2.getByText(/antes:/i)).toBeVisible()
  await expect(version2.locator('p', { hasText: /después:/i })).toContainText('9 días calendario')

  // Retirar la programada: la vigente sigue aplicando.
  await version2.getByRole('button', { name: 'Retirar versión' }).click()
  const retiro = page.getByRole('dialog', { name: 'Retirar versión programada' })
  await expect(retiro.getByText('La versión anterior continuará aplicándose.')).toBeVisible()
  await retiro.getByLabel('Justificación del retiro').fill('Se pospone el cambio')
  await retiro.getByRole('button', { name: 'Retirar versión' }).click()

  await expect(page.getByText(/versión 2 retirada\. la versión anterior continúa aplicándose/i)).toBeVisible()
  await expect(page.getByRole('region', { name: 'Versión programada' })).toHaveCount(0)
  await expect(page.getByRole('region', { name: 'Condiciones vigentes' }).getByText('3 días calendario')).toBeVisible()
  await expect(version2.getByText('Retirada', { exact: true })).toBeVisible()
})

test('Administrador: un segundo borrador del mismo tipo no se crea y se puede continuar el existente', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await page.goto('/politicas-personal/vacaciones')

  await page.getByRole('button', { name: 'Crear borrador' }).click()
  await expect(page.getByRole('heading', { name: 'Borrador de Vacaciones' })).toBeVisible()
  await page.goto('/politicas-personal/vacaciones')

  await expect(page.getByRole('link', { name: 'Continuar borrador' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Crear borrador', exact: true })).toHaveCount(0)

  // Descartar libera el cupo.
  await page.getByRole('link', { name: 'Continuar borrador' }).click()
  await page.getByRole('button', { name: 'Descartar borrador' }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Descartar borrador' }).click()
  await expect(page.getByText('Borrador descartado.')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Crear borrador', exact: true })).toBeVisible()
})

test('Colaborador: no ve el panel de políticas ni accede a su ruta', async ({ page }) => {
  await iniciarSesion(page, 'usuario@hrms.test')

  await expect(page.getByRole('link', { name: 'Políticas de personal' })).toHaveCount(0)
  await page.goto('/politicas-personal')
  await expect(page.getByText('No tienes permiso para ver esta información')).toBeVisible()
  await page.goto('/politicas-personal/permisos/borrador')
  await expect(page.getByText('No tienes permiso para ver esta información')).toBeVisible()
})

for (const ancho of [320, 1440]) {
  test(`sin scroll horizontal a ${ancho}px en el listado, el detalle y el editor`, async ({ page }) => {
    await page.setViewportSize({ width: ancho, height: 900 })
    await iniciarSesion(page, 'admin@hrms.test')

    for (const ruta of ['/politicas-personal', '/politicas-personal/permisos', '/politicas-personal/offboarding']) {
      await page.goto(ruta)
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
      const desborda = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)
      expect(desborda, `${ruta} a ${ancho}px`).toBe(false)
    }

    await page.goto('/politicas-personal/offboarding')
    await page.getByRole('button', { name: 'Crear borrador' }).click()
    await expect(page.getByRole('heading', { name: 'Borrador de Offboarding' })).toBeVisible()
    await page.getByRole('button', { name: 'Agregar tarea' }).click()
    const desborda = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)
    expect(desborda, `editor a ${ancho}px`).toBe(false)
    await page.getByRole('button', { name: 'Descartar borrador' }).click()
    await page.getByRole('alertdialog').getByRole('button', { name: 'Descartar borrador' }).click()
    await expect(page.getByText('Borrador descartado.')).toBeVisible()
  })
}
