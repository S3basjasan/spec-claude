import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vitest/config'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  test: {
    // Lógica pura en Node; las pruebas de componentes (*.dom.test.tsx) declaran jsdom con `@vitest-environment`.
    environment: 'node',
    include: ['src/**/*.test.{ts,tsx}'],
    exclude: ['node_modules', 'dist', 'e2e'],
    setupFiles: ['src/test/setup.ts'],
  },
})
