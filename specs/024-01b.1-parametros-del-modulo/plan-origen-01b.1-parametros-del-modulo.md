# Plan · 01b.1 Parámetros del módulo

## 1. Contexto de la feature
- Stack y arquitectura según `constitution.md`: Laravel (versión del repositorio, hoy 13) con PHP 8.3, Clean Architecture por módulo, Pest; React con TypeScript estricto, TanStack Query, React Hook Form y Zod, Vitest y una prueba E2E.
- Backend: `backend/app/Modules/Compensaciones/` (carpetas Domain, Application, Infrastructure y Presentation que ya existen). Rutas bajo `/api/compensaciones/`.
- Frontend: `frontend/src/modules/compensaciones/parametros/`.
- Depende de: ninguna spec (conviene hacer antes 01b.3, que define los nombres de permisos). Lo usan después 01b.2, 02.2, 02.3, 03.4, 03.5, 07.2 y 07.3.
- Permiso `parametros.gestionar` (nombre recurso.acción, ver 01b.3). En el repositorio de prueba lo tiene quien tiene la capacidad `admin`; lo resuelve `PoliticaDeAcceso`.

## 2. Modelo de datos
- `parametros_modulo` (nueva; esta spec crea su migración).
- `categorias_reconocimiento` (solo lectura: puntuación de la categoría activa más alta).

## 3. Backend
Todos los endpoints requieren sesión Sanctum (401) y el permiso `parametros.gestionar` (403).

| Operación | Endpoint | Notas | Requisito |
|---|---|---|---|
| Consultar parámetros | GET /api/compensaciones/parametros | Devuelve las seis claves. Por cada una: valor vigente hoy, valor programado y desde cuándo (solo puntos por colaborador, si hay un cambio para el mes siguiente), rango, valor por defecto, último cambio (nombre de quien lo hizo, fecha, valor anterior y nuevo) y `version` (id de la última fila de esa clave, o 0 si nunca cambió). Si una clave nunca cambió, su valor es el por defecto; para puntos por colaborador, el por defecto es el mayor entre 100 y la puntuación de la categoría activa más alta. No hace falta sembrar filas iniciales. | CA-01, CA-02, CA-07, CA-09 |
| Guardar cambios | PUT /api/compensaciones/parametros | Body: `cambios`, lista de `{clave, valor, version}`. Todo en una transacción: si un cambio falla, no se guarda ninguno. Valida entero dentro del rango de la clave (422 por campo); `sensibilidad_radar` solo acepta 1 (Baja), 2 (Normal) o 3 (Alta). Puntos por colaborador menor que la categoría activa más alta: 422 con el nombre de esa categoría. `version` distinta de la actual: 409. Inserta una fila nueva por clave cambiada (nunca actualiza ni borra): puntos por colaborador con `vigente_desde` = día 1 del mes siguiente; las demás con `vigente_desde` = hoy (America/Guayaquil). Si ya había un cambio programado para el mes siguiente, la nueva fila lo reemplaza porque es más reciente. | CA-03 a CA-09 |

Regla de lectura del valor vigente en una fecha: la fila de esa clave con el `vigente_desde` más reciente que no supere la fecha; si hay varias con el mismo `vigente_desde`, la última creada. Esta regla vive en un servicio de Aplicación (`ParametrosDelModulo::valorVigente(clave, fecha)`) que usan las demás specs del módulo.

Mensajes que muestra el frontend:
- 409: «Otra persona cambió un parámetro mientras editabas. Recarga para ver los valores actuales.»
- 422 (categoría): «No puede ser menor que la puntuación de la categoría «{nombre}» ({puntos} puntos).»

Pruebas (Pest):
- GET sin filas devuelve los seis valores por defecto (sensibilidad 2, Normal); con una categoría activa de 150 puntos, puntos por colaborador vale 150.
- PUT fuera de rango, decimal o texto: 422 y no se guarda nada.
- PUT puntos por colaborador menor que la categoría más alta: 422 con el nombre.
- PUT puntos por colaborador: `valorVigente` de hoy devuelve el valor anterior y el del día 1 del mes siguiente, el nuevo.
- PUT plazo de anulación: aplica hoy.
- PUT `sensibilidad_radar` con 0 o 4: 422.
- Dos cambios sucesivos dejan dos filas; ninguna se modifica.
- `version` desactualizada: 409 y no se guarda nada.
- Usuario sin `admin`: 403. Sin sesión: 401.

## 4. Frontend
- Referencia visual: tablero «Parámetros del módulo — Gestor de Talento» del prototipo (`ParametrosModulo.dc.html`). Se copia a `design/` antes de implementar la interfaz.
- Ruta `/compensaciones/parametros`, pestaña «Parámetros» del módulo, visible solo para quien tiene `parametros.gestionar`.
- Una tarjeta por parámetro con: título, explicación, campo numérico, rango y cuándo aplica. En puntos por colaborador se muestra el ejemplo «Un equipo de 10 personas tendría X puntos al mes» y el aviso de que aplica desde el mes siguiente; si hay un valor programado, se muestra.
- Bajo cada tarjeta: «Último cambio: {nombre}, {fecha} (de {anterior} a {nuevo})».
- Botones «Descartar» y «Guardar» para todos los cambios juntos; «Guardar» se bloquea mientras hay un envío pendiente o no hay cambios.
- Estados: cargando, error con «Reintentar» y éxito con mensaje de confirmación. Errores junto a cada campo; los valores escritos se conservan.
- Validación con Zod (enteros y rangos) y la misma validación en el servidor.
- Pruebas: Vitest del formulario (rangos, error de categoría, 409) y un E2E: el Gestor cambia el plazo de anulación y ve el último cambio.
- Tarjeta «Sensibilidad del radar de clima»: tres opciones de radio, sin números, con su explicación:
  - **Baja:** «Solo alerta en casos claros. Menos avisos.»
  - **Normal** (recomendada): «Equilibrio entre detectar a tiempo y no saturar al líder.»
  - **Alta:** «Detecta antes las señales leves. Más avisos.»
  - Nota: «Aplica desde el siguiente cálculo semanal. Los líderes verán que la regla cambió en esa medición.» Los umbrales de cada opción viven en el código (07.2).

## 5. Relación con otros módulos
- Shared: `getNombresDeUsuarios` para el nombre de quien hizo el último cambio.
- No escribe en tablas de otros módulos.
