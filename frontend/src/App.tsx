import { useEffect } from 'react'
import { Navigate, Route, Routes, useNavigate } from 'react-router-dom'
import { AuthenticatedLayout } from './modules/auth/login/components/AuthenticatedLayout'
import { RedirectIfAuthenticated } from './modules/auth/login/components/RedirectIfAuthenticated'
import { RequireAuth } from './modules/auth/login/components/RequireAuth'
import { LoginPage } from './modules/auth/login/pages/LoginPage'
import { catalogoFormativoNav } from './modules/desarrollo/catalogo-formativo/nav'
import { CatalogoPage } from './modules/desarrollo/catalogo-formativo/pages/CatalogoPage'
import { RequireAdmin as RequireAdminDnf } from './modules/desarrollo/dnf/components/RequireAdmin'
import { dnfNav } from './modules/desarrollo/dnf/nav'
import { DnfDetallePage } from './modules/desarrollo/dnf/pages/DnfDetallePage'
import { DnfHistorialPage } from './modules/desarrollo/dnf/pages/DnfHistorialPage'
import { DnfPage } from './modules/desarrollo/dnf/pages/DnfPage'
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
import { RequireAdmin as RequireAdminPuestos } from './modules/estructura/puestos/components/RequireAdmin'
import { puestosNav } from './modules/estructura/puestos/nav'
import { PuestoFormPage } from './modules/estructura/puestos/pages/PuestoFormPage'
import { PuestosPage } from './modules/estructura/puestos/pages/PuestosPage'
import { unidadesNav } from './modules/estructura/unidades/nav'
import { UnidadesPage } from './modules/estructura/unidades/pages/UnidadesPage'
import { RequireAdmin as RequireAdminFichas } from './modules/gestionPersonal/fichas/components/RequireAdmin'
import { colaboradoresNav, miFichaNav } from './modules/gestionPersonal/fichas/nav'
import { ColaboradoresPage } from './modules/gestionPersonal/fichas/pages/ColaboradoresPage'
import { FichaDetallePage } from './modules/gestionPersonal/fichas/pages/FichaDetallePage'
import { FichaFormPage } from './modules/gestionPersonal/fichas/pages/FichaFormPage'
import { MiFichaPage } from './modules/gestionPersonal/fichas/pages/MiFichaPage'
import { inicioNav } from './modules/inicio/nav'
import { InicioPage } from './modules/inicio/pages/InicioPage'
import { onSessionExpired } from './shared/api/httpClient'
import { queryClient } from './shared/lib/queryClient'

/** Menú lateral: cada módulo aporta su ítem. */
const navItems = [inicioNav, competenciasNav, comportamientosNav, preguntasNav, puestosNav, catalogoFormativoNav, dnfNav, unidadesNav, colaboradoresNav, miFichaNav]

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
            <Route path="formacion" element={<CatalogoPage />} />
            <Route path="dnf" element={<DnfPage />} />
            <Route element={<RequireAdminDnf />}>
              <Route path="dnf/historial" element={<DnfHistorialPage />} />
              <Route path="dnf/:id" element={<DnfDetallePage />} />
            </Route>
            <Route path="puestos" element={<PuestosPage />} />
            <Route element={<RequireAdminPuestos />}>
              <Route path="puestos/nuevo" element={<PuestoFormPage />} />
              <Route path="puestos/:puestoId/editar" element={<PuestoFormPage />} />
            </Route>
            <Route path="unidades" element={<UnidadesPage />} />
            <Route path="mi-ficha" element={<MiFichaPage />} />
            <Route element={<RequireAdminFichas />}>
              <Route path="colaboradores" element={<ColaboradoresPage />} />
              <Route path="colaboradores/nuevo" element={<FichaFormPage />} />
              <Route path="colaboradores/:colaboradorId" element={<FichaDetallePage />} />
              <Route path="colaboradores/:colaboradorId/editar" element={<FichaFormPage />} />
            </Route>
          </Route>
        </Route>
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </>
  )
}

export default App
