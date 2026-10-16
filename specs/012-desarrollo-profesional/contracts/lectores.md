# Contrato: lectura de desarrollo para otros módulos (GP-06)

Ningún módulo accede a las tablas; se usa el puerto/fachada de `GestionPersonal`.

## `LectorDeDesarrolloPort` (Application\Ports)

| Método | Datos |
|---|---|
| `deColaboradores(int $tenantId, array $colaboradorIds): Lectura` | `[colaborador_id => {formaciones: list<…>, experiencias: list<…>, movilidad: {estado, observaciones, actualizada_at}}]`, mismo orden y forma que la representación HTTP (sin `version` ni auditoría) |

- Una sola consulta `whereIn` por tabla (sin N+1); ids de otro tenant se ignoran.
- Una ficha sin registros aparece con listas vacías y movilidad `NoInformada`.
- Fallos de infraestructura ⇒ `Lectura::noDisponible()`, nunca excepción; con la feature instalada nunca `noInstalado()`.

## `PersonalFacade`

| Método | Descripción |
|---|---|
| `getDesarrollo(int $tenantId, array $colaboradorIds): array` | Delegado al puerto; devuelve el arreglo de datos o lanza la `InformacionNoDisponible` del módulo si la lectura falla (el llamador decide su degradación) |

## `LectorDeCapacidadesPort`

`instalada('desarrollo')` ⇒ `true` con `LectorDeCapacidadesDesarrollo` (instalado por esta feature). Las demás capacidades siguen en `false` hasta que sus features las instalen.
