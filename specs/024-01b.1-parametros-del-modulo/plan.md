# Implementation Plan: Parámetros del módulo (01b.1)

**Branch**: `024-01b.1-parametros-del-modulo` | **Date**: 2026-11-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/024-01b.1-parametros-del-modulo/spec.md` y plan de origen [plan-origen-01b.1-parametros-del-modulo.md](plan-origen-01b.1-parametros-del-modulo.md) (inc-01b), con la tabla `parametros_modulo` de `modelo-de-datos.dbml`.

## Summary

El Gestor de Talento ajusta seis parámetros únicos de la organización desde la pestaña «Parámetros». Backend: tabla nueva `parametros_modulo` de solo inserciones (cada cambio es una fila: historial y vigencia a la vez), dos endpoints (`GET` y `PUT /api/compensaciones/parametros`), un servicio de Aplicación `ParametrosDelModulo::valorVigente(clave, fecha)` que reutilizarán las specs siguientes, y control de concurrencia con una `version` por clave. Frontend: pantalla de tarjetas con un único «Guardar», vista previa de los puntos por colaborador con el ejemplo fijo de 10 personas, y último cambio visible bajo cada tarjeta. Se amplía `PoliticaDeAcceso` con el permiso `parametros.gestionar`.

## Technical Context

**Language/Version**: PHP 8.3 / Laravel (versión del repositorio, hoy 13); TypeScript estricto, React

**Primary Dependencies**: las existentes (Sanctum, React Router, TanStack Query, Axios, React Hook Form, Zod); no se añade ninguna

**Storage**: SQLite en desarrollo (PostgreSQL en el modelo lógico). Una migración nueva: `parametros_modulo`

**Testing**: Pest (backend); Vitest + Testing Library (frontend); un E2E Playwright existente en `frontend/e2e/`

**Target Platform**: Web (SPA + API)

**Project Type**: web-application (backend + frontend)

**Performance Goals**: GET y PUT responden de forma imperceptible (6 claves, consultas por índice `(clave, vigente_desde)`); sin objetivos adicionales

**Constraints**: solo inserciones en `parametros_modulo` (nunca UPDATE ni DELETE); fechas de vigencia en la zona de `config/compensaciones.php` (`zona_horaria` = America/Guayaquil) aunque `config/app.php` use UTC; sin FK a `users`; el módulo no escribe en tablas de otros módulos ni lee la tabla `users`

**Scale/Scope**: 6 claves, 1 tabla, 2 endpoints, 1 pantalla, ~1 pestaña nueva en el módulo

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principio | Evaluación |
|---|---|
| I. Backend modular / Clean Architecture | Pasa. Casos de uso explícitos (`ConsultarParametros`, `GuardarParametros`), repositorio `ParametroRepository` en Dominio con adaptador Eloquent en Infraestructura, puerto `NombresDeUsuarioPort` hacia Shared por su Facade (sin leer `users`), controlador delgado, `FormRequest` para validar. El piso por categoría se lee con el `CategoriaRepository` propio del módulo. Rutas bajo `/api/compensaciones/` con `auth:sanctum` + `admin` (capacidad del repositorio de prueba) y, además, la decisión fina en `PoliticaDeAcceso::puede(..., 'parametros.gestionar')`. |
| II. Frontend modular, accesible y tipado | Pasa. `frontend/src/modules/compensaciones/parametros/` con `api.ts`, `hooks/`, `components/`, `pages/`, `validation.ts` (Zod) y `text.ts` (todo el texto en español, centralizado). Sin `any`. Axios solo en la capa compartida; las escrituras no se reintentan. Estados loading / error con «Reintentar» / success y errores por campo conservando valores. WCAG 2.2 AA y 320–1440 px. |
| III. Diseño como referencia | Pasa con acción: `design/ParametrosModulo.dc.html` ya está en `design/` (sin versionar) y se commitea con esta feature. Si `design/DESIGN.md` no documenta el patrón «tarjetas de parámetros con un solo Guardar», se agrega una sección breve antes de implementar la interfaz. |
| IV. Calidad y seguridad | Pasa. Cobertura Pest/Vitest/E2E definida en `quickstart.md`; entradas validadas en servidor y cliente; errores públicos seguros; sin secretos ni dependencias nuevas; auditoría inmutable (solo inserciones). |

Sin violaciones; no se requiere Complexity Tracking. Re-evaluado tras el diseño de Phase 1: sin cambios.

## Project Structure

### Documentation (this feature)

```text
specs/024-01b.1-parametros-del-modulo/
├── 01b.1-parametros-del-modulo.md          # Fuente de la spec (copiada)
├── plan-origen-01b.1-parametros-del-modulo.md  # Fuente del plan (copiada)
├── spec.md
├── plan.md                                 # Este archivo
├── research.md
├── data-model.md
├── quickstart.md
├── contracts/
│   └── parametros-api.md
├── checklists/requirements.md
└── tasks.md                                # Lo crea /speckit-tasks
```

### Source Code (repository root)

```text
backend/
├── app/Modules/Compensaciones/
│   ├── Domain/
│   │   ├── Entities/CambioDeParametro.php
│   │   ├── ValueObjects/ClaveDeParametro.php          # enum, rango y valor por defecto de cada clave
│   │   ├── Exceptions/ParametrosModificadosPorOtro.php (409)
│   │   ├── Exceptions/ValorDeParametroInvalido.php    (422 por campo)
│   │   ├── Exceptions/PuntosPorDebajoDeCategoria.php  (422 con nombre y puntos)
│   │   └── Repositories/ParametroRepository.php
│   ├── Application/
│   │   ├── Ports/NombresDeUsuarioPort.php
│   │   ├── DTOs/ParametroDTO.php, CambioSolicitadoDTO.php
│   │   ├── Services/ParametrosDelModulo.php           # valorVigente(clave, fecha) y valorProgramado()
│   │   ├── Services/PoliticaDeAcceso.php              # + PARAMETROS_GESTIONAR
│   │   ├── UseCases/ConsultarParametros.php
│   │   └── UseCases/GuardarParametros.php
│   ├── Infrastructure/
│   │   ├── Adapters/NombresDeUsuarioDesdeShared.php
│   │   ├── Persistence/Eloquent/Models/ParametroModuloModel.php
│   │   ├── Persistence/Eloquent/Repositories/EloquentParametroRepository.php
│   │   └── Providers/CompensacionesServiceProvider.php   # + bindings
│   ├── Presentation/
│   │   ├── Controllers/ParametrosController.php
│   │   ├── Requests/GuardarParametrosRequest.php
│   │   └── Resources/ParametrosResource.php
│   └── Routes/api.php                                  # + GET/PUT /parametros
├── config/compensaciones.php                           # zona_horaria = America/Guayaquil (la reutiliza 02.2)
├── database/migrations/2026_11_03_00000X_create_parametros_modulo_table.php
└── tests/Feature/Compensaciones/ParametrosTest.php, ParametrosDelModuloTest.php; PoliticaDeAccesoTest.php (+ parametros.gestionar)

frontend/
├── src/modules/compensaciones/parametros/
│   ├── api.ts, text.ts, validation.ts (+ validation.test.ts), nav.tsx
│   ├── hooks/useParametros.ts, hooks/useGuardarParametros.ts
│   ├── components/TarjetaParametro.tsx, TarjetaSensibilidad.tsx, VistaPreviaPuntos.tsx
│   └── pages/ParametrosPage.tsx (+ ParametrosPage.dom.test.tsx)
├── src/App.tsx                                         # ruta compensaciones/parametros
└── e2e/parametros-modulo.spec.ts

design/ParametrosModulo.dc.html                         # ya existe sin versionar; se commitea
```

**Structure Decision**: Aplicación web existente. Todo vive en el módulo `Compensaciones` / `compensaciones` siguiendo la estructura que ya usa categorías.  Las specs posteriores (01b.2, 02.x, 03.x, 07.x) son del mismo módulo y usan directamente el servicio de Aplicación `ParametrosDelModulo`; no hay Facade para uso interno.

## Decisiones de diseño

1. **Solo inserciones**: cada PUT inserta una fila por clave cuyo valor realmente cambió. No hay UPDATE ni DELETE, ni en el repositorio ni en el modelo (el modelo no expone `save` sobre filas existentes).
2. **Vigencia**: `puntos_por_colaborador` con `vigente_desde` = día 1 del mes siguiente; las demás = hoy. «Hoy» y «mes siguiente» se calculan con la zona `config('compensaciones.zona_horaria')` (America/Guayaquil) en una sola función de Aplicación (inyectable en pruebas con `Carbon::setTestNow`).
3. **Lectura del valor vigente**: la fila de la clave con `vigente_desde` más reciente que no supere la fecha; con empate, la de mayor `id`. Sin filas: valor por defecto (para puntos, el mayor entre 100 y la puntuación de la categoría activa más alta, CA-02). El valor programado es la fila con `vigente_desde` futuro, si existe.
4. **Concurrencia (CA-08)**: `version` por clave = id de su última fila (0 si nunca cambió). El PUT recibe la `version` de cada clave editada y, dentro de la transacción y de un candado de aplicación (`Cache::lock`), compara con la actual; si difiere en cualquiera, 409 y no se guarda nada. El candado cubre el caso `version = 0` en que no hay fila que bloquear.
5. **Sin cambios reales**: una entrada cuyo valor ya es el último guardado de esa clave (programado si lo hay, o vigente) no inserta fila, pero sí se valida su `version`.
6. **Piso por categoría (CA-04)**: se compara con la categoría activa de mayor `puntuacion_base` usando el `CategoriaRepository` del módulo; el 422 lleva nombre y puntos. El lado inverso (categoría > parámetro) es 01b.2.
7. **Acceso (CA-09)**: rutas con `auth:sanctum` + `admin`, y `PoliticaDeAcceso::puede($usuario, PARAMETROS_GESTIONAR)` en el límite de entrada del caso de uso. El frontend oculta la pestaña a quien no tiene rol `admin` (solo presentación).
8. **Nombre de quien cambió**: `NombresDeUsuarioPort` implementado con la Facade pública de Shared (`getNombresDeUsuarios`), igual que el módulo Desarrollo.
9. **Pestaña «Parámetros»**: página propia en `/compensaciones/parametros`; el módulo muestra «Categorías | Parámetros» como pestañas de navegación solo para el Gestor, reutilizando el componente `Tabs` compartido sin modificarlo, envuelto en `frontend/src/modules/compensaciones/PestanasCompensaciones.tsx` (la navegación entre rutas se resuelve dentro del módulo). Quien no es Gestor ve el módulo como hoy.
10. **Sensibilidad**: se guarda como entero 1/2/3 y la interfaz solo muestra Baja / Normal (recomendada) / Alta con su explicación; los umbrales numéricos de cada opción viven en código y los define 07.2.

## Diseño de frontend

### Rutas
| Ruta | Pantalla (del spec) | Roles con acceso |
|---|---|---|
| `/compensaciones/parametros` | Parámetros del módulo | Gestor de Talento (rol `admin` en el repositorio de prueba); otros roles no ven la pestaña y el backend responde 403 |

### Componentes
- **Nuevos**: `ParametrosPage`, `TarjetaParametro` (título, explicación, campo numérico, rango, cuándo aplica, último cambio), `VistaPreviaPuntos` (actual → nuevo, vigencia, ejemplo de 10 personas, valor programado), `TarjetaSensibilidad` (tres radios con explicación y nota del cálculo semanal).
- **Del design system**: `Tabs`, `TextField`, `Button`, `Alert`, `Skeleton`, `ErrorState`, patrón Formulario de `design/DESIGN.md`.

### Datos y estado
- `GET /api/compensaciones/parametros` alimenta la pantalla; `PUT` guarda solo los campos modificados con su `version`.
- Tras un PUT exitoso se invalida la consulta de parámetros; en 409 se muestra el aviso con acción «Recargar» que vuelve a pedir el GET sin perder lo escrito hasta que el usuario recarga.
- Sin estado en URL (no hay filtros ni paginación).

### Validación
- Zod: entero dentro del rango de cada clave (rangos del GET); sensibilidad 1–3. Misma validación en servidor.
- Los 422 por campo (`cambios.N.valor`) se mapean al campo de su clave; el 422 de categoría se muestra bajo «Puntos por colaborador» con el nombre y los puntos de la categoría.
