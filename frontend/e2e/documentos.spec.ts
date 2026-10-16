import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e (PersonalSeeder): categorías Contratos y Certificados (Activas); EMP-001 Ana con dos
// documentos sintéticos («Contrato de ejemplo» visible y «Certificado archivado de ejemplo» archivado); EMP-003 María sin
// documentos. usuario@hrms.test es Colaborador de Ana con permiso documental.
const PASSWORD = 'password-de-prueba'

// Los títulos son únicos por ejecución para que la prueba sea repetible sobre una base ya usada.
const SUFIJO = String(Date.now()).slice(-6)
const TITULO = `Documento E2E ${SUFIJO}`

/** PDF mínimo y estructuralmente válido, sin datos personales. */
function pdfMinimo(texto: string): Buffer {
  const flujo = `BT /F1 14 Tf 72 720 Td (${texto}) Tj ET`
  const objetos = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Count 1 /Kids [3 0 R] >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
    `<< /Length ${flujo.length} >>\nstream\n${flujo}\nendstream`,
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
  ]
  let pdf = '%PDF-1.4\n'
  const desplazamientos: number[] = []
  objetos.forEach((cuerpo, indice) => {
    desplazamientos.push(pdf.length)
    pdf += `${indice + 1} 0 obj\n${cuerpo}\nendobj\n`
  })
  const xref = pdf.length
  pdf += `xref\n0 ${objetos.length + 1}\n0000000000 65535 f \n`
  for (const desplazamiento of desplazamientos) pdf += `${String(desplazamiento).padStart(10, '0')} 00000 n \n`
  pdf += `trailer\n<< /Size ${objetos.length + 1} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF\n`
  return Buffer.from(pdf, 'latin1')
}

async function iniciarSesion(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible()
}

test.describe.configure({ mode: 'serial' })

test('Administrador: sube, rechaza archivos inválidos, cambia la visibilidad y archiva un documento', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await page.goto('/colaboradores')
  await page.getByLabel('Buscar colaborador').fill('Pérez')
  await page.getByRole('link', { name: /^Ver ficha de María/ }).click()
  await page.getByRole('tab', { name: 'Documentos' }).click()
  await expect(page.getByText('Aún no hay documentos').or(page.getByRole('table'))).toBeVisible({ timeout: 20_000 })

  // Un archivo con extensión .pdf pero contenido de imagen se rechaza por contenido, con el motivo explicado.
  await page.getByRole('button', { name: 'Subir documento' }).first().click()
  const dialogo = page.getByRole('dialog')
  await dialogo.getByLabel('Título').fill(TITULO)
  await dialogo.getByLabel('Categoría').selectOption({ label: 'Contratos' })
  await dialogo.getByLabel('Archivo PDF').setInputFiles({ name: 'falso.pdf', mimeType: 'application/pdf', buffer: Buffer.from('esto no es un pdf') })
  await dialogo.getByRole('button', { name: 'Subir' }).click()
  await expect(dialogo.getByText('El formato no es válido. Selecciona un archivo PDF.').first()).toBeVisible()
  await expect(dialogo.getByLabel('Título')).toHaveValue(TITULO)

  // Un PDF válido se sube y aparece Disponible, no visible para el colaborador.
  await dialogo.getByLabel('Archivo PDF').setInputFiles({ name: 'contrato.pdf', mimeType: 'application/pdf', buffer: pdfMinimo('Contrato E2E') })
  await dialogo.getByRole('button', { name: 'Subir' }).click()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(page.getByText('Documento subido.')).toBeVisible()
  const fila = page.getByRole('row', { name: new RegExp(TITULO) })
  await expect(fila).toBeVisible()
  await expect(fila.getByRole('button', { name: /^Hacer visible/ })).toBeVisible()

  // Cambia la visibilidad (confirmada por el servidor) y archiva con confirmación.
  await fila.getByRole('button', { name: /^Hacer visible/ }).click()
  await expect(page.getByText('Visibilidad actualizada.')).toBeVisible()
  await expect(fila.getByRole('button', { name: /^Retirar la visibilidad/ })).toBeVisible()
  await expect(page.getByRole('button', { name: /eliminar|restaurar|editar/i })).toHaveCount(0)

  await fila.getByRole('button', { name: `Archivar ${TITULO}` }).click()
  await expect(page.getByRole('alertdialog')).toContainText('Se conservan el archivo y sus datos')
  await page.getByRole('alertdialog').getByRole('button', { name: 'Archivar documento' }).click()
  await expect(page.getByText(/Documento archivado/)).toBeVisible()
  await expect(page.getByRole('row', { name: new RegExp(TITULO) })).toHaveCount(0)

  // El archivado aparece en su vista, sin acciones de escritura.
  await page.getByRole('button', { name: 'Archivados' }).click()
  const archivado = page.getByRole('row', { name: new RegExp(TITULO) })
  await expect(archivado).toBeVisible()
  await expect(archivado.getByRole('button', { name: /archivar|visible/i })).toHaveCount(0)
  await expect(archivado.getByRole('button', { name: `Descargar ${TITULO}` })).toBeVisible()
})

test('Administrador: descarga un documento disponible de Ana', async ({ page }) => {
  await iniciarSesion(page, 'admin@hrms.test')
  await page.goto('/colaboradores')
  await page.getByLabel('Buscar colaborador').fill('Núñez Paredes')
  await page.getByRole('link', { name: /^Ver ficha de Ana/ }).click()
  await page.getByRole('tab', { name: 'Documentos' }).click()

  const descarga = page.waitForEvent('download')
  await page.getByRole('button', { name: 'Descargar Contrato de ejemplo' }).click({ timeout: 20_000 })
  const archivo = await descarga

  expect(archivo.suggestedFilename()).toMatch(/^Documento_\d+\.pdf$/)
})

test('Colaborador: ve solo lo propio, visible y disponible, sin controles de administración', async ({ page }) => {
  await iniciarSesion(page, 'usuario@hrms.test')
  await page.getByRole('link', { name: 'Mis documentos', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Mis documentos', level: 1 })).toBeVisible()

  await expect(page.getByRole('row', { name: /Contrato de ejemplo/ })).toBeVisible({ timeout: 20_000 })
  await expect(page.getByText('Certificado archivado de ejemplo')).toHaveCount(0)
  await expect(page.getByRole('button', { name: /subir|archivar|archivados|visible|eliminar|restaurar|editar/i })).toHaveCount(0)
})

for (const ancho of [320, 1440]) {
  test(`sin scroll horizontal a ${ancho}px en Documentos y su diálogo`, async ({ page }) => {
    await page.setViewportSize({ width: ancho, height: 900 })
    await iniciarSesion(page, 'admin@hrms.test')
    await page.goto('/colaboradores')
    await page.getByLabel('Buscar colaborador').fill('Núñez Paredes')
    await page.getByRole('link', { name: /^Ver ficha de Ana/ }).click()
    await page.getByRole('tab', { name: 'Documentos' }).click()
    await expect(page.getByRole('row', { name: /Contrato de ejemplo/ })).toBeVisible({ timeout: 20_000 })

    const desborda = () => page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth)
    expect(await desborda(), `lista a ${ancho}px`).toBe(false)

    await page.getByRole('button', { name: 'Subir documento' }).first().click()
    await expect(page.getByRole('dialog')).toBeVisible()
    expect(await desborda(), `diálogo a ${ancho}px`).toBe(false)
    await page.keyboard.press('Escape')
    await expect(page.getByRole('button', { name: 'Subir documento' }).first()).toBeFocused()
  })
}
