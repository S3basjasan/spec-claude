import { useEffect } from 'react'
import { Navigate, Route, Routes, useNavigate } from 'react-router-dom'
import { AuthenticatedLayout } from './modules/auth/login/components/AuthenticatedLayout'
import { RedirectIfAuthenticated } from './modules/auth/login/components/RedirectIfAuthenticated'
import { RequireAuth } from './modules/auth/login/components/RequireAuth'
import { LoginPage } from './modules/auth/login/pages/LoginPage'
import { RequireAdmin } from './modules/estructura/competencias/components/RequireAdmin'
import { competenciasNav } from './modules/estructura/competencias/nav'
import { CompetenciaFormPage } from './modules/estructura/competencias/pages/CompetenciaFormPage'
import { CompetenciasPage } from './modules/estructura/competencias/pages/CompetenciasPage'
import { RequireAdmin as RequireAdminComportamientos } from './modules/estructura/comportamientos/components/RequireAdmin'
import { comportamientosNav } from './modules/estructura/comportamientos/nav'
import { ComportamientosFormPage } from './modules/estructura/comportamientos/pages/ComportamientosFormPage'
import { ComportamientosPage } from './modules/estructura/comportamientos/pages/ComportamientosPage'
import { RequireAdmin as RequireAdminPreguntas } from './modules/estructura/preguntas/components/RequireAdmin'
import { preguntasNav } from './modules/estructura/preguntas/nav'
import { PreguntasFormPage } from './modules/estructura/preguntas/pages/PreguntasFormPage'
import { PreguntasPage } from './modules/estructura/preguntas/pages/PreguntasPage'
import { inicioNav } from './modules/inicio/nav'
import { InicioPage } from './modules/inicio/pages/InicioPage'
import { onSessionExpired } from './shared/api/httpClient'
import { queryClient } from './shared/lib/queryClient'

/** Menú lateral: cada módulo aporta su ítem. */
const navItems = [inicioNav, competenciasNav, comportamientosNav, preguntasNav]

/** Vuelve a /login cuando el backend invalida la sesión (token revocado o inválido). */
function SessionWatcher() {
  const navigate = useNavigate()

  useEffect(
    () =>
      onSessionExpired(() => {
        queryClient.clear()
        navigate('/login', { replace: true })
      }),
    [navigate],
  )

  return null
}

function App() {
  return (
    <>
      <SessionWatcher />
      <Routes>
        <Route element={<RedirectIfAuthenticated />}>
          <Route path="/login" element={<LoginPage />} />
        </Route>
        <Route element={<RequireAuth />}>
          <Route element={<AuthenticatedLayout navItems={navItems} />}>
            <Route index element={<InicioPage />} />
            <Route path="competencias" element={<CompetenciasPage />} />
            <Route element={<RequireAdmin />}>
              <Route path="competencias/nueva" element={<CompetenciaFormPage />} />
              <Route path="competencias/:id/editar" element={<CompetenciaFormPage />} />
            </Route>
            <Route path="comportamientos" element={<ComportamientosPage />} />
            <Route element={<RequireAdminComportamientos />}>
              <Route path="comportamientos/nuevo" element={<ComportamientosFormPage />} />
              <Route path="comportamientos/:competenciaId/editar" element={<ComportamientosFormPage />} />
            </Route>
            <Route path="preguntas" element={<PreguntasPage />} />
            <Route element={<RequireAdminPreguntas />}>
              <Route path="preguntas/nuevo" element={<PreguntasFormPage />} />
              <Route path="preguntas/:competenciaId/editar" element={<PreguntasFormPage />} />
            </Route>
          </Route>
        </Route>
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </>
  )
}

export default App
