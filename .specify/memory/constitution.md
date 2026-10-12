<!--
Sync Impact Report
- Version change: (plantilla sin ratificar) → 1.0.0
- Principios modificados: ninguno renombrado; se definieron 4 principios nuevos
  (I. Backend, II. Frontend, III. Diseño, IV. Calidad, seguridad y dependencias)
- Secciones añadidas: Dependencias preaprobadas; Flujo de desarrollo y puertas de calidad
- Secciones eliminadas: quinto principio de la plantilla (no requerido)
- TODOs diferidos: ninguno
-->
# Spec Claude Constitution

## Core Principles

### I. Backend modular con Clean Architecture

El backend MUST mantenerse en Laravel con PHP 8.3 y respetar las versiones declaradas en
`backend/composer.json`.

**Código transversal** en `backend/app/Shared/`: MUST usarse solo para capacidades técnicas o
contratos realmente reutilizables por varios módulos; MUST NOT convertirse en un módulo de
negocio ni en un lugar para código cuya responsabilidad no está clara. Solo puede contener,
ante una necesidad concreta:

- `Application/`: contratos de aplicación transversales, buses, paginación y resultados compartidos.
- `Domain/`: objetos de valor, eventos base, excepciones y contratos sin significado exclusivo
  de un módulo.
- `Infrastructure/`: adaptadores técnicos compartidos, integraciones, persistencia transversal
  y utilidades de framework.
- `Presentation/`: respuestas HTTP, middleware y transformadores reutilizables.
- `Support/`: utilidades técnicas pequeñas que no expresan reglas de negocio.

**Estructura modular** obligatoria en `backend/app/Modules/<Modulo>/`. Cada módulo MUST aplicar
Clean Architecture usando las carpetas existentes, sin sustituirlas por un árbol nuevo:

- Dominio: entidades, objetos de valor, reglas e interfaces independientes de Laravel.
- Aplicación: casos de uso, comandos, consultas, DTO de entrada/salida y puertos.
- Infraestructura: persistencia, repositorios concretos, integraciones y adaptadores.
- Presentación: controladores HTTP, requests, resources y rutas.

Las dependencias MUST apuntar hacia adentro. Dominio y aplicación MUST NOT depender de Laravel,
Eloquent, HTTP, SQLite u otros detalles de infraestructura.

Reglas adicionales:

- Los controladores MUST ser delgados y MUST NOT ejecutar consultas de base de datos.
- Cada acción de negocio MUST coordinarse mediante un caso de uso explícito.
- La validación y autorización MUST ejecutarse en los límites de entrada.
- La persistencia MUST accederse mediante contratos o repositorios del módulo.
- Un módulo MUST NOT acceder a clases internas o tablas de otro módulo; la colaboración MUST
  usar contratos públicos, casos de uso o eventos explícitos.
- SQLite es el almacenamiento predeterminado del desarrollo actual.
- Migraciones, factories y seeders MUST seguir las convenciones existentes.
- Los endpoints MUST usar rutas bajo `/api/<modulo>/`.
- Los errores MUST NOT revelar información sensible.
- Los endpoints administrativos MUST requerir autenticación Sanctum y capacidad `admin`, salvo
  excepciones expresamente aprobadas.
- Pest es obligatorio para las pruebas backend.
- Laravel Pint, el análisis estático disponible y las pruebas MUST pasar antes de completar
  una feature.

### II. Frontend modular, accesible y tipado

El frontend MUST mantenerse como SPA React dentro de `frontend/`, con TypeScript estricto, Vite,
Tailwind CSS 4, React Router, TanStack Query, Axios, React Hook Form y Zod.

**Organización**: `frontend/src/modules/<modulo>/<funcionalidad>/`. Una funcionalidad puede
contener, solo cuando haya código con una responsabilidad concreta: `api.ts`, `components/`,
`hooks/`, `pages/`, `validation.ts`, `text.ts` y pruebas colocadas o en la ubicación de pruebas
existente. Estas carpetas MUST NOT crearse de forma automática.

**Código transversal**: `frontend/src/shared/` (`api/`, `layouts/`, `lib/`, `ui/`). `shared` MUST
NOT depender de ningún módulo de negocio.

Reglas frontend:

- Los módulos de negocio MUST corresponder conceptualmente con los módulos backend.
- Una funcionalidad MUST NOT importar implementaciones internas de otro módulo; la comunicación
  usa contratos públicos o `shared`.
- Las páginas MUST NOT llamar Axios directamente; Axios MUST estar encapsulado en el cliente HTTP
  compartido, y las páginas consumen hooks o adaptadores de datos del módulo.
- TanStack Query MUST gestionar consultas, caché y mutaciones. Las escrituras MUST NOT
  reintentarse automáticamente.
- Los parámetros compartibles de búsqueda, filtro y paginación MUST persistirse en la URL.
- `any` está prohibido.
- Todo texto visible MUST estar en español y centralizado.
- La autenticación Bearer MUST gestionarse exclusivamente en la capa HTTP compartida.
- Las guardas frontend son solo de presentación; el backend mantiene la autoridad.
- Toda pantalla autenticada MUST usar un único App Shell reutilizable con header, navegación
  lateral declarativa, contenido mediante `Outlet` y footer.
- Toda vista de datos MUST implementar loading, empty, error/retry y success.
- Los formularios MUST mostrar errores por campo, conservar valores y bloquear envíos pendientes.
- Las acciones destructivas MUST exigir confirmación accesible.
- El tema claro/oscuro MUST persistirse.
- La interfaz MUST cumplir WCAG 2.2 AA y funcionar entre 320 y 1440 px sin scroll horizontal.
- Vitest y Testing Library son obligatorios para lógica de componentes y hooks.
- Cada feature MUST incluir al menos un E2E real de su flujo principal.
- ESLint, TypeScript, pruebas y build MUST pasar antes de completar una feature.

### III. Diseño como fuente de referencia visual

`design/` es la fuente de referencia visual del producto y MUST contener exclusivamente
documentación de diseño, artboards o prototipos de referencia, tokens y decisiones visuales, y
recursos necesarios para explicar la experiencia. MUST NOT contener lógica de aplicación ni
código productivo.

- `design/DESIGN.md` MUST describir layout, componentes, estados, responsive y tokens.
- Los tokens de color, tipografía, espaciado, radios y tamaños MUST tener una única
  representación aplicable en frontend.
- La implementación MUST seguir `design/`. Una desviación visual MUST actualizar primero el
  diseño y registrarse en la spec o el plan.
- Los recursos generados MUST NOT duplicarse entre `design/` y `frontend/` sin una razón
  documentada.
- La referencia visual MUST contemplar los estados loading, empty, error, success y confirmación.
- Los temas claro y oscuro y los puntos de ruptura responsive MUST quedar documentados.

### IV. Calidad, seguridad y dependencias (NON-NEGOTIABLE)

- Toda modificación de comportamiento MUST tener cobertura automatizada, usando el nivel de
  prueba más pequeño que demuestre correctamente el comportamiento.
- Ninguna feature está completa con pruebas, lint, tipos o build fallidos.
- Las dependencias MUST NOT añadirse ni cambiarse sin aprobación explícita, salvo las
  preaprobadas.
- MUST NOT registrarse secretos, tokens, credenciales ni datos personales reales.
- Las entradas externas MUST validarse.
- Autenticación, autorización y auditoría MUST mantenerse en los límites correspondientes.
- Los errores públicos MUST ser seguros y comprensibles.
- Se MUST evitar duplicación, abstracciones especulativas y refactors ajenos al alcance.
- No se permiten excepciones silenciosas a esta constitución.

## Dependencias preaprobadas

Dependencias frontend preaprobadas: React, TypeScript, Vite, Tailwind CSS, React Router,
TanStack Query, Axios, React Hook Form, Zod, ESLint, Vitest y Testing Library.

Cualquier otra dependencia, incluidas herramientas E2E o generadores, requiere aprobación
explícita.

## Flujo de desarrollo y puertas de calidad

Una feature se considera completa solo cuando:

- Backend: Pest, Laravel Pint y el análisis estático disponible pasan.
- Frontend: ESLint, TypeScript, Vitest y build pasan, y existe al menos un E2E del flujo principal.
- La implementación sigue `design/` o la desviación está registrada en la spec o el plan.
- No se añadieron dependencias fuera de las preaprobadas sin aprobación explícita.

## Governance

Esta constitución prevalece sobre cualquier otra práctica del proyecto. Las enmiendas MUST
documentarse, ser aprobadas explícitamente e incluir un plan de migración cuando afecten código
existente. El versionado sigue SemVer: MAJOR para eliminaciones o redefiniciones incompatibles de
principios; MINOR para nuevos principios o ampliación material; PATCH para aclaraciones y
correcciones de redacción. Todo PR y revisión MUST verificar el cumplimiento de esta constitución;
la complejidad añadida MUST justificarse. Las desviaciones MUST registrarse en la spec o el plan.

**Version**: 1.0.0 | **Ratified**: 2026-10-09 | **Last Amended**: 2026-10-09
