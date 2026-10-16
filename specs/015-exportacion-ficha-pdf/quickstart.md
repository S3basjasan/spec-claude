# Quickstart: validar la exportación individual de ficha

Guía de validación de extremo a extremo. Datos y API: [data-model.md](data-model.md) y [contracts/](contracts/).

## Prerrequisitos

- Backend y frontend instalados; fichas, ingresos, desarrollo y Estructura disponibles.
- Dependencia `dompdf/dompdf` aprobada por el usuario (2026-10-16); verificada con `composer require dompdf/dompdf --dry-run` (resuelve `v3.1.6` y 4 dependencias transitivas). Extensiones `dom` y `mbstring` activas. La instalación real es la primera tarea de la implementación.
- Entorno de pruebas, no datos reales:

```bash
cd backend && php artisan migrate:fresh --seed --env=e2e
```

Semillas: Ana (ficha completa con formación, experiencia, movilidad y vínculo), Luis, María (sin datos de desarrollo) y cuentas `admin@hrms.test` (Administrador) y `usuario@hrms.test` (Colaborador de Ana). Para la propia hay que habilitar `puede_exportar` en la asociación de la cuenta de ejemplo.

## Escenarios

1. **Administrador (US1)**: abrir la ficha de Ana → Exportar ficha ⇒ se descarga `Ficha_EMP-001.pdf` con título, código, secciones rotuladas, paginación y momento de generación; la ficha no cambia y no aparece un documento nuevo en Documentos.
2. **Ausentes**: exportar la ficha de María ⇒ «No registrado», movilidad «No informada» y las secciones sin registros identificadas como vacías.
3. **Ingreso programado**: una ficha con ingreso futuro ⇒ aparece como programado, no como vigente; los vínculos previos van en la trayectoria.
4. **Listas extensas**: una ficha con muchas experiencias y observaciones largas ⇒ varias páginas, sin recortes ni texto superpuesto; con tildes y eñes correctas.
5. **Colaborador (US2)**: con `puede_exportar` y sin permiso de consulta de ficha, `/exportar-mi-ficha` descarga su PDF; con solo permiso de ficha o de documentos ⇒ 403 `SIN_PERMISO_EXPORTACION`; cambiar el id a otra ficha por cualquier vía ⇒ no hay ruta ni respuesta con datos ajenos.
6. **Fallos (US3)**: forzar la caída de Estructura o del lector de desarrollo ⇒ 503 `EXPORTACION_NO_DISPONIBLE`, sin archivo y con Reintentar; nunca una sección vacía en su lugar.
7. **Revocación**: revocar `puede_exportar` entre la solicitud y la entrega (prueba con un revocador controlado) ⇒ 403 sin bytes.
8. **Doble clic**: pulsar dos veces rápido ⇒ una sola petición.
9. **Sin ficha asociada**: cuenta sin ficha ⇒ mensaje «Tu cuenta no tiene una ficha asociada».

## Revisión visual del PDF

Descargar el PDF de Ana y el de una ficha extensa, abrirlos en un visor y revisar: paginación, saltos de página sin partir un registro, acentos, saltos de línea de textos largos, secciones vacías y que no aparezca ningún enlace de descarga ni dato de documentos.

## Pruebas automatizadas

```bash
cd backend && vendor/bin/pint --dirty --format agent && php artisan test --compact --filter=Exportacion
cd frontend && npm run lint && npx tsc -b && npm run test && npm run build
cd frontend && npx playwright test e2e/exportacion.spec.ts
```

Cobertura esperada: plantilla (todas las secciones, etiquetas, escape de HTML en observaciones, ingreso programado ≠ vigente); renderizado real con acentos, observaciones largas y listas de varias páginas (estructura `%PDF-`/`%%EOF` y más de una página); feature de ambas rutas (401/403/404/503, 404 de otro tenant, propia sin permiso de ficha, con permiso de ficha pero sin exportación, sin ficha, sin escrituras en ninguna tabla, cabeceras exactas, nombre saneado); fallos de cada fuente y del renderizador ⇒ JSON 503 sin bytes; PDF vacío o truncado ⇒ rechazado; revocación entre generar y entregar ⇒ 403; lectura coherente con una escritura concurrente (la lectura usa una sola transacción en la conexión por defecto); Vitest del botón (progreso, bloqueo, errores por estado, blob con tipo incorrecto no se descarga, URL liberada) y de la página; E2E del flujo del Administrador y del Colaborador.
