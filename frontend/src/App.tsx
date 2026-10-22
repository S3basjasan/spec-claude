import { useEffect } from 'react'
import { Navigate, Route, Routes, useNavigate } from 'react-router-dom'
import { AuthenticatedLayout } from './modules/auth/login/components/AuthenticatedLayout'
import { RedirectIfAuthenticated } from './modules/auth/login/components/RedirectIfAuthenticated'
import { RequireAuth } from './modules/auth/login/components/RequireAuth'
import { LoginPage } from './modules/auth/login/pages/LoginPage'
import { categoriasReconocimientoNav } from './modules/compensaciones/categorias/nav'
import { CategoriasReconocimientoPage } from './modules/compensaciones/categorias/pages/CategoriasReconocimientoPage'
import { RequireRole as RequireRoleActividades } from './modules/desarrollo/actividades/components/RequireRole'
import { actividadesNav } from './modules/desarrollo/actividades/nav'

import { ActividadesPage } from './modules/desarrollo/actividades/pages/ActividadesPage'
import { AsistenciaPage } from './modules/desarrollo/actividades/pages/AsistenciaPage'
import { catalogoFormativoNav } from './modules/desarrollo/catalogo-formativo/nav'
import { CatalogoPage } from './modules/desarrollo/catalogo-formativo/pages/CatalogoPage'
import { RequireRole as RequireRoleMicro } from './modules/desarrollo/micro-learning/components/RequireRole'
import { microLearningNav } from './modules/desarrollo/micro-learning/nav'
import { MicroLearningPage } from './modules/desarrollo/micro-learning/pages/MicroLearningPage'
import { reportesNav } from './modules/desarrollo/reportes/nav'
import { ReportesPage } from './modules/desarrollo/reportes/pages/ReportesPage'
import { RequireRole as RequireRoleRutas } from './modules/desarrollo/rutas/components/RequireRole'
import { rutasNav } from './modules/desarrollo/rutas/nav'
import { RutaEditorPage } from './modules/desarrollo/rutas/pages/RutaEditorPage'
import { RutasPage } from './modules/desarrollo/rutas/pages/RutasPage'
import { ReproduccionPage } from './modules/desarrollo/micro-learning/pages/ReproduccionPage'
import { RequireAdmin as RequireAdminDnf } from './modules/desarrollo/dnf/components/RequireAdmin'
import { dnfNav } from './modules/desarrollo/dnf/nav'
import { DnfDetallePage } from './modules/desarrollo/dnf/pages/DnfDetallePage'
import { DnfHistorialPage } from './modules/desarrollo/dnf/pages/DnfHistorialPage'
import { DnfPage } from './modules/desarrollo/dnf/pages/DnfPage'
import { RequireAdmin as RequireAdminPlan } from './modules/desarrollo/plan-formacion/components/RequireAdmin'
import { planFormacionNav } from './modules/desarrollo/plan-formacion/nav'
import { AccionesPage } from './modules/desarrollo/plan-formacion/pages/AccionesPage'
import { AsistentePlanPage } from './modules/desarrollo/plan-formacion/pages/AsistentePlanPage'
import { PlanDetallePage } from './modules/desarrollo/plan-formacion/pages/PlanDetallePage'
import { PlanFormacionPage } from './modules/desarrollo/plan-formacion/pages/PlanFormacionPage'
import { competenciasNav } from './modules/estructura/competencias/nav'
import { CompetenciasPage } from './modules/estructura/competencias/pages/CompetenciasPage'
import { RequireAdmin as RequireAdminPuestos } from './modules/estructura/puestos/components/RequireAdmin'
import { puestosNav } from './modules/estructura/puestos/nav'
import { PuestoFormPage } from './modules/estructura/puestos/pages/PuestoFormPage'
import { PuestosPage } from './modules/estructura/puestos/pages/PuestosPage'
import { organigramaNav } from './modules/estructura/organigrama/nav'
import { OrganigramaPage } from './modules/estructura/organigrama/pages/OrganigramaPage'
import { posicionesNav } from './modules/estructura/posiciones/nav'
import { PosicionesPage } from './modules/estructura/posiciones/pages/PosicionesPage'
import { unidadesNav } from './modules/estructura/unidades/nav'
import { UnidadesPage } from './modules/estructura/unidades/pages/UnidadesPage'
import { RequireAdmin as RequireAdminFichas } from './modules/gestionPersonal/fichas/components/RequireAdmin'
import { categoriasNav } from './modules/gestionPersonal/categorias/nav'
import { CategoriasPage } from './modules/gestionPersonal/categorias/pages/CategoriasPage'
import { exportarMiFichaNav } from './modules/gestionPersonal/exportacion/nav'
import { MiExportacionPage } from './modules/gestionPersonal/exportacion/pages/MiExportacionPage'
import { misDocumentosNav } from './modules/gestionPersonal/documentos/nav'
import { MisDocumentosPage } from './modules/gestionPersonal/documentos/pages/MisDocumentosPage'
import { colaboradoresNav, miFichaNav } from './modules/gestionPersonal/fichas/nav'
import { ColaboradoresPage } from './modules/gestionPersonal/fichas/pages/ColaboradoresPage'
import { FichaDetallePage } from './modules/gestionPersonal/fichas/pages/FichaDetallePage'
import { FichaFormPage } from './modules/gestionPersonal/fichas/pages/FichaFormPage'
import { MiFichaPage } from './modules/gestionPersonal/fichas/pages/MiFichaPage'
import { RegistrarIngresoPage } from './modules/gestionPersonal/fichas/pages/RegistrarIngresoPage'
import { inicioNav } from './modules/inicio/nav'
import { InicioPage } from './modules/inicio/pages/InicioPage'
import { onSessionExpired } from './shared/api/httpClient'
import { queryClient } from './shared/lib/queryClient'

/** Menú lateral: cada módulo aporta su ítem. */
const navItems = [inicioNav, competenciasNav, puestosNav, posicionesNav, catalogoFormativoNav, dnfNav, planFormacionNav, categoriasReconocimientoNav, actividadesNav, reportesNav, rutasNav, microLearningNav, unidadesNav, organigramaNav, colaboradoresNav, categoriasNav, miFichaNav, misDocumentosNav, exportarMiFichaNav]

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
            <Route path="formacion" element={<CatalogoPage />} />
            <Route path="dnf" element={<DnfPage />} />
            <Route element={<RequireAdminDnf />}>
              <Route path="dnf/historial" element={<DnfHistorialPage />} />
              <Route path="dnf/:id" element={<DnfDetallePage />} />
            </Route>
            <Route path="plan-formacion" element={<PlanFormacionPage />} />
            <Route element={<RequireAdminPlan />}>
              <Route path="plan-formacion/acciones" element={<AccionesPage />} />
              <Route path="plan-formacion/nuevo" element={<AsistentePlanPage />} />
              <Route path="plan-formacion/:id/editar" element={<AsistentePlanPage />} />
              <Route path="plan-formacion/:id" element={<PlanDetallePage />} />
            </Route>
            <Route path="actividades" element={<ActividadesPage />} />
            <Route path="actividades/:id/asistencia" element={<RequireRoleActividades admin={<AsistenciaPage />} />} />
            <Route path="reportes" element={<ReportesPage />} />
            <Route path="rutas" element={<RutasPage />} />
            <Route path="rutas/:id" element={<RequireRoleRutas admin={<RutaEditorPage />} />} />
            <Route path="micro-learning" element={<MicroLearningPage />} />
            <Route path="micro-learning/:id" element={<RequireRoleMicro colaborador={<ReproduccionPage />} />} />
            <Route path="puestos" element={<PuestosPage />} />
            <Route element={<RequireAdminPuestos />}>
              <Route path="puestos/nuevo" element={<PuestoFormPage />} />
              <Route path="puestos/:puestoId/editar" element={<PuestoFormPage />} />
            </Route>
            <Route path="compensaciones/categorias" element={<CategoriasReconocimientoPage />} />
            <Route path="unidades" element={<UnidadesPage />} />
            <Route path="posiciones" element={<PosicionesPage />} />
            <Route path="organigrama" element={<OrganigramaPage />} />
            <Route path="mi-ficha" element={<MiFichaPage />} />
            <Route path="mis-documentos" element={<MisDocumentosPage />} />
            <Route path="exportar-mi-ficha" element={<MiExportacionPage />} />
            <Route element={<RequireAdminFichas />}>
              <Route path="colaboradores" element={<ColaboradoresPage />} />
              <Route path="colaboradores/nuevo" element={<FichaFormPage />} />
              <Route path="colaboradores/:colaboradorId" element={<FichaDetallePage />} />
              <Route path="colaboradores/:colaboradorId/editar" element={<FichaFormPage />} />
              <Route path="colaboradores/:colaboradorId/ingreso" element={<RegistrarIngresoPage />} />
              <Route path="categorias-documentales" element={<CategoriasPage />} />
            </Route>
          </Route>
        </Route>
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </>
  )
}

export default App
