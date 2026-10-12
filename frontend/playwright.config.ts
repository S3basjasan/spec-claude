import { defineConfig } from '@playwright/test'

const API_PORT = 8001
const WEB_PORT = 5174

// E2E real: backend Laravel con base SQLite propia (backend/.env.e2e, sembrada desde cero)
// y frontend Vite apuntando a ese backend. Usa el Edge ya instalado (sin descargar navegadores).
export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  reporter: 'list',
  use: {
    baseURL: `http://127.0.0.1:${WEB_PORT}`,
    channel: 'msedge',
    locale: 'es-EC',
    trace: 'retain-on-failure',
  },
  webServer: [
    {
      command: `php artisan migrate:fresh --seed --force --env=e2e && php artisan serve --host=127.0.0.1 --port=${API_PORT} --env=e2e`,
      cwd: '../backend',
      url: `http://127.0.0.1:${API_PORT}/up`,
      reuseExistingServer: false,
      timeout: 120_000,
    },
    {
      command: `npm run dev -- --host 127.0.0.1 --port ${WEB_PORT} --strictPort`,
      url: `http://127.0.0.1:${WEB_PORT}`,
      reuseExistingServer: false,
      timeout: 120_000,
      env: { VITE_API_URL: `http://127.0.0.1:${API_PORT}` },
    },
  ],
})
