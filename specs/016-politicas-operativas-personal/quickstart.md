# Quickstart: validar las políticas operativas de personal

Guía de validación de punta a punta. Contratos: [api-politicas.md](contracts/api-politicas.md), [catalogo-de-parametros.md](contracts/catalogo-de-parametros.md), [consumo-de-politicas.md](contracts/consumo-de-politicas.md). Modelo: [data-model.md](data-model.md).

## Requisitos previos

- Backend: `php artisan migrate` aplicado en la base que se use (la base de desarrollo no se migra sola).
- E2E: base propia sembrada con el seeder (`php artisan migrate:fresh --seed --env=e2e`); Playwright la levanta y se ejecuta con `DB_DATABASE=database/e2e.sqlite`.
- Seeder: cabeceras de los siete tipos y una política de Permisos publicada de ejemplo (versión 1) para el E2E; el resto queda «sin configurar».
- Interruptor: `GESTION_PERSONAL_POLITICAS_HABILITADO=true` (por defecto).

## Comandos

```bash
# Backend
cd backend && php artisan test --compact --filter=Politicas && vendor/bin/pint --dirty --format agent

# Frontend
cd frontend && npx eslint src && npx tsc -b && npx vitest run src/modules/gestionPersonal/politicas

# E2E
cd frontend && DB_DATABASE=database/e2e.sqlite npx playwright test e2e/politicas.spec.ts
```

## Escenarios

1. **Organización nueva** (PO-CA01): el listado muestra los siete tipos con «Política sin configurar»; ninguno aparece como «Sin restricciones».
2. **Ciclo completo** (PO-CA02/03/04): crear borrador de Permisos, completar parámetros, previsualizar (condiciones, diferencias con la vigente, momento, efecto), publicar de inmediato con motivo y revisor → versión 1 Vigente. Crear otro borrador desde la versión 1, programar su inicio en el futuro → Programada; avanzar el reloj hasta ese instante → la versión 2 es Vigente y la 1 Sustituida, sin ningún proceso auxiliar.
3. **Validaciones** (PO-CA05/06/07/09–17): publicar sin motivo o sin revisor, con parámetro desconocido, con anticipación negativa o decimal, en Ausencias con anticipación, con tareas duplicadas (mayúsculas/tildes), con más de 30 tareas, con inicio pasado, intentando fijar una regla fija; cada rechazo señala el campo o la regla.
4. **Borrador único** (D6): crear un segundo borrador del mismo tipo → 409 con el id del existente; descartar y crear otro funciona.
5. **Inmutabilidad** (PO-CA19): intentar editar o eliminar una versión publicada (por HTTP y por SQL directo en una prueba) falla; sus columnas selladas no cambian.
6. **Retiro** (PO-CA20–23): retirar una programada con justificación → Retirada, número no reutilizado, la vigente sigue aplicando; retirar una que ya empezó → 409 `VIGENCIA_INICIADA`; publicar con una programada existente → 409 `PROGRAMADA_EXISTENTE`.
7. **Concurrencia**: dos publicaciones simultáneas del mismo tipo → una 201 y la otra 409; publicación frente al retiro en el instante de inicio → un único resultado coherente; ni números duplicados ni dos vigentes.
8. **Idempotencia**: repetir la publicación con la misma `Idempotency-Key` → 200 con la misma versión; misma clave con otro contenido → 409.
9. **Edición obsoleta** (PO-CA08): dos administradores editan el borrador; el segundo recibe 409 y el formulario conserva su contenido pendiente y ofrece recargar.
10. **Aplicación a solicitudes** (consumidor de prueba): presentar con la versión 1, publicar la 2, resolver → la solicitud conserva la 1; una nueva usa la 2; con la política sin configurar o con `disponible=false` no se confirma; la plantilla de offboarding copiada no cambia.
11. **Consulta del Colaborador** (PO-CA34/27): `GET …/aplicable` en sus tres situaciones y 503 simulado (sin tratarse como «sin requisitos»); la respuesta no contiene descripción interna, motivo, revisión, borradores ni programadas; el panel y las rutas de administración responden 403.
12. **Tenant ajeno**: ids de versiones de otro tenant → 404 idéntico al inexistente.
13. **Cambio de hora**: con una zona de prueba con cambio de hora, un inicio en el hueco se ajusta al primer instante válido y la confirmación muestra la hora exacta.
14. **Accesibilidad y responsive**: panel, detalle y editor sin scroll horizontal entre 320 y 1440 px; diálogos accesibles; errores asociados a su campo.

## Resultado esperado

- Pruebas backend y frontend en verde; ESLint, `tsc -b` y Pint sin errores; E2E del flujo principal en verde.
- Ninguna versión publicada modificada, ninguna eliminada, números sin huecos reutilizados, como máximo una Vigente y una Programada por tipo.
- La validez de las reglas para la organización se revisa además con el cliente mediante casos de negocio (los tests demuestran el comportamiento, no el acierto del negocio).
