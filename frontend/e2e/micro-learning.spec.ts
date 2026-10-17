import { expect, test, type Page } from '@playwright/test'

// Datos sembrados en backend/.env.e2e (MicroLearningSeeder y CatalogoFormativoSeeder):
//   10 micro-contenidos MICRO-0001…0010. Publicados: 0001 Video «Cómo dar y recibir retroalimentación», 0002 Artículo,
//   0003 PDF «Infografía del ciclo PDCA», 0004 Video «Pausas activas para concentrarte» y 0005 Quiz «Quiz: retroalimentación
//   efectiva» (5 preguntas). Borrador: 0006, 0007 y 0009. Archivado: 0008 y 0010.
//   Ana (usuario@hrms.test): Video 0001 en progreso (1:35), PDF 0003 y Quiz 0005 completados (4 de 5).
// Las pruebas son seriales: cada una parte de lo que dejó la anterior.
const PASSWORD = 'password-de-prueba'
const API = 'http://127.0.0.1:8001'
const ADMIN = { email: 'admin@hrms.test' }
const USUARIO = { email: 'usuario@hrms.test' }

async function iniciarSesion(page: Page, email: string) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(PASSWORD)
  await page.getByRole('button', { name: 'Ingresar' }).click()
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible({ timeout: 30_000 })
}

async function abrirMicroLearning(page: Page) {
  await page.getByRole('link', { name: 'Micro-learning', exact: true }).click()
  await expect(page.getByRole('heading', { name: 'Micro-learning', level: 1 })).toBeVisible()
}

async function cabeceras(page: Page) {
  const token = await page.evaluate(() => localStorage.getItem('hrms.token'))
  return { Authorization: `Bearer ${token}`, Accept: 'application/json' }
}

const tarjeta = (page: Page, titulo: string) => page.getByRole('article', { name: titulo })

async function elegirCompetencia(page: Page, nombre: RegExp) {
  await page.getByLabel('Competencias asociadas').first().click()
  await page.getByRole('listbox', { name: 'Competencias asociadas' }).getByRole('option', { name: nombre }).click()
}

test.describe.configure({ mode: 'serial', timeout: 120_000 })

test('Administrador: crea, publica, edita, archiva, reactiva y elimina contenidos', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirMicroLearning(page)

  // Por defecto ve Borrador y Publicado; los archivados no aparecen.
  await expect(page.getByRole('list', { name: 'Repositorio de micro-contenidos' })).toBeVisible()
  await expect(page.getByRole('article')).toHaveCount(8)
  await expect(page.getByRole('heading', { name: 'Reunión efectiva en 10 minutos' })).toHaveCount(0)
  await expect(page.getByText(/refuerzan conocimientos y no reemplazan/).first()).toBeVisible()

  // Con progreso de colaboradores no se ofrece Eliminar.
  const conProgreso = tarjeta(page, 'Cómo dar y recibir retroalimentación')
  await expect(conProgreso.getByRole('button', { name: /Eliminar/ })).toHaveCount(0)
  await expect(conProgreso).toContainText('solo puede archivarse')

  // Crea un Video como borrador con solo título, tipo y duración.
  await page.getByRole('button', { name: '+ Nuevo Contenido' }).click()
  const dialogo = page.getByRole('dialog', { name: 'Nuevo contenido' })
  await dialogo.getByLabel('Título').fill('E2E Video')
  await dialogo.getByLabel('Duración (minutos)').fill('7')
  await dialogo.getByRole('button', { name: 'Guardar borrador' }).click()
  await expect(dialogo).toBeHidden()
  await expect(tarjeta(page, 'E2E Video')).toContainText('Borrador')
  await expect(tarjeta(page, 'E2E Video')).toContainText('MICRO-0011')

  // Publicar sin URL ni competencia informa lo que falta.
  await page.getByRole('button', { name: 'Publicar E2E Video' }).click()
  await expect(page.getByRole('alert')).toContainText('Faltan datos para publicar: la URL, al menos una competencia activa')

  // Edita: agrega URL y competencia, y publica.
  await page.getByRole('button', { name: 'Editar E2E Video' }).click()
  const edicion = page.getByRole('dialog', { name: 'Editar contenido' })
  await expect(edicion.getByLabel('Tipo')).toBeDisabled()
  await edicion.getByLabel('URL del contenido').fill('https://contenido.test/e2e')
  await elegirCompetencia(page, /Liderazgo/)
  await edicion.getByRole('button', { name: 'Guardar cambios' }).click()
  await expect(edicion).toBeHidden()
  await page.getByRole('button', { name: 'Publicar E2E Video' }).click()
  await expect(page.getByText(/E2E Video».*Contenido publicado/)).toBeVisible()
  await expect(tarjeta(page, 'E2E Video')).toContainText('Publicado')

  // El vínculo es el mismo dato que el del Catálogo Formativo: el recurso aparece en la competencia, con su destino.
  const headers = await cabeceras(page)
  const opciones = await (await page.request.get(`${API}/api/microcontenidos/opciones`, { headers })).json()
  const liderazgo = opciones.data.competencias.find((c: { nombre: string }) => c.nombre === 'Liderazgo')
  const detalle = await (await page.request.get(`${API}/api/catalogo-formativo/${liderazgo.id}`, { headers })).json()
  const recurso = detalle.data.microcontenidos.find((m: { nombre: string }) => m.nombre === 'E2E Video')
  expect(recurso.url_destino).toMatch(/^\/micro-learning\/\d+$/)

  // Archiva con confirmación y lo reactiva desde el filtro de archivados.
  await page.getByRole('button', { name: 'Archivar E2E Video' }).click()
  const archivar = page.getByRole('alertdialog')
  await expect(archivar).toContainText('Dejará de estar disponible para los colaboradores')
  await archivar.getByRole('button', { name: 'Archivar' }).click()
  await expect(tarjeta(page, 'E2E Video')).toHaveCount(0)
  await page.getByLabel('Filtrar por estado').selectOption('Archivado')
  await expect(tarjeta(page, 'E2E Video')).toContainText('Archivado')
  await page.getByRole('button', { name: 'Reactivar E2E Video' }).click()
  await expect(tarjeta(page, 'E2E Video')).toHaveCount(0)
  await page.getByLabel('Filtrar por estado').selectOption('Activos')
  await expect(tarjeta(page, 'E2E Video')).toContainText('Publicado')

  // El historial de estados registra cada cambio.
  await page.getByRole('button', { name: 'Historial E2E Video' }).click()
  const historial = page.getByRole('dialog', { name: /Historial de estados/ })
  await expect(historial).toContainText('Creado como Borrador')
  await expect(historial).toContainText('Borrador → Publicado')
  await expect(historial).toContainText('Publicado → Archivado')
  await expect(historial).toContainText('Archivado → Publicado')
  await historial.getByRole('button', { name: 'Cerrar' }).first().click()

  // Elimina un borrador sin progreso con confirmación.
  await page.getByRole('button', { name: '+ Nuevo Contenido' }).click()
  const otro = page.getByRole('dialog', { name: 'Nuevo contenido' })
  await otro.getByLabel('Título').fill('E2E Borrador')
  await otro.getByLabel('Duración (minutos)').fill('3')
  await otro.getByRole('button', { name: 'Guardar borrador' }).click()
  await expect(tarjeta(page, 'E2E Borrador')).toBeVisible()
  await page.getByRole('button', { name: 'Eliminar E2E Borrador' }).click()
  await expect(page.getByRole('alertdialog')).toContainText('Esta acción no se puede deshacer')
  await page.getByRole('alertdialog').getByRole('button', { name: 'Eliminar' }).click()
  await expect(tarjeta(page, 'E2E Borrador')).toHaveCount(0)
})

test('Administrador: arma un quiz con el constructor, respeta los límites y la vista previa no registra nada', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await abrirMicroLearning(page)

  // Límite de 20 preguntas.
  await page.getByRole('button', { name: '+ Nuevo Contenido' }).click()
  const limite = page.getByRole('dialog', { name: 'Nuevo contenido' })
  await limite.getByLabel('Tipo').selectOption('Quiz')
  for (let i = 0; i < 20; i++) await limite.getByRole('button', { name: 'Agregar pregunta' }).click()
  await expect(limite.getByRole('button', { name: 'Agregar pregunta' })).toBeDisabled()
  await expect(limite.getByText('Un quiz admite como máximo 20 preguntas.')).toBeVisible()
  await limite.getByRole('button', { name: 'Cancelar' }).click()
  await expect(limite).toBeHidden()

  // Quiz de dos preguntas: la segunda pasa a ser la primera.
  await page.getByRole('button', { name: '+ Nuevo Contenido' }).click()
  const dialogo = page.getByRole('dialog', { name: 'Nuevo contenido' })
  await dialogo.getByLabel('Título').fill('E2E Quiz')
  await dialogo.getByLabel('Duración (minutos)').fill('5')
  await dialogo.getByLabel('Tipo').selectOption('Quiz')
  await expect(dialogo.getByLabel('URL del contenido')).toHaveCount(0)
  await elegirCompetencia(page, /Liderazgo/)

  await dialogo.getByRole('button', { name: 'Agregar pregunta' }).click()
  await dialogo.getByLabel('Enunciado de la pregunta 1').fill('Pregunta uno')
  await dialogo.getByLabel('Texto de la opción A de la pregunta 1').fill('Uno correcta')
  await dialogo.getByLabel('Texto de la opción B de la pregunta 1').fill('Uno incorrecta')
  await dialogo.getByRole('button', { name: 'Agregar pregunta' }).click()
  await dialogo.getByLabel('Enunciado de la pregunta 2').fill('Pregunta dos')
  await dialogo.getByLabel('Texto de la opción A de la pregunta 2').fill('Dos incorrecta')
  await dialogo.getByLabel('Texto de la opción B de la pregunta 2').fill('Dos correcta')
  await dialogo.getByRole('radio', { name: 'Marcar la opción B de la pregunta 2 como correcta' }).check()
  await dialogo.getByRole('button', { name: 'Subir la pregunta 2' }).click()
  await expect(dialogo.getByLabel('Enunciado de la pregunta 1')).toHaveValue('Pregunta dos')

  // Con una opción sin texto no se guarda y se señala el campo.
  await dialogo.getByRole('button', { name: 'Agregar opción' }).first().click()
  await dialogo.getByRole('button', { name: 'Guardar y publicar' }).click()
  await expect(dialogo.getByText('Cada opción necesita un texto.')).toBeVisible()
  await dialogo.getByRole('button', { name: 'Quitar la opción C de la pregunta 1' }).click()

  await dialogo.getByRole('button', { name: 'Guardar y publicar' }).click()
  await expect(dialogo).toBeHidden()
  await expect(tarjeta(page, 'E2E Quiz')).toContainText('Publicado')
  await expect(tarjeta(page, 'E2E Quiz')).toContainText('Quiz')

  // La vista previa resuelve el quiz sin registrar progreso ni intentos.
  await page.getByRole('button', { name: 'Vista previa E2E Quiz' }).click()
  const previa = page.getByRole('dialog', { name: 'Vista previa · así lo verá el colaborador' })
  await expect(previa.getByText('Pregunta 1 de 2')).toBeVisible()
  await previa.getByRole('button', { name: /Dos correcta/ }).click()
  await expect(previa.getByText('Correcto')).toBeVisible()
  await previa.getByRole('button', { name: 'Siguiente pregunta' }).click()
  await previa.getByRole('button', { name: /Uno incorrecta/ }).click()
  await expect(previa.getByText('Incorrecto')).toBeVisible()
  await previa.getByRole('button', { name: 'Ver resultado' }).click()
  await expect(previa.getByTestId('puntaje')).toHaveText('1 de 2')
  await previa.getByRole('button', { name: 'Cerrar vista previa' }).click()

  const headers = await cabeceras(page)
  const lista = await (await page.request.get(`${API}/api/microcontenidos?q=E2E Quiz`, { headers })).json()
  expect(lista.data[0].completaciones).toBe(0)
  expect(lista.data[0].puede_eliminarse).toBe(true)
})

test('Colaborador: ve su aprendizaje, retoma un video, lo completa y descarga su certificado', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  await abrirMicroLearning(page)

  // Resumen: dos completados (PDF y Quiz), uno en progreso (Video) y sus certificados.
  await expect(page.getByText('Completados', { exact: true }).locator('xpath=following-sibling::dd')).toHaveText('2')
  await expect(page.getByText('En progreso', { exact: true }).locator('xpath=following-sibling::dd')).toHaveText('1')
  await expect(page.getByText('Certificados', { exact: true }).locator('xpath=following-sibling::dd')).toHaveText('2')
  await expect(page.getByRole('tab', { name: /Asignado \(\d+\)/ })).toHaveAttribute('aria-selected', 'true')
  await expect(page.getByRole('button', { name: /Nuevo contenido|Archivar|Editar/ })).toHaveCount(0)

  // Un borrador no es accesible para el colaborador, ni siquiera por la API.
  const headers = await cabeceras(page)
  expect((await page.request.get(`${API}/api/microcontenidos/6`, { headers })).status()).toBe(404)
  expect((await page.request.post(`${API}/api/microcontenidos`, { headers, data: {} })).status()).toBe(403)
  const quiz = await (await page.request.get(`${API}/api/microcontenidos/5`, { headers })).json()
  expect(JSON.stringify(quiz)).not.toContain('es_correcta')

  // Completado: el PDF y el Quiz con su botón de certificado.
  await page.getByRole('tab', { name: 'Completado (2)' }).click()
  await expect(page).toHaveURL(/tab=completado/)
  await expect(page.getByRole('button', { name: /Descargar certificado de/ })).toHaveCount(2)
  await page.getByRole('tab', { name: /Asignado/ }).click()

  // Retoma el video donde lo dejó y lo completa.
  await page.getByRole('link', { name: 'Continuar Cómo dar y recibir retroalimentación' }).click()
  await expect(page.getByRole('heading', { name: 'Cómo dar y recibir retroalimentación', level: 1 })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Reanudar desde 1:35' })).toBeVisible()
  await page.getByRole('button', { name: 'Reanudar desde 1:35' }).click()
  await expect(page.getByText('1:37 / 8:00')).toBeVisible({ timeout: 15_000 })
  await page.getByRole('button', { name: 'Marcar como completado' }).click()
  await expect(page.getByText('Este contenido ya está completado.')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Marcar como completado' })).toHaveCount(0)

  // Certificado PDF del contenido completado.
  const [respuesta] = await Promise.all([
    page.waitForResponse((r) => r.url().includes('/certificado')),
    page.getByRole('button', { name: 'Descargar certificado' }).click(),
  ])
  expect(respuesta.status()).toBe(200)
  expect(respuesta.headers()['content-type']).toContain('application/pdf')

  // El resumen se actualiza.
  await page.getByRole('link', { name: 'Volver a Mi aprendizaje' }).click()
  await expect(page.getByText('Completados', { exact: true }).locator('xpath=following-sibling::dd')).toHaveText('3')
  await expect(page.getByText('En progreso', { exact: true }).locator('xpath=following-sibling::dd')).toHaveText('0')
})

test('Colaborador: repite un quiz y sigue Completado; el administrador no tiene la pantalla de reproducción', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)
  await abrirMicroLearning(page)

  await page.getByRole('tab', { name: /Completado/ }).click()
  await page.getByRole('link', { name: 'Ver Quiz: retroalimentación efectiva' }).click()
  await expect(page.getByText('Último puntaje: 4 de 5')).toBeVisible()

  await page.getByRole('button', { name: 'Reintentar quiz' }).click()
  for (let n = 1; n <= 5; n++) {
    await expect(page.getByText(`Pregunta ${n} de 5`)).toBeVisible()
    await page.getByRole('group', { name: 'Opciones de respuesta' }).getByRole('button').first().click()
    await expect(page.getByRole('status').filter({ hasText: /Correcto|Incorrecto/ })).toBeVisible()
    await page.getByRole('button', { name: n === 5 ? 'Ver resultado' : 'Siguiente pregunta' }).click()
  }
  await expect(page.getByTestId('puntaje')).toHaveText(/^\d de 5$/)
  await expect(page.getByText('Completaste el quiz.')).toBeVisible()
  await expect(page.getByText(/aprobad|reprobad/i)).toHaveCount(0)

  await page.getByRole('button', { name: 'Volver a Mi aprendizaje' }).click()
  await expect(page.getByText('Completados', { exact: true }).locator('xpath=following-sibling::dd')).toHaveText('3')

  // El administrador no usa la reproducción (solo la vista previa).
  await page.evaluate(() => localStorage.clear())
  await iniciarSesion(page, ADMIN.email)
  await page.goto('/micro-learning/1')
  await expect(page.getByRole('heading', { name: 'No tienes permisos para ver esta pantalla' })).toBeVisible()
})
