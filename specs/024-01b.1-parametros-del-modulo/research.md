# Research: Parámetros del módulo (01b.1)

No quedaron `NEEDS CLARIFICATION` en el contexto técnico. Se registran las decisiones que el plan de origen deja implícitas y que se verificaron contra el código del repositorio.

## 1. Historial y vigencia en una sola tabla de solo inserciones
- **Decision**: `parametros_modulo` guarda una fila por cambio; el valor vigente se deduce por `vigente_desde`.
- **Rationale**: cumple CA-07 (nunca se borra un cambio) y CA-05/CA-06 (vigencia diferida) sin job de activación el día 1: al cambiar de mes, la fila programada pasa a ser la más reciente que no supera la fecha.
- **Alternatives considered**: tabla de valores + tabla de historial (duplica datos, riesgo de divergencia); job mensual que aplique el valor pendiente (más piezas y puede fallar).

## 2. Zona horaria
- **Decision**: calcular «hoy» y «día 1 del mes siguiente» con Carbon en la zona de `config/compensaciones.php` (`zona_horaria` = `America/Guayaquil`), aunque `config/app.php` tiene `timezone = UTC`. El archivo de configuración es nuevo y 02.2 reutiliza la misma clave.
- **Rationale**: `grep` no encontró «Guayaquil» en `backend/app` ni `backend/config`; si se usara la zona por defecto, entre las 19:00 y 24:00 locales la fecha sería la del día siguiente y el cambio de mes se adelantaría.
- **Alternatives considered**: cambiar `app.timezone` (afecta a todo el sistema, fuera de alcance); fijar la zona en el código (02.2 la necesita igual).

## 3. Control de concurrencia
- **Decision**: `version` por clave = id de su última fila (0 si no hay); comparación dentro de transacción y de un candado de aplicación.
- **Rationale**: `parametros_modulo` no tiene `updated_at` (a diferencia de categorías, que usa microsegundos). Con `version = 0` no hay fila que bloquear, así que dos primeras inserciones simultáneas necesitan un candado externo a las filas.
- **Alternatives considered**: `lockForUpdate` (no cubre el caso sin filas y SQLite lo ignora); índice único `(clave, vigente_desde)` (impediría el reemplazo de un cambio programado, que el plan resuelve con otra fila).

## 4. Nombre de quien hizo el cambio
- **Decision**: puerto `NombresDeUsuarioPort` con adaptador sobre `SharedFacade::getNombresDeUsuarios`.
- **Rationale**: es el patrón que ya usa el módulo Desarrollo (`NombresDeUsuarioDesdeShared`); respeta que un módulo no lea tablas de otro.
- **Alternatives considered**: unir con `users` (viola la constitución).

## 5. Valor anterior del cambio
- **Decision**: no se agrega columna `valor_anterior`. Si existe una fila previa de esa clave, el «valor anterior» sale de ella. En el primer cambio de una clave no hay valor anterior: la pantalla muestra «de valor por defecto a {nuevo}», sin número.
- **Rationale**: respeta el modelo de datos recibido y evita mostrar un número que dependa de las categorías (el defecto de `puntos_por_colaborador` varía con CA-02).
- **Alternatives considered**: calcular el defecto al consultar (puede no coincidir con el que realmente se reemplazó); columna `valor_anterior` nullable (rechazada).

## 6. Navegación «Pestaña Parámetros»
- **Decision**: usar `shared/ui/Tabs` sin modificarlo. Como es por paneles (recibe `children`), el módulo agrega `frontend/src/modules/compensaciones/PestanasCompensaciones.tsx`, que lo envuelve: `active` según la ruta, `onChange` con `navigate()` y la página como `children`. Solo se muestra con `role === 'admin'`.
- **Rationale**: hoy el módulo es una sola página con un ítem de menú (`categoriasReconocimientoNav`); `Tabs` ya existe y es accesible. La visibilidad usa el mismo criterio que `CategoriasReconocimientoPage` (`usuario?.role === 'admin'`).
- **Alternatives considered**: un segundo ítem en el menú lateral (el spec habla de «pestaña»).

## 7. Reutilización por otras specs
- **Decision**: el servicio de Aplicación `ParametrosDelModulo::valorVigente(clave, fecha)` lo usan directamente las demás specs; no hay `ParametrosFacade`.
- **Rationale**: 01b.2, 02.x, 03.x y 07.x pertenecen al mismo módulo Compensaciones; las Facades son para colaboración entre módulos, no para uso interno (decisiones compartidas §6).
