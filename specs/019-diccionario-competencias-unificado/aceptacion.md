# Casos de aceptación: Diccionario de competencias unificado

Lista para que una persona de Talento Humano (o quien pidió la feature) compruebe a mano que la funcionalidad resuelve lo que se pidió. Está organizada **por historia de usuario y por criterio**: el número de cada criterio (`1.1` a `6.5`) es el de los escenarios de aceptación de [spec.md](spec.md), en el mismo orden. Un criterio puede tener más de un caso (`1.1a`, `1.1b`).

## Antes de empezar

| Dato | Valor |
|---|---|
| Versión probada | (commit o fecha de la prueba) |
| Persona que prueba | |
| Fecha | |
| Navegador y tamaño de pantalla | |
| Base de datos | Recién sembrada con `php artisan migrate:fresh --seed` (7 competencias: COMP-0001 a COMP-0007; Adaptabilidad está inactiva) |
| Usuarios de prueba | Administrador: `admin@hrms.test`. Usuario sin rol de administrador: `usuario@hrms.test`. La contraseña de ambos la define `SEED_PASSWORD` en `backend/.env` (si no está definida, se usa la de `backend/config/hrms.php`) |
| Cómo abrir la pantalla | Iniciar sesión y elegir **Competencias** en el menú lateral |

Si un caso modifica datos, el siguiente puede depender del anterior: ejecuta los casos **en el orden en que aparecen**. Para volver al estado inicial: `cd backend && php artisan migrate:fresh --seed`.

**Cómo registrar el resultado:** escribe **Aprobado** o **Rechazado** en la columna *Resultado* y anota en *Observaciones* lo que viste si no fue lo esperado.

**Columna "Respaldo automático":** qué prueba ya verifica lo mismo sin intervención humana (**U** unitaria, **I** integración, **S** sistema/E2E en `frontend/e2e/competencias.spec.ts`). Este documento es la comprobación humana; el respaldo automático sirve para detectar regresiones, no lo reemplaza.

## Resumen de cobertura

| Historia | Criterios | Casos manuales |
|---|---|---|
| 1. Consultar | 8 | 12 |
| 2. Crear | 9 | 10 |
| 3. Editar por secciones | 8 | 9 |
| 4. Activar y desactivar | 7 | 8 |
| 5. Ver y cambiar la escala | 7 | 8 |
| 6. Trazabilidad y otros módulos | 5 | 7 |
| Transversal (pantallas) | — | 3 |
| **Total** | **44** | **57** |

---

## Historia 1 · Consultar el diccionario y el detalle de una competencia

| Caso | Criterio | Pasos | Resultado esperado | Respaldo automático | Resultado | Observaciones |
|---|---|---|---|---|---|---|
| 1.1a | **1.1** Usuario ve solo las activas, ordenadas por código | Iniciar sesión como **usuario** y abrir Competencias | Se ven 6 competencias (COMP-0001 a COMP-0006) en orden de código, una por fila, con código, nombre y tipo. **No** aparece Adaptabilidad. Debajo dice "6 competencias" | I, S | | |
| 1.1b | **1.1** …y sin acciones de gestión | Mirar la pantalla | No hay botón "Nueva competencia", ni botones de activar/desactivar, ni lápices de edición dentro de las tarjetas | I, S | | |
| 1.2 | **1.2** Administrador ve activas e inactivas | Cerrar sesión; iniciar como **administrador** y abrir Competencias | Se ven las 7 competencias; Adaptabilidad con el nombre atenuado. Debajo dice "7 competencias". No hay filtro de estado | I, S | | |
| 1.3a | **1.3** Búsqueda sin importar tildes ni mayúsculas | Escribir `lidérazgo` en "Buscar por nombre" | Mientras se escribe queda solo Liderazgo; debajo dice "1 competencia" | U, I, S | | |
| 1.3b | **1.3** Búsqueda combinada con el filtro por tipo | Con la búsqueda vacía, elegir tipo **Específica** y luego escribir `proyectos`; después pulsar "Limpiar filtros" | Con ambos filtros solo queda Gestión de proyectos. Al limpiar vuelven todas | I, S | | |
| 1.4 | **1.4** Tarjeta abre en la pestaña Competencia | Pulsar la tarjeta de **Liderazgo** | Se despliega con tres pestañas (Competencia, Comportamientos, Preguntas) y empieza en Competencia: descripción completa y los cuatro grados con su descripción, nombrados con la escala de esa competencia | I, S | | |
| 1.5a | **1.5** Comportamientos por grado | Pestaña **Comportamientos** | Cuatro grados, cada uno con exactamente cuatro comportamientos en viñetas | I, S | | |
| 1.5b | **1.5** Preguntas numeradas | Pestaña **Preguntas** | Cuatro preguntas numeradas del 1 al 4 | I, S | | |
| 1.6a | **1.6** Varias tarjetas abiertas | Abrir otra tarjeta sin cerrar la primera; cambiar la pestaña de una | Las dos siguen abiertas y cada una conserva su propia pestaña | I | | |
| 1.6b | **1.6** Cerrar una tarjeta | Volver a pulsar la tarjeta abierta | El detalle se oculta y la otra sigue abierta | — | | |
| 1.7 | **1.7** Sin resultados y recuento | Escribir `zzzz` | Aparece "Ninguna competencia coincide con los filtros." con el botón "Limpiar filtros"; el recuento bajo la lista refleja lo encontrado | I, S | | |
| 1.8 | **1.8** Inactiva consultable por quien llega desde otro registro | Como administrador, abrir **Adaptabilidad** (inactiva) y recorrer las tres pestañas. Luego, en **Puestos**, abrir uno con una competencia asignada y comprobar que se muestra su nombre y su grado requerido | La inactiva muestra descripción, grados, comportamientos y preguntas completos; el puesto muestra la competencia asignada sin errores | I, S (API) | | |

## Historia 2 · Crear una competencia completa · como administrador

| Caso | Criterio | Pasos | Resultado esperado | Respaldo automático | Resultado | Observaciones |
|---|---|---|---|---|---|---|
| 2.1 | **2.1** Orden de campos y escala por defecto | Pulsar "Nueva competencia" | Orden: Nombre y Tipo, Descripción, Escala, Grados, Comportamientos, Preguntas. La escala **Letras** viene elegida | I | | |
| 2.2 | **2.2** Los rótulos siguen la escala | Elegir **Números** | Los cuatro grados pasan a llamarse Nivel 1 a Nivel 4 | I | | |
| 2.3 | **2.3** Categorías prellenadas y editables | Elegir **Categorías** | Aparecen cuatro campos con Excelente, Muy buena, Buena y Normal, que se pueden cambiar. Los rótulos de los grados usan esos nombres | I, S | | |
| 2.4 | **2.4** Nombres de categoría vacíos o repetidos | Escribir el mismo nombre en dos categorías (por ejemplo `Buena` y ` buena `) y guardar; repetir dejando una categoría vacía | Se rechaza con "Escribe las cuatro categorías, sin repetir nombres." | U, I | | |
| 2.5 | **2.5** Crear una competencia completa | Completar todo (nombre `Pensamiento estratégico`, tipo Específica, escala Categorías con `Maestría`, `Avanzado`, `Competente`, `Inicial`, los 4 grados, los 16 comportamientos y las 4 preguntas) y guardar | Aviso "Competencia creada correctamente." con el botón Aceptar. Sale en la lista con el código COMP-0008, activa | U, I, S | | |
| 2.6a | **2.6** Formulario vacío | Abrir el formulario y pulsar "Guardar competencia" sin escribir nada | No se guarda nada. Salen los errores junto a los campos ("Ingresa el nombre.", "Selecciona el tipo.", "Completa los cuatro grados.", etc.) | U, I, S | | |
| 2.6b | **2.6** Un solo comportamiento o pregunta vacíos | Completar todo salvo **un** comportamiento (y en otro intento, salvo una pregunta) y guardar | No se guarda; el error aparece junto a ese campo | U, I | | |
| 2.7 | **2.7** Nombre duplicado | Crear otra con el nombre `LIDÉRAZGO ` | Se rechaza con "Ya existe una competencia con este nombre." junto al campo, y lo escrito se conserva | U, I | | |
| 2.8 | **2.8** Cancelar no crea nada | Abrir el formulario, escribir algo y pulsar "Cancelar" (o Escape) | El formulario se cierra y no se crea nada | I | | |
| 2.9 | **2.9** Sin permisos no se crea | Como **usuario**, buscar "Nueva competencia"; luego, con su sesión, intentar un `POST /api/competencias` (por ejemplo desde la consola del navegador) | No existe el botón y el API responde `403` | I, S | | |

## Historia 3 · Editar una competencia por secciones · como administrador

| Caso | Criterio | Pasos | Resultado esperado | Respaldo automático | Resultado | Observaciones |
|---|---|---|---|---|---|---|
| 3.1 | **3.1** Editar competencia y grados | Competencia de prueba, pestaña Competencia, "Editar grados de desarrollo": cambiar nombre, descripción, tipo y una descripción de grado; guardar | "Competencia actualizada correctamente." y los cambios se ven. El código no cambia | I, S | | |
| 3.2 | **3.2** Editar comportamientos | En Liderazgo, pestaña Comportamientos, "Editar comportamientos", cambiar un texto y guardar | "Comportamientos actualizados correctamente." y el texto nuevo se ve en la pestaña | I, S | | |
| 3.3 | **3.3** Editar preguntas | Pestaña Preguntas, "Editar preguntas", cambiar la pregunta 1 y guardar | "Preguntas actualizadas correctamente." y el cambio se ve | I, S | | |
| 3.4 | **3.4** Dato inválido: no guarda | En cualquiera de las tres ediciones, vaciar un campo y guardar | No se guarda nada y el mensaje sale junto al campo | U, I | | |
| 3.5 | **3.5** Editar una inactiva | Abrir Adaptabilidad y editar sus preguntas | Se permite y la competencia sigue inactiva | I, S | | |
| 3.6a | **3.6** Nombre de otra competencia | Editar Liderazgo y poner el nombre de otra (por ejemplo `Comunicación efectiva`) | Se rechaza con "Ya existe una competencia con este nombre." | I | | |
| 3.6b | **3.6** Conservar el propio nombre | Editar Liderazgo cambiando solo la descripción y dejando el nombre igual | Se guarda sin error | I | | |
| 3.7 | **3.7** Usuario no ve edición | Como **usuario**, abrir una tarjeta y recorrer las pestañas | No hay ningún lápiz ni botón de editar | I, S | | |
| 3.8 | **3.8** Conflicto de edición | Abrir "Editar preguntas" de una competencia en una **segunda ventana** o navegador (como administrador); guardar un cambio en la primera; intentar guardar en la segunda | La segunda muestra "Esta competencia cambió mientras la editabas. Recarga para ver la versión actual." con el botón "Recargar competencia"; al recargar ve el cambio de la primera | U, I, S | | |

## Historia 4 · Activar y desactivar una competencia · como administrador

| Caso | Criterio | Pasos | Resultado esperado | Respaldo automático | Resultado | Observaciones |
|---|---|---|---|---|---|---|
| 4.1 | **4.1** Confirmación al desactivar | Pulsar el botón rojo de **Gestión de proyectos** | Diálogo "¿Desactivar «Gestión de proyectos»?" que avisa que no podrá asignarse en registros nuevos | I, S | | |
| 4.2a | **4.2** Cancelar | Pulsar "Cancelar" | No cambia nada | S | | |
| 4.2b | **4.2** Confirmar la desactivación | Repetir y confirmar con "Desactivar" | Aviso "Competencia desactivada."; la tarjeta queda con el nombre atenuado y el botón pasa a "Activar" | U, I, S | | |
| 4.3 | **4.3** Activar sin confirmación | Pulsar el botón verde de Gestión de proyectos | Se activa de inmediato con "Competencia activada." y sin diálogo | I, S | | |
| 4.4 | **4.4** Otros usuarios dejan de verla | Desactivarla de nuevo; en otra ventana iniciar como usuario y abrir Competencias | Gestión de proyectos ya no aparece. (Después, reactivarla) | I, S | | |
| 4.5 | **4.5** Asignaciones se conservan | Ver el caso 6.5; aquí comprobar que, tras desactivar una competencia asignada a un puesto, el puesto sigue mostrándola | La asignación sigue y la competencia se puede consultar | I | | |
| 4.6 | **4.6** No existe eliminar | Revisar todos los botones, y con la sesión de administrador intentar un `DELETE /api/competencias/1` | No hay acción de eliminar y el API responde `404` o `405` | I, S (API) | | |
| 4.7 | **4.7** Activar una incompleta *(condicional)* | Solo si hay una competencia heredada sin comportamientos o preguntas (inactiva): intentar activarla | Se rechaza con "No se puede activar: faltan …" indicando qué falta. Tras completar esa sección, se puede activar. Con la base recién sembrada no hay ninguna: marcar **No aplica** | U, I | | |

## Historia 5 · Ver y cambiar la escala de grados

### Como usuario sin rol de administrador

| Caso | Criterio | Pasos | Resultado esperado | Respaldo automático | Resultado | Observaciones |
|---|---|---|---|---|---|---|
| 5.1 | **5.1** Vista previa sin guardar | En el detalle de Liderazgo, pestaña Competencia, elegir **Números** en "Ver con escala:". Repetir en la pestaña Comportamientos | Los grados pasan a Nivel 1 a Nivel 4 y el título dice "(vista previa)". Las descripciones no cambian. Al recargar (F5) vuelve Grado A a D | I, S | | |
| 5.2 | **5.2** Descartar | Pulsar "Descartar" | Vuelve la escala guardada (Grado A a D) | I, S | | |
| 5.4 | **5.4** Usuario no ve "Aplicar" | Con una vista previa activa, mirar los botones | Aparece "Descartar" pero **no** "Aplicar esta escala" | I, S | | |

### Como administrador

| Caso | Criterio | Pasos | Resultado esperado | Respaldo automático | Resultado | Observaciones |
|---|---|---|---|---|---|---|
| 5.3 | **5.3** Aplicar la escala | En Orientación a resultados, elegir **Números** y pulsar "Aplicar esta escala" | "Escala de grados actualizada correctamente." y los grados quedan como Nivel 1 a 4 | U, I, S | | |
| 5.5 | **5.5** Solo cambian los nombres | Recargar (F5), abrir la competencia y comparar descripciones y comportamientos con lo que había antes. Ver también el caso 6.5a | Sigue en Números; descripciones y comportamientos no cambiaron; el grado requerido en Puestos tampoco | I, S (parcial) | | |
| 5.6a | **5.6** Categorías sin nombres propios usa los de por defecto | Tomar una competencia creada con escala Letras (por ejemplo Liderazgo), elegir **Categorías** en la vista previa y aplicar | Los grados se llaman Excelente, Muy buena, Buena y Normal | I | | |
| 5.6b | **5.6** …y se pueden cambiar luego | En esa competencia, "Editar grados de desarrollo": cambiar los cuatro nombres de categoría y guardar | Los grados usan los nombres nuevos | I | | |
| 5.7 | **5.7** Conserva los nombres propios | En la competencia creada en 2.5, elegir otra escala, aplicarla y volver a aplicar Categorías | Vuelven los nombres propios (Maestría, Avanzado, Competente, Inicial) | I | | |

## Historia 6 · Trazabilidad y uso por otros módulos

Las pantallas de Competencias **no muestran** la auditoría ni el historial (la especificación no pide una pantalla para ellos). Los criterios 6.1 a 6.3 se comprueban por el API y la base de datos.

| Caso | Criterio | Pasos | Resultado esperado | Respaldo automático | Resultado | Observaciones |
|---|---|---|---|---|---|---|
| 6.1 | **6.1** Quién la creó y cuándo | Como administrador, consultar `GET /api/competencias/{id}` de la competencia creada en 2.5 (consola del navegador con la sesión iniciada, o un cliente HTTP con el token) | La respuesta incluye `auditoria.created_by` con el nombre de quien la creó y `created_at` con la fecha | U, I | | |
| 6.2 | **6.2** Quién la modificó por última vez | Tras editar la misma competencia (3.1, 3.2, 3.3 o 5.3), consultar de nuevo el mismo `GET` | `auditoria.updated_by` y `updated_at` reflejan la última edición. Como **usuario**, la respuesta **no** trae la clave `auditoria` | I | | |
| 6.3a | **6.3** Historial de estado | Tras los casos 4.2b, 4.3 y 4.4, mirar la tabla `competencia_estado_historial` (por ejemplo con `php artisan tinker`: `DB::table('competencia_estado_historial')->latest('id')->get()`) | Una fila por cada cambio, con valor anterior, valor nuevo, usuario y fecha | U, I | | |
| 6.3b | **6.3** Historial de escala | Tras el caso 5.3, mirar `competencia_escala_historial` | Una fila con `escala_anterior`, `escala_nueva`, `user_id` y `created_at` | U, I | | |
| 6.4 | **6.4** Otros módulos solo ofrecen las activas | Con Adaptabilidad inactiva, ir a **Puestos** e intentar asignarla a un puesto nuevo o existente | No se ofrece para asignar | I | | |
| 6.5a | **6.5** Asignaciones conservan el grado | En **Puestos**, abrir uno con competencias y anotar el grado requerido de una; cambiar la escala de esa competencia (caso 5.3) y volver a mirar el puesto | El grado requerido del puesto no cambió | I | | |
| 6.5b | **6.5** Asignaciones a inactivas se conservan | Con una competencia asignada a un puesto, desactivarla (4.2b) y volver al puesto | La asignación sigue, completa y consultable | I | | |

## Transversal · uso en distintas pantallas

| Caso | Qué se comprueba | Pasos | Resultado esperado | Resultado | Observaciones |
|---|---|---|---|---|---|
| T1 | Pantalla angosta | Reducir la ventana a unos 390 px (o abrir en el teléfono) | Una competencia por fila, sin barra de desplazamiento horizontal en la página; las pestañas se desplazan dentro de la tarjeta | | |
| T2 | Tema oscuro | Pulsar el botón de tema | Todo se lee bien: tarjetas, pestañas, formularios y avisos | | |
| T3 | Solo con teclado | Navegar con Tab y Enter | Se puede abrir una tarjeta, cambiar de pestaña con las flechas, abrir y cerrar un formulario con Escape, y el foco siempre se ve | | |

## Resultado final

| | |
|---|---|
| Casos aprobados | ___ de 57 |
| Casos rechazados | ___ |
| No aplica | ___ |
| Decisión | ☐ Aceptada  ☐ Aceptada con observaciones  ☐ Rechazada |
| Observaciones generales | |
| Aprobado por | |
| Fecha | |
| Firma | |

**Regla de aceptación:** la funcionalidad se acepta cuando todos los criterios (44) tienen al menos un caso **Aprobado** o **No aplica** justificado.

Tras la prueba con usuarios, anota aquí cuántas personas completaron la creación de una competencia **sin ayuda** (criterio SC-009: al menos el 90 %): ___ de ___.
