# Implementation Plan: Gestión de documentos del colaborador

**Branch**: `014-documentos-colaborador` | **Date**: 2026-10-16 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/014-documentos-colaborador/spec.md` y el insumo técnico «Gestión de documentos del colaborador» aportado por el usuario. El insumo declara que tablas y rutas son una *propuesta* que debe contrastarse con el repositorio; donde difiere de la realidad del repositorio o de la constitución prevalecen estas últimas (ver [research.md](research.md) §Divergencias con el insumo).

## Summary

Instalar la sección «Documentos» reservada por GP-01 (`capacidades.documentos`): el Administrador carga PDF a una ficha (con clasificación por el catálogo de categorías de la feature 013, visibilidad y carga idempotente), los consulta y descarga, cambia su visibilidad y los archiva; el Colaborador consulta y descarga solo los propios, visibles y disponibles con su permiso documental vigente. Los archivos viven en almacenamiento privado y solo se entregan por un endpoint autenticado que revalida el acceso en cada petición. Todo en el módulo `GestionPersonal`.

Enfoque técnico: tabla `documentos_colaborador` con metadatos, referencia opaca al objeto y auditoría en la propia fila; **publicación en dos fases con compensación** (objeto privado por intento → transacción corta que re-autoriza, bloquea la categoría activa con `CatalogoDeCategoriasPort::confirmarActiva` e inserta → limpieza del objeto del intento si falla); `StoragePort` sobre un disco privado de Laravel configurable; un validador estructural de PDF propio (sin dependencias nuevas) que no ejecuta nada del archivo; resolución de la ficha propia que exige permiso documental **sin** exigir el de ficha; comando programado que limpia objetos huérfanos de más de 24 horas; pestaña «Documentos» en `FichaContenido` y página «Mis documentos» del Colaborador.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 13 (backend); TypeScript estricto, React 19 (frontend)

**Primary Dependencies**: las existentes (Sanctum, Eloquent, Flysystem local incluido en Laravel; Vite, Tailwind 4, React Router, TanStack Query, Axios, React Hook Form, Zod). **Sin dependencias nuevas**: el adaptador S3 de Flysystem y cualquier librería de PDF requieren aprobación explícita (constitución IV) y quedan fuera; ver research D2 y D3.

**Storage**: SQLite (predeterminado de la constitución) con aislamiento por columna `tenant_id` para metadatos; archivos en un disco privado de Laravel configurable (`hrms.gestion_personal.documentos.disco`, por defecto `local` = `storage/app/private`, sin enlace público). El insumo propone PostgreSQL con esquema por tenant: no existe en el repositorio y no se introduce.

**Testing**: Pest (Feature/Unit; `Storage::fake`/`StoragePort` falso para fallos; PDFs sintéticos pequeños generados en pruebas; concurrencia determinista como en GP-03 y la feature 013), Pint; Vitest + Testing Library; Playwright (E2E ya adoptado)

**Target Platform**: Web (SPA + API REST), 320–1440 px, WCAG 2.2 AA

**Project Type**: web-service + SPA (monolito modular)

**Performance Goals**: listado por ficha en una consulta indexada con resolución de categorías en una sola lectura por lote; cargas de hasta 10 000 000 bytes; entrega por streaming sin cargar el archivo completo en memoria; percepción instantánea para búsquedas (SC-011).

**Constraints**: archivos siempre privados; entrega solo por backend autenticado con revalidación por petición; sin URL firmada ni pública; transacciones cortas (nunca se mantiene el bloqueo de categoría durante la transferencia); escrituras no reintentables automáticamente; errores públicos seguros; sin eliminación, restauración, edición ni versionado.

**Scale/Scope**: 4 historias, 34 FR, 1 entidad nueva, 6 endpoints administrativos/propios + 2 de contenido, 1 comando programado, 1 pestaña, 1 página y 3 diálogos.

## Constitution Check

*GATE: debe pasar antes de Phase 0 research. Reevaluado tras Phase 1.*

| Principio | Evaluación |
|---|---|
| I. Clean Architecture modular | Código en `backend/app/Modules/GestionPersonal/{Domain,Application,Infrastructure,Presentation}` (el insumo nombra `Modules/Personal`, que no existe; se conserva la ubicación equivalente real). Casos de uso explícitos (`CargarDocumento`, `ListarDocumentos`, `ListarMisDocumentos`, `ObtenerContenidoDocumento`, `ObtenerMiContenidoDocumento`, `CambiarVisibilidadDocumento`, `ArchivarDocumento`, `LimpiarObjetosHuerfanos`); controladores delgados; persistencia tras `DocumentoRepositoryInterface`; archivos tras `AlmacenDeDocumentosPort`; validador tras `ValidadorDePdfPort`; Eloquent y `Storage` solo en Infrastructure. **Pasa.** |
| Rutas `/api/<modulo>/` | Se mantiene la excepción ya aprobada por precedente de GP-01..03 y la feature 013: las rutas cuelgan de `/api/colaboradores/{colaborador}/…` y `/api/mi-ficha/…`. **Pasa (precedente; registrado aquí).** |
| Endpoints admin con Sanctum + `admin` | Cargar, listar, descargar, visibilidad y archivo bajo `auth:sanctum` + `admin`; las rutas propias solo `auth:sanctum` y el caso de uso exige asociación válida y permiso documental (precedente `mi-ficha`). Las capacidades `personal.documentos.gestionar` y `personal.documentos_propios.consultar` del insumo se implementan con el middleware `admin` y con `colaborador_cuentas.puede_consultar_documentos` respectivamente; no existe otro sistema de permisos. **Pasa.** |
| Módulo no accede a tablas de otro módulo | Solo tablas de `GestionPersonal` (`documentos_colaborador`, `colaboradores`, `colaborador_cuentas`, `categorias_documentales`, todas del mismo módulo). **Pasa.** |
| II. Frontend | `frontend/src/modules/gestionPersonal/documentos/` (api, hooks, components, pages, validation, text); páginas sin Axios (la descarga con Bearer usa el cliente HTTP compartido con blob); TanStack Query, escrituras sin reintento; búsqueda, filtro y página en la URL; loading/empty/error/success; formularios RHF+Zod con errores por campo y botón bloqueado; confirmación accesible. La categoría se consume solo por `categorias/index.ts`. **Pasa.** |
| III. Diseño | Sigue `design/GP-05_Documentos-del-colaborador.html` y `DESIGN.md`. Diferencias a registrar en `DESIGN.md` §9 como tarea. **Pasa.** |
| IV. Calidad / dependencias | Pest + Pint, ESLint + tsc + Vitest + build, E2E del flujo principal; **sin dependencias nuevas** (no S3, no librería PDF); sin secretos ni documentos reales (PDFs sintéticos). **Pasa.** |

Sin violaciones: no se completa «Complexity Tracking».

## Project Structure

### Documentation (this feature)

```text
specs/014-documentos-colaborador/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── api-documentos.md
│   └── puertos.md
├── checklists/requirements.md
└── tasks.md            # /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/GestionPersonal/
│   ├── Domain/
│   │   ├── Entities/DocumentoColaborador.php
│   │   ├── ValueObjects/EstadoDeDocumento.php
│   │   ├── Repositories/DocumentoRepositoryInterface.php
│   │   ├── Services/ReglasDeDocumento.php
│   │   └── Exceptions/{DocumentoNoEncontrado,DocumentoModificado,ArchivoInvalido,AlmacenamientoNoDisponible}.php   # CategoriaInactiva, ClaveDeOperacionRequerida/Reutilizada, DatosInvalidos, EscrituraDeshabilitada, CuentaSinFicha, SinPermisoDeFicha (reutilizable) se reutilizan
│   ├── Application/
│   │   ├── DTO/{CargaDeDocumentoDTO,FiltroDocumentosDTO,ListadoDocumentosDTO,ContenidoDeDocumentoDTO}.php
│   │   ├── Ports/{AlmacenDeDocumentosPort,ValidadorDePdfPort}.php
│   │   ├── Services/PublicadorDeDocumento.php
│   │   └── UseCases/{CargarDocumento,ListarDocumentos,ListarMisDocumentos,ObtenerContenidoDocumento,ObtenerMiContenidoDocumento,CambiarVisibilidadDocumento,ArchivarDocumento,ResolverFichaDocumentalPropia,LimpiarObjetosHuerfanos}.php
│   ├── Infrastructure/
│   │   ├── Adapters/{AlmacenDeDocumentosDisco,ValidadorDePdfEstructural}.php
│   │   ├── Console/LimpiarObjetosHuerfanosCommand.php
│   │   └── Persistence/Eloquent/{Models/DocumentoColaboradorModel.php,Repositories/EloquentDocumentoRepository.php}
│   └── Presentation/
│       ├── Controllers/{DocumentoColaboradorController,MiDocumentoController}.php
│       ├── Middleware/LimitarTamanoDeCarga.php
│       ├── Requests/{CargarDocumentoRequest,ListarDocumentosRequest,CambiarVisibilidadRequest,ArchivarDocumentoRequest}.php
│       ├── Resources/{DocumentoResource,MiDocumentoResource}.php
│       └── (Routes/api.php ampliado; Providers: bindings; LectorDeCapacidades: instalada 'documentos')
├── config/hrms.php (gestion_personal.documentos: disco, max_bytes, huerfanos_horas, habilitado)
├── routes/console.php (programación del comando de compensación)
├── database/migrations/2026_10_19_000001_create_documentos_colaborador_table.php
├── database/{factories/DocumentoColaboradorFactory.php,seeders/PersonalSeeder.php}   # PDFs sintéticos
└── tests/{Feature,Unit}/GestionPersonal/ (Documento*Test.php)

frontend/
├── src/modules/gestionPersonal/documentos/
│   ├── api.ts, text.ts, validation.ts (+ test), nav.tsx, index.ts
│   ├── hooks/{useDocumentos,useMisDocumentos,useDocumentosParams,useGuardarDocumento,useAbrirDocumento}.ts
│   ├── components/{SeccionDocumentos,DocumentosTabla,FormularioDocumento,ConfirmarArchivo}.tsx
│   └── pages/MisDocumentosPage.tsx
├── src/modules/gestionPersonal/fichas/components/FichaContenido.tsx   # panel «Documentos»
├── src/App.tsx            # ruta /mis-documentos y entrada de navegación del Colaborador
└── e2e/documentos.spec.ts
design/DESIGN.md (§9 diferencias)
```

**Structure Decision**: aplicación web existente (backend + frontend); la funcionalidad es una carpeta nueva por capa dentro de `GestionPersonal`/`gestionPersonal`. Las ediciones de código de otras funcionalidades se limitan al panel de `FichaContenido`, el binding y la capacidad `documentos` en el proveedor y el lector de capacidades, el semillero, `routes/console.php`, `config/hrms.php` y `App.tsx`.

## Decisiones clave (detalle en research.md)

1. **Publicación en dos fases con compensación**: objeto privado por intento → transacción corta (re-autorizar, `confirmarActiva` de la categoría, insertar) → si falla, borrar solo el objeto de ese intento; si el borrado falla, registrar en el log técnico y dejar la limpieza al barrido periódico. Un documento solo existe como fila una vez publicado: no hay estado «Subiendo».
2. **Carga idempotente** por `Idempotency-Key` UUID (patrón de GP-01): `operacion_id` único por tenant y `operacion_hash` con campos normalizados, ficha, actor y SHA-256 de los bytes; misma clave con otro contenido ⇒ 409; repetición válida ⇒ 200 con el documento ya publicado, sin subir otra copia.
3. **Almacenamiento privado sin dependencias nuevas**: `AlmacenDeDocumentosPort` sobre un disco de Laravel configurable (por defecto `local` privado). El adaptador S3 requiere `league/flysystem-aws-s3-v3`, que no está instalado: queda como cambio de configuración condicionado a aprobación de la dependencia. Clave opaca `personal/{tenant_id}/colaboradores/{colaborador_id}/{uuid-aleatorio}.pdf`, nunca derivada del nombre del cliente ni reutilizada por otro intento.
4. **Validador de PDF estructural propio** (`ValidadorDePdfEstructural`), sin ejecutar ni renderizar nada: tipo por contenido (`fileinfo`), cabecera `%PDF-`, marcador `%%EOF`, `startxref` con desplazamiento válido, raíz del catálogo y al menos un objeto. Distingue **inválido** (422) de **no verificable por limitación técnica** (503).
5. **Acceso propio independiente**: `ResolverFichaDocumentalPropia` exige asociación válida y `puede_consultar_documentos`, **sin** exigir `puede_consultar_ficha` (el existente `ResolverFichaPropia` sí lo exige y no sirve aquí). Cada apertura y descarga revalida cuenta, ficha, permiso, visibilidad y estado.
6. **Entrega solo por backend autenticado**: `GET …/contenido` en streaming con `Content-Type: application/pdf`, `X-Content-Type-Options: nosniff`, `Cache-Control: private, no-store` y nombre seguro `Documento_{id}.pdf`; el frontend lo obtiene con el cliente compartido como blob (el Bearer no viaja en un enlace). Sin URL firmada ni pública.
7. **Límite de tamaño**: 10 000 000 bytes exactos comprobados en servidor; middleware que traduce el rechazo previo de PHP/proxy (cuerpo más grande que `post_max_size`) a 422/413 `ARCHIVO_DEMASIADO_GRANDE`, nunca a un éxito; configuración de PHP por encima del multipart necesario documentada en el quickstart.
8. **Estado y visibilidad versionados**: `PATCH …/visibilidad` y `PATCH …/archivo` con `version`; solo sobre documentos Disponibles (un archivado ⇒ 409 incompatible); mismo valor con versión vigente ⇒ 200 sin escribir; archivar registra `archived_by/at` y nunca se revierte.
9. **404 uniforme** para documento inexistente, de otra ficha o de otro tenant, y para el Colaborador ante un documento privado, archivado o ajeno; 403 al Colaborador en rutas de administración.
10. **Sin eliminación en ningún camino**, salvo el barrido técnico de objetos **no referenciados** de más de 24 horas, que nunca toca documentos publicados ni archivados.

## Diseño de frontend

### Rutas
| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/colaboradores/:colaboradorId` (pestaña «Documentos») | Documentos de la ficha (con controles) | Administrador |
| `/mi-ficha` (pestaña «Documentos», si la cuenta tiene permiso de ficha) y `/mis-documentos` | Mis documentos (solo lectura) | Colaborador con permiso documental |

La página `/mis-documentos` existe porque el permiso documental es independiente del de ficha: un Colaborador con solo permiso documental no puede abrir Mi ficha. Ambas vistas reutilizan `SeccionDocumentos`.

### Componentes
- **Nuevos**: `SeccionDocumentos` (lista, filtros, estados, composición), `DocumentosTabla`, `FormularioDocumento` (diálogo de carga), `ConfirmarArchivo`, `MisDocumentosPage`.
- **Del design system**: `Button`, `Badge`, `Modal`, `ConfirmDialog`, `TextField`, `SelectField`, `SearchField`, `Pagination`, `EmptyState`, `ErrorState`, `Skeleton`, `Alert`; selector de categoría activa mediante `useCategoriasActivas` de `categorias/index.ts`.

### Datos y estado
- Endpoints (ver [contracts/api-documentos.md](contracts/api-documentos.md)): `GET`/`POST …/documentos`, `GET …/contenido`, `PATCH …/visibilidad`, `PATCH …/archivo` y sus equivalentes `GET /api/mi-ficha/documentos` y `…/contenido`.
- Claves `['documentos', colaboradorId, …]` y `['mi-ficha', 'documentos', …]`; tras cargar, cambiar visibilidad o archivar se invalidan; **sin actualización optimista** de visibilidad ni archivo.
- Estado en URL: `q`, `categoria_id`, `estado` (`Disponible` por defecto, `Archivado` solo Administrador) y `page`.
- La `Idempotency-Key` se genera al abrir el diálogo y se conserva hasta resolver (éxito o cierre); un reintento tras fallo de red reutiliza la misma clave.
- Abrir/descargar: la petición del blob usa el cliente compartido; «Abrir» muestra el PDF en una pestaña con el blob local y «Descargar» fuerza `descarga=1` con el nombre seguro; ante 403/404 se informa sin mostrar contenido.

### Validación
- Zod replica título obligatorio tras recortar y máximo 200, categoría obligatoria, archivo obligatorio, `.pdf`, no vacío y máximo 10 000 000 bytes; el servidor es la autoridad por contenido.
- Mapeo de errores: 422 por campo (`titulo`, `categoria_id`, `archivo`) junto al campo; 409 de categoría desactivada («La categoría ya no está disponible»); 413 como tamaño excedido; 503 como fallo del almacenamiento; título y categoría se conservan y se indica si hay que volver a elegir el archivo.
