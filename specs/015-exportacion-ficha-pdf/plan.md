# Implementation Plan: Exportación individual de ficha integral

**Branch**: `015-exportacion-ficha-pdf` | **Date**: 2026-10-16 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/015-exportacion-ficha-pdf/spec.md` y el insumo técnico «Exportación individual de ficha integral» aportado por el usuario. El insumo declara que tablas y rutas son una *propuesta* que debe contrastarse con el repositorio; donde difiere de la realidad del repositorio o de la constitución prevalecen estas últimas (ver [research.md](research.md) §Divergencias con el insumo).

## Summary

Instalar la acción «Exportar ficha» reservada por GP-01 (`capacidades.exportar`): un `GET` autenticado que reúne, en una lectura coherente, los datos guardados de una ficha (identificación, datos personales, situación y trayectoria laborales, formación, experiencia y movilidad), los pasa como una instantánea inmutable a un renderizador de PDF, vuelve a autorizar justo antes de entregar y responde el archivo completo o un error JSON, nunca un archivo parcial. El Administrador exporta cualquier ficha de su tenant; el Colaborador solo la propia con el permiso de exportación, **independiente** de los de ficha y documentos. No hay tabla, historial ni almacenamiento del PDF.

Enfoque técnico: caso de uso `GenerarFichaPdf` que (1) autoriza, (2) arma `SnapshotDeFichaDTO` dentro de una transacción de lectura de solo lectura sobre la conexión por defecto (`ContextoDeLecturaPort`), reutilizando los casos de uso y lectores existentes y tratando cualquier fallo de una fuente como `ExportacionNoDisponible` (503), (3) renderiza en memoria con `RenderizadorDePdfPort` (dompdf, aprobado y encapsulado tras el puerto), (4) valida que el PDF sea completo, (5) revalida permisos con lecturas nuevas y (6) entrega con cabeceras seguras. Frontend: botón «Exportar ficha» con descarga por blob validada y página «Exportar mi ficha» para el Colaborador.

## Technical Context

**Language/Version**: PHP 8.3 (`composer.json`; entorno local 8.5.5) / Laravel 13 (backend); TypeScript estricto, React 19 (frontend)

**Primary Dependencies**: las existentes (Sanctum, Eloquent; Vite, Tailwind 4, React Router, TanStack Query, Axios, React Hook Form, Zod). **Dependencia nueva aprobada explícitamente por el usuario el 2026-10-16 (constitución IV): `dompdf/dompdf`** (resuelve en `v3.1.6` con `dompdf/php-font-lib`, `dompdf/php-svg-lib`, `masterminds/html5` y `sabberworm/php-css-parser`; verificado con `composer require --dry-run` sin modificar `composer.json` ni `composer.lock`). No existe ningún generador de PDF instalado que reutilizar. Extensiones requeridas presentes en el entorno: `dom`, `mbstring`; `gd` no está habilitada y no hace falta (el PDF no lleva imágenes). La contingencia de un generador propio detrás del mismo puerto (research D2) queda descartada salvo que la prueba de renderizado con PHP 8.5 revele una incompatibilidad.

**Storage**: SQLite (predeterminado de la constitución), solo lectura; **no se escribe ninguna tabla ni archivo**. El PDF se genera en memoria y no se persiste. El insumo propone PostgreSQL con esquema por tenant: no existe en el repositorio.

**Testing**: Pest (Feature/Unit; lectores y renderizador falsos para fallos; renderizado real de dompdf con acentos, listas de varias páginas y texto largo), Pint; Vitest + Testing Library; Playwright (E2E ya adoptado)

**Target Platform**: Web (SPA + API REST), 320–1440 px, WCAG 2.2 AA

**Project Type**: web-service + SPA (monolito modular)

**Performance Goals**: PDF de una ficha completa entregado en menos de 10 segundos (SC-001) medido en el entorno real; sin tope artificial de formaciones o experiencias; los límites de memoria y tiempo se miden, no se suponen.

**Constraints**: sin modificar datos; autorización al solicitar y justo antes de entregar; sin URL pública ni permanente; sin adjuntos, enlaces, información financiera ni indicadores calculados; sin servicio externo que reciba datos personales; ninguna fuente fallida se disfraza de sección vacía; cabeceras `application/pdf`, `Cache-Control: private, no-store`, `X-Content-Type-Options: nosniff`.

**Scale/Scope**: 3 historias, 24 FR, 0 entidades persistentes, 2 endpoints `GET`, 1 DTO de instantánea, 1 plantilla, 1 botón y 1 página.

## Constitution Check

*GATE: debe pasar antes de Phase 0 research. Reevaluado tras Phase 1.*

| Principio | Evaluación |
|---|---|
| I. Clean Architecture modular | Código en `backend/app/Modules/GestionPersonal/{Domain,Application,Infrastructure,Presentation}` (el insumo nombra `Modules/Personal`, que no existe). Casos de uso explícitos (`GenerarFichaPdf`, `ArmarSnapshotDeFicha`); controladores delgados sin consultas; dominio sin Laravel; el renderizador y la transacción de lectura detrás de puertos de Aplicación; dompdf y `DB` solo en Infrastructure. **Pasa.** |
| Rutas `/api/<modulo>/` | Se mantiene la excepción ya aprobada por precedente (`/api/colaboradores`, `/api/mi-ficha`, GP-01..03, 013 y 014): `…/exportacion.pdf` cuelga de esos prefijos. **Pasa (precedente; registrado aquí).** |
| Endpoints admin con Sanctum + `admin` | La ruta administrativa va bajo `auth:sanctum` + `admin`; la propia solo `auth:sanctum` y el caso de uso exige asociación y permiso de exportación (precedente `mi-ficha`). Las capacidades `personal.fichas.exportar` y `personal.ficha_propia.exportar` del insumo se implementan con `admin` y `colaborador_cuentas.puede_exportar`; no existe otro sistema de permisos. **Pasa.** |
| Módulo no accede a tablas de otro módulo | Los datos se obtienen por los casos de uso, repositorios y puertos del propio módulo (`LectorEstructuraPort` hacia Estructura, ya existente). El renderizador solo recibe el DTO. **Pasa.** |
| II. Frontend | `frontend/src/modules/gestionPersonal/exportacion/` (api, hooks, components, pages, text); páginas sin Axios (la descarga con Bearer usa el cliente HTTP compartido con blob); mutación sin reintento; loading/éxito/error. **Pasa.** |
| III. Diseño | Sigue `design/GP-06_Exportar-ficha-PDF.html` y `DESIGN.md`; diferencias a registrar en `DESIGN.md` §9 como tarea. **Pasa.** |
| IV. Calidad / dependencias | Pest + Pint, ESLint + tsc + Vitest + build y E2E. **`dompdf/dompdf` no está preaprobada en la constitución; el usuario la aprobó explícitamente el 2026-10-16 y se instala en la primera tarea.** No se registran datos personales reales en pruebas (fichas sintéticas). **Pasa.** |

Sin violaciones ni condiciones abiertas.

## Project Structure

### Documentation (this feature)

```text
specs/015-exportacion-ficha-pdf/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── api-exportacion.md
│   └── puertos.md
├── checklists/requirements.md
└── tasks.md            # /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/GestionPersonal/
│   ├── Domain/
│   │   ├── Exceptions/{ExportacionNoDisponible,SinPermisoDeExportacion}.php   # ColaboradorNoEncontrado, CuentaSinFicha se reutilizan
│   │   └── Services/EtiquetasDeExportacion.php                                 # «No registrado», «No informada», secciones vacías (PHP puro)
│   ├── Application/
│   │   ├── DTO/SnapshotDeFichaDTO.php (+ sub-DTO de secciones)
│   │   ├── Ports/{ContextoDeLecturaPort,RenderizadorDePdfPort}.php
│   │   ├── Services/{ArmadorDeSnapshotDeFicha,AutorizadorDeExportacion}.php
│   │   └── UseCases/{GenerarFichaPdf,GenerarMiFichaPdf,ResolverFichaExportablePropia}.php
│   ├── Infrastructure/
│   │   ├── Adapters/{ContextoDeLecturaSnapshot,RenderizadorDePdfDompdf}.php
│   │   └── Pdf/plantillas/ficha.blade.php
│   └── Presentation/
│       └── Controllers/{ExportacionFichaController,MiExportacionController}.php
├── config/hrms.php (gestion_personal.exportacion_habilitada)
├── composer.json / composer.lock  (dompdf aprobado)
└── tests/{Feature,Unit}/GestionPersonal/ (Exportacion*Test.php)

frontend/
├── src/modules/gestionPersonal/exportacion/
│   ├── api.ts, text.ts, nav.tsx, index.ts
│   ├── hooks/useExportarFicha.ts
│   ├── components/BotonExportarFicha.tsx
│   └── pages/MiExportacionPage.tsx
├── src/modules/gestionPersonal/fichas/pages/{FichaDetallePage,MiFichaPage}.tsx   # botón en el encabezado
├── src/App.tsx            # ruta /exportar-mi-ficha y entrada de navegación del Colaborador
└── e2e/exportacion.spec.ts
design/DESIGN.md (§9 diferencias)
```

**Structure Decision**: aplicación web existente (backend + frontend); la funcionalidad es una carpeta nueva por capa dentro de `GestionPersonal`/`gestionPersonal`. Las ediciones en otras funcionalidades se limitan a: `ConsultarTrayectoria` (resultado con disponibilidad, ver research D4), el proveedor y el lector de capacidades (`exportar` instalada), las dos páginas de ficha, `routes`, `config/hrms.php` y `App.tsx`.

## Decisiones clave (detalle en research.md)

1. **Una petición, una generación, entrega inmediata**: `GET` que genera y responde en el mismo ciclo; sin PDF almacenado, sin tabla, sin `Idempotency-Key`, sin dirección intermedia. «Autorización al solicitar y al entregar» se cumple con una comprobación inicial y otra, con lecturas nuevas, justo antes de enviar el primer byte.
2. **Instantánea coherente**: una transacción de lectura sobre la conexión por defecto (donde ya leen ficha, vínculos, desarrollo y el lector de Estructura) que se cierra antes de renderizar. En SQLite una transacción de lectura da una vista estable; en un motor con aislamiento configurable se fija `REPEATABLE READ READ ONLY`. Se verifica con una prueba que todos los lectores usan esa misma conexión.
3. **Un fallo no es un dato ausente**: las etiquetas «No registrado» / «No informada» se aplican solo cuando la lectura confirma la ausencia; cualquier fallo de fuente (incluida Estructura, que hoy los casos de uso degradan a `no_disponible`) lanza `ExportacionNoDisponible` ⇒ 503 antes de renderizar.
4. **Renderizador detrás de un puerto**: dompdf (propuesto) con `isRemoteEnabled=false`, `isPhpEnabled=false`, JavaScript desactivado, raíz de archivos restringida (`chroot`) a la carpeta de plantillas y fuente DejaVu local; el contenido de usuario se escapa como texto. Sin aprobación, un generador propio de PDF implementa el mismo puerto.
5. **PDF en memoria, sin temporal**: se obtiene la cadena completa, se comprueba que no esté vacía, que empiece por `%PDF-` y termine con `%%EOF` y, solo entonces, se revalida el permiso y se responde. Un error produce JSON antes de enviar ningún byte; no hay archivo parcial ni temporal que limpiar.
6. **Acceso propio independiente**: `ResolverFichaExportablePropia` exige asociación válida y `puede_exportar`, sin exigir `puede_consultar_ficha` ni `puede_consultar_documentos`.
7. **Sin escrituras ni efectos**: `GET` puro; no crea vínculos, no toca documentos, no agrega el PDF al expediente.
8. **404 uniforme** para ficha inexistente, de otro tenant o ajena; 403 al Colaborador en la ruta administrativa y por falta del permiso en la propia; 503 `EXPORTACION_NO_DISPONIBLE` para fallos de fuente o de render.

## Diseño de frontend

### Rutas
| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/colaboradores/:colaboradorId` (encabezado) | Acción «Exportar ficha» | Administrador |
| `/mi-ficha` (encabezado, si `capacidades.exportar`) y `/exportar-mi-ficha` | Acción «Exportar mi ficha» | Colaborador con permiso de exportación |

`/exportar-mi-ficha` existe porque el permiso de exportación es independiente del de ficha: un Colaborador con solo ese permiso no puede abrir Mi ficha.

### Componentes
- **Nuevos**: `BotonExportarFicha` (progreso, bloqueo mientras dura la petición, éxito y error con Reintentar), `MiExportacionPage`.
- **Del design system**: `Button`, `Alert`, `EmptyState`, `ErrorState`, `Skeleton`.

### Datos y estado
- Un `GET` por exportación (ver [contracts/api-exportacion.md](contracts/api-exportacion.md)); sin caché ni invalidación: es una lectura puntual.
- Se obtiene el blob con el cliente compartido; antes de crear el enlace se comprueba el estado y que `Content-Type` sea `application/pdf`; un error JSON recibido como blob se lee para mostrar su mensaje y nunca se descarga como `.pdf`; el `object URL` se libera tras descargar. Nombre: el de `Content-Disposition` (`Ficha_EMP-###.pdf`).
- Una petición en curso bloquea el botón (el doble clic no lanza otra). Al recibir 403, la acción pasa a «sin permiso». Los cambios sin guardar de un formulario no viajan: el servidor lee solo lo guardado.

### Validación
- No hay formulario. Mapeo de errores: 403 «No tienes permiso para exportar esta ficha»; 404 «La ficha no existe»; `CUENTA_SIN_FICHA` «Tu cuenta no tiene una ficha asociada»; 503 «No se pudo generar la ficha. Inténtalo de nuevo»; red «No se pudo conectar». Nunca se afirma que el PDF se agregó a Documentos.
