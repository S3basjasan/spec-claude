# Quickstart: validar el catálogo formativo

Guía para comprobar de punta a punta que la feature funciona. Contratos en [contracts/](contracts/) y modelo en
[data-model.md](data-model.md).

## Requisitos previos

- Backend: PHP 8.3, Composer y SQLite. Frontend: Node y dependencias instaladas (`npm install`).
- Usuarios sembrados por `UsuariosInicialesSeeder`: un Administrador (`admin`) y un Usuario/Colaborador (`user`).
- Las features 001 (competencias) y 004 (puestos) ya aplicadas.

## Preparar los datos

```bash
cd backend && php artisan migrate:fresh --seed
```

La siembra deja 4 áreas formativas, cursos y micro-contenidos de demostración (uno archivado) y asociaciones tales que: ≥ 2
competencias activas sin recursos, ≥ 1 inactiva con vínculos y ≥ 1 activa con solo recursos archivados.

## Levantar la aplicación

```bash
cd backend && php artisan serve
```

```bash
cd frontend && npm run dev
```

## Escenarios de validación

**1. Administrador — matriz y cobertura** (US2). Entra como Administrador, abre **Formación**. Esperado: por defecto solo
competencias activas; indicador de cobertura que coincide con (activas con ≥ 1 recurso disponible) / (activas totales);
una competencia con solo recursos archivados figura "sin cobertura"; búsqueda y filtros (tipo, área, con/sin cursos, estado)
actualizan la URL y la tabla; "Ver sin cobertura" lista las activas sin ningún curso ni micro-contenido disponible (`cobertura=sin_oferta`); los contadores de la matriz coinciden con el detalle ("2 cursos · 1 archivado").

**2. Administrador — asociar y guardar** (US3). Abre "Ver detalle" de una competencia activa sin cursos, pulsa "Asociar curso",
elige dos y pulsa **Guardar**. Esperado: la matriz muestra las nuevas cantidades, la cobertura se recalcula y el historial
muestra dos entradas "Asociado". Cierra el panel con cambios sin guardar: aparece la advertencia.

**3. Quitar un vínculo** (US3). Quita un curso: el diálogo informa que solo se elimina el vínculo; al confirmar y guardar, el
curso sigue existiendo y aparece en el selector de otra competencia.

**4. Competencia inactiva** (US3). Abre el detalle de la competencia inactiva sembrada: "Asociar" aparece deshabilitado con el
motivo, pero sus vínculos se ven y se pueden quitar. Una petición `PUT` que agregue recursos a esa competencia devuelve 422.

**5. Áreas formativas** (US4). Crea un área, asígnala a una competencia y renómbrala; intenta un nombre repetido con otra
capitalización y espacios: 422 con error en el campo. El colaborador ve la tarjeta bajo el área nueva y el nombre actualizado.

**6. Edición concurrente.** Abre la misma competencia en dos sesiones, guarda en la primera y luego en la segunda: la segunda
recibe la alerta "La configuración cambió" con "Recargar".

**7. Colaborador — tarjetas y detalle** (US1). Entra como Colaborador. Esperado: tarjetas agrupadas por área (y "Sin área
formativa" al final), solo competencias activas, contador "X cursos disponibles" sin archivados, puntos A/B/C/D sin resaltar
(no hay Gestión de Personal), detalle con grados, cursos (tipo, duración, formador) y micro-contenidos con la nota de
conocimientos; los botones sin destino aparecen deshabilitados con "Próximamente". No hay controles de gestión ni cobertura.

**8. Recomendados** (US5). Sin DNF la sección **no aparece** y el resto funciona. Con un adaptador falso de brechas (en pruebas
Pest/Vitest) aparece solo para competencias con brecha y con recursos disponibles.

**9. Auditoría** (US6). El detalle del Administrador muestra "Última configuración" (usuario y fecha) y el historial con vínculos,
cambios de área y renombrados; un guardado sin cambios no agrega entradas; no hay forma de editar ni borrar el historial.

**10. Coherencia visual.** Compara la matriz y las tarjetas con `design/CatalogoAdmin.dc.html` y
`design/CatalogoColaborador.dc.html` y con Competencias/Puestos: mismo shell, tokens, tabla, paginación y estados; revisa tema
claro/oscuro y anchos de 320 a 1440 px sin scroll horizontal.

## Pruebas automáticas

```bash
cd backend && ./vendor/bin/pest && ./vendor/bin/pint --test
```

```bash
cd frontend && npm run lint && npx tsc -b && npm test && npm run build
```

```bash
cd frontend && npm run e2e
```

Resultado esperado: todo en verde, incluido `e2e/catalogo-formativo.spec.ts` (administrador asocia y guarda; colaborador consulta).
