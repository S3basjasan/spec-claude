import { expect, test, type Page } from '@playwright/test'

// Credenciales de los usuarios sembrados (backend/.env.e2e, solo pruebas).
const PASSWORD = 'password-de-prueba'
const ADMIN = { email: 'admin@hrms.test', name: 'Administrador de prueba', role: 'Administrador' }
const USUARIO = { email: 'usuario@hrms.test', name: 'Usuario de prueba', role: 'Usuario' }

async function iniciarSesion(page: Page, email: string, password = PASSWORD) {
  await page.goto('/login')
  await page.getByLabel('Correo electrónico').fill(email)
  await page.getByLabel('Contraseña').fill(password)
  await page.getByRole('button', { name: 'Ingresar' }).click()
}

async function abrirMenuDeUsuario(page: Page, nombre: string) {
  await page.getByRole('button', { name: new RegExp(`Menú de usuario: ${nombre}`) }).click()
}

// Cada prueba usa un contexto de navegador nuevo: parte siempre sin sesión.

test('sin sesión, cualquier pantalla interna lleva al inicio de sesión', async ({ page }) => {
  await page.goto('/')
  await expect(page).toHaveURL(/\/login$/)
  await expect(page.getByRole('heading', { name: 'Iniciar sesión' })).toBeVisible()
})

test('el formulario vacío muestra errores por campo', async ({ page }) => {
  await page.goto('/login')
  await page.getByRole('button', { name: 'Ingresar' }).click()

  await expect(page.getByText('Ingresa tu correo electrónico.')).toBeVisible()
  await expect(page.getByText('Ingresa tu contraseña.')).toBeVisible()
})

test('credenciales incorrectas muestran un mensaje general y no entran', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email, 'incorrecta')

  await expect(page.getByRole('alert')).toHaveText(/Correo o contraseña incorrectos/)
  await expect(page).toHaveURL(/\/login$/)
  await expect(page.getByLabel('Correo electrónico')).toHaveValue(ADMIN.email)
})

test('administrador: iniciar sesión, persistir al recargar y cerrar sesión', async ({ page }) => {
  await iniciarSesion(page, `  ${ADMIN.email.toUpperCase()} `)

  await expect(page).toHaveURL(/\/$/)
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible()
  await abrirMenuDeUsuario(page, ADMIN.name)
  await expect(page.getByRole('menu').getByText(ADMIN.role, { exact: true })).toBeVisible()
  await page.keyboard.press('Escape')

  // La sesión persiste hasta cerrar sesión explícitamente.
  await page.reload()
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible()

  // Con sesión, /login lleva a la pantalla principal.
  await page.goto('/login')
  await expect(page).toHaveURL(/\/$/)

  await abrirMenuDeUsuario(page, ADMIN.name)
  await page.getByRole('menuitem', { name: 'Cerrar sesión' }).click()
  await expect(page).toHaveURL(/\/login$/)

  // Tras cerrar sesión, ni Atrás ni abrir la ruta muestran datos protegidos.
  await page.goBack()
  await expect(page.getByRole('heading', { name: 'Iniciar sesión' })).toBeVisible()
  await page.goto('/')
  await expect(page).toHaveURL(/\/login$/)
})

test('usuario de prueba: entra con rol Usuario', async ({ page }) => {
  await iniciarSesion(page, USUARIO.email)

  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible()
  await abrirMenuDeUsuario(page, USUARIO.name)
  await expect(page.getByRole('menu').getByText(USUARIO.role, { exact: true })).toBeVisible()
})

test('un token revocado devuelve a la persona al inicio de sesión', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible()

  await page.evaluate(() => localStorage.setItem('hrms.token', '999|token-invalido'))
  await page.reload()

  await expect(page).toHaveURL(/\/login$/)
})

for (const ancho of [320, 1440]) {
  test(`sin scroll horizontal a ${ancho}px en login y principal`, async ({ page }) => {
    await page.setViewportSize({ width: ancho, height: 800 })
    await page.goto('/login')
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)

    await iniciarSesion(page, ADMIN.email)
    await expect(page.getByRole('heading', { name: 'Inicio' })).toBeVisible()
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
  })
}

test('el foco es visible en los controles del formulario', async ({ page }) => {
  await page.goto('/login')
  for (const nombre of ['Correo electrónico', 'Contraseña']) {
    await page.keyboard.press('Tab')
    await expect(page.getByLabel(nombre)).toBeFocused()
    const contorno = await page.getByLabel(nombre).evaluate((el) => getComputedStyle(el).outlineWidth)
    expect(contorno).toBe('2px')
  }
})

test('el tema oscuro se aplica, persiste al recargar y no genera scroll horizontal', async ({ page }) => {
  await iniciarSesion(page, ADMIN.email)
  await page.getByRole('button', { name: 'Cambiar a tema oscuro' }).click()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')

  await page.reload()
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
  await expect(page.getByRole('button', { name: 'Cambiar a tema claro' })).toBeVisible()
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
})
