# Implementation Plan: Categorías e indicadores cualitativos de reconocimiento

**Branch**: `016-categorias-reconocimiento` | **Date**: 2026-10-16 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/016-categorias-reconocimiento/spec.md` y el insumo técnico `plan-categorias-reconocimiento-v2.md` aportado por el usuario. Donde el insumo difiere de la realidad del repositorio o de la constitución prevalecen estas últimas (ver [research.md](research.md) §Divergencias con el insumo).

## Summary

Crear el catálogo maestro de categorías de reconocimiento con 1 a 5 indicadores cualitativos cada una, vinculadas a una competencia del Diccionario de Competencias. El Administrador crea, edita, activa y desactiva (nunca elimina); los demás roles consultan. El módulo `Compensaciones` (nuevo) expone además una Facade pública con las categorías seleccionables (Activas con competencia activa) para Desafíos, Hitos y Ranking.

Enfoque técnico: módulo backend `Compensaciones` con Clean Architecture (entidad `Categoria`, objetos de valor `NombreCategoria`, `PuntuacionBase`, `Icono`, `Indicador`; casos de uso explícitos; puertos `CategoriaRepository` y `CompetenciasPort` con adaptador sobre `EstructuraFacade`). Concurrencia optimista por `updated_at` con microsegundos mediante `UPDATE … WHERE updated_at = ?`; código `CAT-###` generado en transacción con reintento ante colisión; auditoría campo a campo en una tabla propia del módulo. Frontend: `frontend/src/modules/compensaciones/categorias` con listado (filtros en URL), detalle, modal de crear/editar con `useFieldArray` y diálogo de desactivación, fiel al tablero `design/CategoriasReconocimiento.dc.html`.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel 13 (backend, versiones de `backend/composer.json`); TypeScript estricto, React 19 (frontend)

**Primary Dependencies**: las existentes (Sanctum, Eloquent; Vite, Tailwind 4, React Router, TanStack Query, Axios, React Hook Form, Zod). **No se agregan dependencias.**

**Storage**: SQLite (predeterminado). Tablas nuevas: `categorias_reconocimiento`, `indicadores_reconocimiento` y `auditoria_categorias_reconocimiento`. Tabla propia de auditoría `auditoria_categorias_reconocimiento`. Ver [data-model.md](data-model.md).

**Testing**: Pest (Unit de dominio, Feature de API, Facade), Laravel Pint y análisis estático; Vitest + Testing Library; Playwright para el flujo principal.

**Target Platform**: Web (SPA + API REST), 320–1440 px, WCAG 2.2 AA.

**Project Type**: web-service + SPA (monolito modular).

**Performance Goals**: primera página del listado en menos de 2 s con hasta 500 categorías (SC-010); paginación de 15 fija.

**Constraints**: permisos comprobados en servidor; código inmutable y sin duplicados bajo concurrencia; token de concurrencia con microsegundos; sin DELETE de categorías (solo el reemplazo de indicadores borra filas hijas); texto plano sin HTML; mensajes sin información sensible; el módulo no hace JOIN ni lee tablas de Estructura.

**Scale/Scope**: 5 historias, 40 FR, 3 tablas, 7 endpoints, 1 Facade pública, 1 página con 3 modales/diálogos.

## Constitution Check

*GATE: debe pasar antes de Phase 0 research. Reevaluado tras Phase 1.*

| Principio | Evaluación |
|---|---|
| I. Clean Architecture modular | Módulo nuevo `backend/app/Modules/Compensaciones/{Domain,Application,Infrastructure,Presentation,Routes}` sobre el esqueleto de `MakeModuleCommand`. Casos de uso explícitos; controladores delgados sin consultas; dominio sin Laravel/Eloquent; persistencia vía `CategoriaRepository`. **Pasa.** |
| Rutas `/api/<modulo>/` | `/api/compensaciones/categorias…` y `/api/compensaciones/competencias-disponibles`. **Pasa.** |
| Endpoints admin con Sanctum + `admin` | Escritura (crear, editar, estado, competencias disponibles) bajo `auth:sanctum` + `admin`. Lectura bajo `auth:sanctum` para todo usuario autenticado; el rol `admin` ve inactivas y auditoría (patrón de `CompetenciaController`). Ver divergencia de permisos en research D1. **Pasa con divergencia registrada (D1).** |
| Módulo no accede a internals de otro | Competencias solo por `EstructuraFacade` (contrato público existente) detrás de `CompetenciasPort`; la FK a `competencias` existe solo por integridad referencial. Desafíos/Hitos/Ranking consumen `CategoriasFacade`. **Pasa.** |
| II. Frontend | `frontend/src/modules/compensaciones/categorias/` con `api.ts`, `validation.ts`, `text.ts`, `hooks/`, `components/`, `pages/`; Axios solo vía cliente compartido; mutaciones sin reintento; estados cargando/vacío/error/éxito. **Pasa.** |
| III. Diseño | Fiel a `design/CategoriasReconocimiento.dc.html` y `design/V_cr-*.dc.html` (27 archivos verificados) y a `DESIGN.md`; no se modifica `design/`. Diferencias en research D8. **Pasa.** |
| IV. Calidad / dependencias | Pest + Pint + análisis estático; ESLint + tsc + Vitest + build + Playwright. Sin dependencias nuevas. Seeders con datos sintéticos. **Pasa.** |

Sin violaciones injustificadas. Una **divergencia** (D1: permisos y roles) y su consecuencia en el alcance (D2: solo los roles Administrador y Usuario) quedan documentadas en [research.md](research.md).

## Project Structure

### Documentation (this feature)

```text
specs/016-categorias-reconocimiento/
├── plan.md
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   ├── api-categorias.md
│   └── facade-categorias.md
├── checklists/
│   └── requirements.md
└── tasks.md          # lo genera /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Compensaciones/
│   ├── Domain/
│   │   ├── Entities/Categoria.php
│   │   ├── ValueObjects/{NombreCategoria,PuntuacionBase,Icono,Indicador,EstadoCategoria}.php
│   │   ├── Exceptions/{CategoriaNoEncontrada,CategoriaModificadaPorOtro,CompetenciaNoDisponible}.php
│   │   └── Repositories/CategoriaRepository.php
│   ├── Application/
│   │   ├── DTO/
│   │   ├── Ports/CompetenciasPort.php
│   │   ├── Services/{CategoriasService,AuditoriaDeCategoria,PoliticaDeAcceso}.php   # CategoriasService respalda la Facade pública
│   │   └── UseCases/{ListarCategorias,ObtenerCategoria,CrearCategoria,ActualizarCategoria,CambiarEstadoCategoria,ListarCompetenciasDisponibles,ListarCompetenciasParaFiltro}.php
│   ├── Infrastructure/
│   │   ├── Adapters/CompetenciasDesdeEstructura.php
│   │   ├── Persistence/Eloquent/{Models,Repositories}/
│   │   └── Providers/CompensacionesServiceProvider.php
│   ├── Presentation/{Controllers,Requests,Resources,Facades}/
│   └── Routes/api.php
├── database/migrations/2026_10_20_00000X_create_{categorias,indicadores,auditoria_categorias}_reconocimiento_table.php
├── database/seeders/CategoriasReconocimientoSeeder.php
└── tests/{Unit,Feature}/Compensaciones/

frontend/src/modules/compensaciones/categorias/
├── api.ts  validation.ts  text.ts  iconos.ts
├── hooks/
├── components/   # tabla, filtros, detalle, formulario, lista de indicadores, selector de ícono, diálogos
└── pages/CategoriasReconocimientoPage.tsx
```

**Structure Decision**: monolito modular existente; el módulo `Compensaciones` es nuevo en backend y frontend (hoy solo hay `Desarrollo`, `Estructura`, `GestionPersonal`, `Shared`). Se registra su proveedor y su ruta de navegación lateral de forma declarativa.

## Complexity Tracking

| Desviación | Por qué se necesita | Alternativa más simple descartada |
|---|---|---|
| Sin tabla de permisos: `ver` = usuario autenticado; escritura = capacidad `admin` (D1). Alcance acotado a los roles Administrador y Usuario (D2) | El repositorio solo tiene las capacidades `admin` y `user` (token Sanctum); no hay permisos de módulo ni roles Líder/Gerencia/Candidato | Crear un sistema de permisos y roles nuevo: fuera del alcance y de la constitución |
| Una sola organización: unicidad global de código y nombre (sin tenant) | El entorno usa un único esquema SQLite y la constitución no define tenants; la spec se alineó (FR-003) | Esquema por tenant: no existe en el repositorio |
