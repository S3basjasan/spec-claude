# Contrato de interfaz: Adecuación a puesto futuro

Fuente visual **exacta**: `design/AdecuacionDiagnostico.dc.html` (estructura común del administrador, pestaña Diagnóstico), `AdecuacionComparar.dc.html` (pestaña Comparar candidatos), `AdecuacionMapa.dc.html` (pestaña Mapa de talento) y `AdecuacionColaborador.dc.html` (colaborador). Layout, tokens, jerarquía, textos y estados salen de esos archivos; las diferencias con la spec están en la tabla de `plan.md` y en `design/DESIGN.md`. Todo texto visible va en español y centralizado en `text.ts`. Cada vista implementa loading (Skeleton), vacío, error con «Reintentar» y éxito; WCAG 2.2 AA, 320–1440 px, temas claro y oscuro.

## Estructura común de la página del administrador (`/adecuacion`)
1. Cabecera de página: sobretítulo «Desarrollo y Formación», título «Adecuación a puesto futuro», descripción «Compara a una persona con un puesto objetivo en experiencia, conocimientos y competencias.» (sin botón «Ver como colaborador»).
2. Aviso permanente (`Alert` neutro): «Esta evaluación es una guía de desarrollo; no implica la promesa de un puesto.» y, en la pestaña Diagnóstico, la nota de que la preparación depende de los meses de cierre y del umbral de {n} meses.
3. Mensaje de éxito cerrable («Cerrar») y mensaje de error (`role="alert"`).
4. Pestañas (`role="tablist"`, estilo `tabbtn`; la activa va en `?pestana=`): **Diagnóstico**, **Comparar candidatos**, **Mapa de talento** (como el tablero) y, con su permiso, **Evaluaciones** y **Parámetros** (añadidas). Una pestaña sin permiso no se muestra.

## Pestaña «Diagnóstico» (Selección → Diagnóstico → Confirmación)
**Selección** (tarjeta con dos campos): `Colaborador` (Combobox con búsqueda: «Nombre · Departamento») y `Puesto objetivo` (Select; primera opción «Siguiente nivel de su ruta (por defecto)» —con varias inscripciones Activas, una opción por inscripción «Ruta · Nivel N: Puesto» y la elección es del administrador— y luego «Puesto directo: …» con la lista de puestos activos). Con puesto directo aparece `Experiencia requerida (años)` opcional. Si el colaborador no tiene objetivo por defecto: «{Nombre} no tiene un siguiente nivel en su ruta. Elige un puesto objetivo (puede ser un desplazamiento lateral).»

**Diagnóstico** (se muestra al elegir colaborador y objetivo; se calcula con `POST /previsualizar`):
- Resumen (`section aria-label="Resumen"`): etiqueta de tipo de cambio, «Puesto actual → Puesto objetivo», línea «{Colaborador} · {Departamento} · {n} años de práctica», métricas «Adecuación al puesto actual {x} %» y «Adecuación al puesto objetivo {y} %» (cumplimiento informativo, no puntaje) y «Nivel de preparación» con insignia.
- Leyenda de barras: «Requisito del puesto actual», «Evaluación de la persona», «Requisito del puesto futuro».
- Tres paneles `section` en este orden: **Experiencia**, **Conocimientos**, **Competencias**. Cabecera «{Título}» + «actual {pa} % · futuro {pf} %»; lista de requisitos, cada uno con nombre, insignia de estado (Con brecha, Cumple, Supera, Sin evaluación) y resumen («Nivel actual: C · Requerido: B · Diferencia: −1»; en experiencia, años) y las barras triples. Conocimientos desactivados: panel con aviso informativo (sin filas).
- «Brechas y tiempo estimado de cierre»: texto de ayuda con el estado del cierre (p. ej. «Faltan {n} tiempos por ingresar»), tabla `Dimensión · Elemento · Brecha · Tiempo estimado (meses)` con `input number` (`min=1`, `max=60`, `aria-label="Tiempo estimado de cierre en meses: {elemento}"`, placeholder «Meses»). Sin brechas: «No hay brechas: la persona cumple todos los requisitos del puesto objetivo.»
- Avisos informativos (`Alert` neutro): requisitos «Sin evaluación» y competencias o conocimientos inactivos omitidos («Se omitieron {n} … inactivos»).

**Confirmación** (bloque de acciones): preparación calculada (insignia), campo `Observaciones` (contador `n/500`), botón primario «Registrar esta evaluación» (abre diálogo de confirmación accesible con la preparación y el resumen; bloqueado mientras falten tiempos o haya envío pendiente), y los botones «Crear plan de desarrollo» e «Incluir en Programa de Talentos» (deshabilitados con su motivo: «No hay brechas» o «Disponible cuando exista el Plan de Desarrollo Individual»). Tras guardar: mensaje «Evaluación registrada como {código}.».

**Historial de adecuaciones de {Colaborador}** (al pie): «Cada evaluación es un registro inmutable: no se edita ni se elimina.»; tabla `Fecha · Puesto objetivo · Exp. / Conoc. / Comp. · Preparación`; vacío: «Aún no hay evaluaciones registradas para esta persona.»

## Pestaña «Comparar candidatos»
- Tarjeta «Puesto y pesos»: `Puesto a cubrir` (Select), pesos `Experiencia (%)`, `Conocim. (%)`, `Compet. (%)` (enteros 0–100) y texto de suma «Suma: {n} %» (error si ≠ 100); nota «Puntaje = suma ponderada del cumplimiento por dimensión. Los pesos son configurables; solo aplican a esta consulta.»
- Tarjeta «Candidatos ({n})»: «Selecciona al menos dos.» y chips seleccionables (`aria-pressed`).
- «Ranking para {Puesto}»: tabla `N.º · Candidato (puesto actual) · Exp. / Conoc. / Comp. · Puntaje ponderado (barra + «{x} %») · Preparación · Acción («Ver diagnóstico»)`. Vacío: «Selecciona al menos dos candidatos y pesos que sumen 100 para ver el ranking.»
- Bajo el ranking: bloques «Preparación incompleta (sin puntaje)» y «Sin adecuación para este puesto» con sus nombres y, en el segundo, un enlace «Generar adecuación».

## Pestaña «Mapa de talento»
- Cuatro indicadores: Lista, En desarrollo, No lista, Incompleta (cantidad y nota).
- Filtros (en la URL): `Departamento`, `Ruta`, `Preparación`, «Limpiar filtros» (visible con filtros activos); resumen `role="status"` («{n} personas»).
- Tabla `Colaborador (departamento) · Ruta · Puesto actual → objetivo · Exp. / Conoc. / Comp. · Preparación · Brechas · Tiempo máx. (meses) · Programa de Talentos · Acciones` con paginación; acciones «Diagnóstico» (abre el detalle) e «Incluir» (deshabilitado con motivo). Vacío: «Ninguna persona coincide con los filtros.» Nota al pie: «Se muestra la adecuación vigente de cada colaborador por puesto objetivo.»

## Pestaña «Evaluaciones» (añadida; patrón Listado de `DESIGN.md`)
Filtros `Desde`, `Hasta`, `Colaborador`, `Puesto objetivo`, `Preparación` (en la URL), tabla `Código · Fecha · Colaborador · Puesto objetivo · Preparación · Brechas` con paginación; cada fila abre `/adecuacion/evaluaciones/:id` (detalle de solo lectura con los tres paneles y la auditoría «Generada por {usuario} el {fecha}»). Sin botones de editar ni eliminar.

## Pestaña «Parámetros» (añadida; patrón Formulario)
Formulario `Competencias (%)`, `Conocimientos (%)`, `Experiencia (%)` (suma 100, con indicador en vivo), `Umbral de preparación (meses)` y «Guardar cambios»; tabla de historial `Fecha · Usuario · Campo · Valor anterior · Valor nuevo`. Errores 422 por campo.

## Vista del colaborador (`/adecuacion` sin permisos de gestión): «Mi adecuación»
- Cabecera: «Desarrollo y Formación», título «Mi adecuación a {Puesto objetivo}», descripción «Tu nivel actual frente a lo que pide el puesto, en experiencia, conocimientos y competencias.»
- Aviso: «**Esta evaluación es una guía de desarrollo; no implica la promesa de un puesto.** Solo ves tu propia adecuación.»
- Selector `Puesto a comparar` entre sus adecuaciones vigentes (en `?puesto=`; la más reciente primero); «Nivel de preparación» con insignia; botón «Mi ruta de carrera» (cuando existe).
- Leyenda «Tu nivel actual» / «Requisito del puesto» y tres tarjetas (Experiencia, Conocimientos, Competencias; mismo orden del tablero) con porcentaje de cumplimiento de la dimensión, filas con nombre, diferencia («−1») y resumen («Nivel actual: C · Requerido: B · Diferencia: −1»). Conocimientos desactivados: tarjeta con aviso informativo.
- «Lo que me falta» (`section`): texto de ayuda, lista de brechas con dimensión, texto de la brecha, **tiempo estimado de cierre** y, en competencias/conocimientos, el botón «Ver cursos sugeridos» (enlace al Catálogo Formativo filtrado por el elemento, `aria-label="Ver cursos sugeridos para {elemento}"`; si el elemento no tiene oferta, texto «Aún no hay cursos sugeridos»); en experiencia: «Se desarrolla con práctica; revisa tu plan de desarrollo». Sin brechas: «Cumples todos los requisitos de este puesto.»
- «Programa de Talentos»: texto informativo «Por ahora no estás incluido en un Programa de Talentos.» (sin simulación).
- Vacío (sin puesto objetivo o sin adecuación): «Aún no tienes una adecuación. Cuando Talento Humano la genere, la verás aquí.»
- **No muestra** puntajes de comparación, rankings ni datos de otras personas.

## Componentes del design system reutilizados
Tabs, Select/Combobox, Input numérico, Textarea con contador, Button primario/secundario, Badge (preparación/estado/tipo), Card, Table con paginación, Alert (error y neutro), Skeleton, Modal de confirmación, barras de progreso triples (misma construcción que las barras de comparación de la feature 019). Insignias con los colores semánticos del design system: Lista = éxito, En desarrollo = información/acento, No lista = peligro, Incompleta = neutro; Con brecha = peligro, Cumple = éxito, Supera = acento, Sin evaluación = neutro.

## Estados por vista
| Vista | loading | vacío | error | sin permiso |
|---|---|---|---|---|
| Diagnóstico | Skeleton al calcular | «Elige un colaborador y un puesto objetivo para ver el diagnóstico» | `Alert` con «Reintentar»; 409 «Recargar diagnóstico» | pestaña oculta |
| Comparar | Skeleton | texto del ranking | `Alert` + «Reintentar»; 422 de pesos junto al campo | pestaña oculta |
| Mapa | Skeleton de tabla | «Ninguna persona coincide…» | `Alert` + «Reintentar» | pestaña oculta |
| Evaluaciones / Parámetros | Skeleton | «No hay evaluaciones» / historial vacío | `Alert` + «Reintentar» | pestaña oculta |
| Mi adecuación | Skeleton de tarjetas | estado vacío del colaborador | `Alert` + «Reintentar» | redirección a inicio |
