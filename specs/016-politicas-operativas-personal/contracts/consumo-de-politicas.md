# Contrato de consumo: cómo usan las políticas los procesos de otros módulos

Aplica a novedades, movimientos y offboarding (módulos que aún no existen). Esta funcionalidad **no** crea tablas de solicitudes ni implementa esos procesos: define la fachada pública y las obligaciones del consumidor.

## Fachada pública

`App\Modules\GestionPersonal\Presentation\Facades\PoliticasFacade` (único punto de entrada permitido; los consumidores no leen ni escriben `politicas_personal`, `politica_versiones` ni `politica_acciones`).

| Método | Uso | Resultado |
|---|---|---|
| `aplicablePara(int $tenantId, string $tipo, DateTimeImmutable $instante): array` | Mostrar las condiciones antes de presentar. No bloquea ni reserva. | `{situacion: 'Vigente'\|'SinConfigurar'\|'NoAdmiteSolicitudes', politica: arreglo\|null}`; lanza `PoliticaNoDisponible` si la lectura falla |
| `seleccionarParaPresentar(int $tenantId, string $tipo, DateTimeImmutable $instante): array` | Elegir la versión **dentro de la transacción del consumidor** al confirmar la presentación. Toma el bloqueo de la cabecera del tipo; lanza `LogicException` si no hay una transacción abierta. | arreglo de la política aplicable o excepción `PoliticaNoConfigurada`, `PoliticaNoAdmiteSolicitudes`, `PoliticaNoDisponible` |

Arreglo de la política aplicable (los modelos y DTO no salen del módulo, igual que en `PersonalFacade`): `version_id`, `numero`, `tipo` (`Permisos`…`Offboarding`), `inicio_vigencia` (ISO 8601 UTC), `parametros` (arreglo validado, los canónicos sellados), `condiciones` (arreglo sellado) y `hash` (`sha256` de las condiciones canónicas). El `tipo` se pasa con su valor (`'Permisos'`, `'Offboarding'`…).

`instante` es el momento de confirmación de la presentación, con el reloj del servidor del consumidor, tomado una sola vez.

## Obligaciones del consumidor

1. **Presentar**: llamar a `seleccionarParaPresentar` dentro de la misma transacción en la que inserta la solicitud. Si lanza `PoliticaNoConfigurada`, `PoliticaNoAdmiteSolicitudes` o `PoliticaNoDisponible`, **no se confirma la presentación** y no se usan valores supuestos.
2. **Conservar**: la solicitud guarda `politica_version_id` (FK **restrictiva**, nunca en cascada) y una **copia** de `condiciones` y de `hash`. Ninguna escritura posterior de políticas altera esa copia.
3. **Evaluar con lo conservado**: la validación de anticipación, justificación y documento al presentar usa los parámetros devueltos; al resolver, el consumidor usa la copia guardada. **No vuelve a seleccionar la política vigente** para sustituirla.
4. **Anticipación**: se calcula en días calendario entre la fecha de presentación y la fecha inicial solicitada, ambas en la zona de la organización; nunca con la fecha de resolución. 0 permite el mismo día y no autoriza fechas pasadas.
5. **Integridad propia**: al resolver y aplicar efectos, el consumidor vuelve a comprobar permisos, disponibilidad de la posición, vínculo, correspondencia y vigencia de la evaluación contra el puesto destino y acceso al documento de respaldo. Una política anterior **no** puede omitir esas condiciones.
6. **Disponibilidad**: `disponible=false` impide presentar nuevas solicitudes y no cancela las existentes.
7. **Offboarding**: al iniciar el proceso copia `parametros.tareas` (nombre, descripción, exige evidencia y orden) de la versión seleccionada a tareas propias del proceso; los cambios posteriores de la plantilla no agregan ni quitan tareas. Los responsables se asignan en el proceso.
8. **Ascensos y Traslados**: la política no aporta destinos ni niveles: puestos destino y niveles provienen de Estructura empresarial; la evaluación contra el puesto destino es siempre obligatoria (regla fija `MOV-EVALUACION`).
9. **Consulta del solicitante**: antes de presentar puede usar `GET /api/politicas-personal/{tipo}/aplicable` o `aplicablePara`; después, el detalle de su solicitud muestra la versión y los requisitos conservados (su copia), con el componente `CondicionesAplicables`.
10. **Historial laboral**: los hechos que aplique el consumidor referencian la versión utilizada; esta funcionalidad no escribe en el historial.

## Garantías de la fachada

- Una publicación o un retiro concurrente no puede intercalarse entre la selección y la asociación: la operación que confirma primero fija el orden.
- El estado temporal se calcula con `instante`; no depende de un proceso auxiliar.
- Un fallo de lectura es `PoliticaNoDisponible`, nunca «sin requisitos».
- Los resultados no incluyen descripción interna, motivo, revisión ni responsables.

## Verificación con un consumidor de prueba

Un consumidor de prueba (solo en pruebas) crea una «solicitud» con `politica_version_id` y la copia, y se comprueba: (a) presentar antes de publicar otra versión conserva la versión original aunque se resuelva después; (b) presentar con una programada aún no iniciada usa la vigente; (c) con la política sin configurar o sin admitir solicitudes no se confirma; (d) la plantilla de offboarding copiada no cambia al publicar otra versión; (e) presentación y publicación entrelazadas: gana la que confirma primero.
