import type { EstadoInscripcion, EstadoRuta, FuenteNoDisponible, Grado, PestanaRuta, TipoRuta } from './api'

const MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic']

/** «5 mar 2026» a partir de una fecha ISO («2026-03-05» o «2026-03-05T10:00:00-05:00»). */
function fecha(iso: string | null): string {
  if (!iso) return '—'
  const [anio, mes, dia] = iso.slice(0, 10).split('-').map(Number)
  if (!anio || !mes || !dia) return '—'
  return `${dia} ${MESES_CORTOS[mes - 1]} ${anio}`
}

/** Años de experiencia: «Sin experiencia», «1 año», «2,5 años»; «—» si el nivel no la define. */
function anios(valor: number | null): string {
  if (valor === null) return '—'
  if (valor === 0) return 'Sin experiencia'
  const texto = String(valor).replace('.', ',')
  return `${texto} ${valor === 1 ? 'año' : 'años'}`
}

/** Textos de las Rutas de carrera (todo texto visible va aquí, en español). */
export const rutasText = {
  navLabel: 'Rutas de carrera',
  loading: 'Cargando…',
  noDisponible: 'No disponible',
  sinDato: '—',
  aviso: 'Esta ruta es una guía de desarrollo; no implica la promesa de un puesto.',
  fecha,
  anios,
  cerrar: 'Cerrar',
  cancelar: 'Cancelar',
  guardar: 'Guardar',

  tipos: {
    Especialista: 'Especialista',
    Gerencial: 'Gerencial',
    Interareas: 'Interáreas',
    JovenesProfesionales: 'Jóvenes profesionales',
  } satisfies Record<TipoRuta, string>,
  ayudaTipo: {
    Especialista: 'Profundiza en un puesto técnico dentro de una misma área.',
    Gerencial: 'Asciende hacia puestos con responsabilidad sobre personas y presupuesto.',
    Interareas: 'Recorre puestos de más de un área para ampliar la experiencia.',
    JovenesProfesionales: 'Camino de crecimiento pensado para recién egresados.',
  } satisfies Record<TipoRuta, string>,
  estados: { Borrador: 'Borrador', Vigente: 'Vigente', Archivada: 'Archivada' } satisfies Record<EstadoRuta, string>,
  estadosInscripcion: { Activa: 'Activa', Finalizada: 'Finalizada', Retirada: 'Retirada' } satisfies Record<EstadoInscripcion, string>,

  error: {
    title: 'No se pudo cargar la información',
    description: 'Revisa tu conexión e inténtalo de nuevo.',
    retry: 'Reintentar',
  },
  noPermission: {
    title: 'No tienes acceso a esta sección',
    description: 'Tu usuario no tiene permiso para ver esta pantalla.',
    back: 'Volver al inicio',
  },
  fuentes: {
    estructura: 'No se pudo consultar Estructura Empresarial: se muestran los datos propios de la ruta, sin competencias ni requisitos.',
    personal: 'No se pudo consultar Gestión de Personal: algunos datos de las personas pueden faltar.',
    reintentar: 'Reintentar',
  } satisfies Record<FuenteNoDisponible, string> & { reintentar: string },
  conflicto: {
    mensaje: 'Otro administrador modificó la ruta mientras la editabas; recarga para ver los cambios.',
    recargar: 'Recargar',
  },

  lista: {
    eyebrow: 'Desarrollo y Formación',
    title: 'Rutas de carrera',
    subtitle: 'Caminos posibles de crecimiento: niveles, puestos vinculados y requisitos.',
    nueva: 'Nueva ruta',
    kpis: {
      vigentes: 'Rutas vigentes',
      vigentesNota: (total: number) => `${total} ${total === 1 ? 'ruta' : 'rutas'} en total`,
      participantes: 'Participantes activos',
      participantesNota: 'Inscritos en rutas vigentes',
      borradores: 'Rutas en borrador',
      borradoresNota: 'Pendientes de publicar',
    },
    filtros: {
      buscar: 'Buscar',
      buscarPlaceholder: 'Nombre de la ruta',
      tipo: 'Tipo',
      departamento: 'Departamento',
      estado: 'Estado',
      todos: 'Todos',
      todas: 'Todos',
      limpiar: 'Limpiar filtros',
    },
    resumen: (mostradas: number, total: number) => `Mostrando ${mostradas} de ${total} rutas`,
    cuenta: (n: number) => `${n} ${n === 1 ? 'ruta' : 'rutas'}`,
    vacio: { title: 'Ninguna ruta coincide con los filtros.', description: 'Cambia los filtros o crea una ruta nueva.' },
    sinRutas: { title: 'Todavía no hay rutas de carrera', description: 'Crea la primera ruta y agrega sus niveles desde el editor.' },
    sinDepartamento: 'Todas las áreas',
    tarjeta: {
      nivelPalabra: (n: number) => (n === 1 ? 'nivel' : 'niveles'),
      participantePalabra: (n: number) => (n === 1 ? 'participante' : 'participantes'),
      abrir: 'Abrir',
      abrirRuta: (nombre: string) => `Abrir ruta ${nombre}`,
      sinDescripcion: 'Sin descripción.',
    },
  },

  formulario: {
    tituloCrear: 'Nueva ruta de carrera',
    tituloEditar: 'Editar ruta de carrera',
    descripcion: 'La ruta se crea en estado Borrador. Los niveles se agregan en el editor.',
    nombre: 'Nombre de la ruta',
    tipo: 'Tipo de ruta',
    departamento: 'Departamento',
    departamentoInterareas: 'No aplica a las rutas Interáreas',
    sinDepartamento: 'Sin departamento',
    descripcionCampo: 'Descripción',
    crear: 'Crear ruta',
    guardarCambios: 'Guardar cambios',
    notaCrear: 'La ruta se crea en estado Borrador. Los niveles se agregan en el editor.',
    errorGuardar: 'No se pudo guardar la ruta.',
    creada: 'Ruta creada en estado Borrador. Agrega los niveles.',
    actualizada: 'Datos de la ruta actualizados.',
  },

  editor: {
    volver: 'Rutas de carrera',
    editar: 'Editar ruta',
    meta: (departamento: string | null, niveles: number, participantes: number) =>
      `${departamento ?? 'Todas las áreas'} · ${niveles} ${niveles === 1 ? 'nivel' : 'niveles'} · ${participantes} ${participantes === 1 ? 'participante' : 'participantes'}`,
    auditoria: (creador: string | null, fechaCreacion: string | null, modificador: string | null, fechaModificacion: string | null) =>
      `Creada por ${creador ?? 'No disponible'} el ${fecha(fechaCreacion)} · Última modificación por ${modificador ?? 'No disponible'} el ${fecha(fechaModificacion)}`,
    estadoLabel: 'Estado de la ruta',
    seccion: 'Sección de la ruta',
    pestanas: (participantes: number): Record<PestanaRuta, string> => ({
      escalera: 'Escalera de niveles',
      participantes: `Participantes (${participantes})`,
      historial: 'Historial',
    }),
    estadoCambiado: (estado: EstadoRuta) => `Estado de la ruta: ${estado}.`,
    archivar: {
      title: 'Archivar ruta',
      description: (inscritos: number) =>
        inscritos > 0
          ? `La ruta tiene ${inscritos} ${inscritos === 1 ? 'inscripción activa' : 'inscripciones activas'}. Al archivarla se conservan, pero no admitirá nuevas inscripciones ni cambios de nivel. Puedes volver a ponerla Vigente.`
          : 'La ruta dejará de admitir nuevas inscripciones. Puedes volver a ponerla Vigente.',
      confirmar: 'Archivar ruta',
    },
    noPublicable: 'La ruta no se puede publicar todavía:',
  },

  escalera: {
    title: 'Niveles de la ruta',
    ayuda: 'El nivel más alto está arriba. Cada nivel apunta a un puesto del Descriptivo de puestos.',
    aria: 'Escalera de niveles',
    vacio: 'Esta ruta aún no tiene niveles. Agrega el primero con el puesto de entrada.',
    nivel: (n: number) => `Nivel ${n}`,
    personas: (n: number) => `${n} ${n === 1 ? 'persona' : 'personas'}`,
    ver: (n: number, puesto: string) => `Ver nivel ${n}: ${puesto}`,
    subir: (puesto: string) => `Subir ${puesto} al nivel superior`,
    bajar: (puesto: string) => `Bajar ${puesto} al nivel inferior`,
    quitar: (puesto: string) => `Quitar ${puesto} de la ruta`,
    enUso: 'No se puede quitar ni cambiar el puesto: el nivel está en uso por inscripciones activas.',
    puestoInactivo: 'El puesto de este nivel está inactivo; la ruta no podrá volver a publicarse hasta cambiarlo.',
    agregar: 'Agregar nivel (puesto del Descriptivo de puestos)',
    agregarBoton: 'Agregar nivel arriba',
    sinPuestos: 'No hay puestos activos disponibles para agregar.',
    elegirPuesto: 'Elige un puesto',
    nivelAgregado: (n: number, puesto: string) => `Nivel agregado: ${puesto} (nivel ${n}).`,
    nivelQuitado: (puesto: string) => `Nivel «${puesto}» quitado de la ruta.`,
    ordenActualizado: 'Orden de niveles actualizado.',
    nivelEditado: 'Nivel actualizado.',
    quitarTitulo: 'Quitar nivel',
    quitarDescripcion: (puesto: string) => `Se quitará «${puesto}» de la ruta. Esta acción solo es posible si ningún participante activo lo usa.`,
    quitarConfirmar: 'Quitar nivel',
    sinPuesto: 'Puesto no disponible',
  },

  detalle: {
    titulo: (n: number, puesto: string) => `Nivel ${n}: ${puesto}`,
    subtitulo: (codigo: string | null, vs: string) => `${codigo ?? '—'}. Requisitos tomados del puesto; en gris, el diferencial frente al ${vs}.`,
    vsPartida: 'punto de partida',
    vsNivel: (n: number, puesto: string) => `nivel ${n} (${puesto})`,
    competencias: 'Competencias',
    conocimientos: 'Conocimientos',
    experiencia: 'Experiencia',
    informativo: 'Informativo',
    informativoAyuda: 'Requisitos de formación y capacitación del puesto, solo para consulta: no llevan grado ni diferencial.',
    formacion: 'Formación',
    capacitacion: 'Capacitación',
    sinCompetencias: 'El puesto no define competencias.',
    sinConocimientos: 'El puesto no define requisitos de formación ni capacitación.',
    sinRequisitos: 'Sin requisitos de experiencia.',
    primerNivel: 'Primer nivel de la ruta',
    nuevo: 'Nuevo requisito',
    sinCambio: 'Sin cambio',
    sube: (delta: number, antes: Grado) => `+${delta} (antes ${antes})`,
    baja: (delta: number, antes: Grado) => `${delta} (antes ${antes})`,
    quitadas: 'Competencias que este nivel ya no pide',
    anioDif: (valor: number | null, primero: boolean) => {
      if (primero) return 'Primer nivel de la ruta'
      if (valor === null) return 'Sin dato para comparar'
      if (valor === 0) return 'Sin cambio'
      return valor > 0 ? `+${String(valor).replace('.', ',')} ${valor === 1 ? 'año' : 'años'}` : `${String(valor).replace('.', ',')} años`
    },
    escala: 'Escala de grados: A (más alto) a D (más bajo). El diferencial se calcula a partir de los puestos; no se guarda como dato aparte.',
    seleccionar: 'Selecciona un nivel para ver sus requisitos.',
    gradoDe: (nombre: string, grado: Grado) => `${nombre}: grado ${grado}`,
    propios: {
      titulo: 'Datos del nivel',
      nombre: 'Nombre del nivel',
      experiencia: 'Experiencia mínima (años)',
      experienciaAyuda: 'Opcional, entre 0 y 60 años.',
      guardar: 'Guardar nivel',
      soloLectura: 'Competencias y conocimientos se gestionan en Estructura Empresarial; aquí solo se leen.',
    },
  },

  participantes: {
    title: 'Participantes inscritos',
    filtroEstado: 'Estado',
    todos: 'Todos',
    columnas: { colaborador: 'Colaborador', actual: 'Nivel actual', objetivo: 'Nivel objetivo', estado: 'Estado', acciones: 'Acciones' },
    sinNivel: 'Sin definir',
    vacio: 'Esta ruta no tiene participantes. Inscribe al primero con el formulario.',
    vacioFiltro: 'Ningún participante coincide con el filtro.',
    cambiar: 'Cambiar niveles',
    cambiarDe: (nombre: string) => `Cambiar los niveles de ${nombre}`,
    alcanzado: 'Registrar nivel alcanzado',
    alcanzadoDe: (nombre: string) => `Registrar el nivel alcanzado de ${nombre}`,
    retirar: 'Retirar',
    retirarA: (nombre: string) => `Retirar a ${nombre} de la ruta`,
    tabla: 'Participantes de la ruta',
    retiradoPor: (motivo: string | null) => (motivo ? `Motivo: ${motivo}` : ''),
  },

  cantera: {
    title: 'Cantera',
    descripcion: 'Personas por nivel de la ruta.',
    aria: (partes: string) => `Personas por nivel: ${partes}`,
    nivel: (n: number) => `Nivel ${n}`,
    sinNivel: (n: number) => `${n} ${n === 1 ? 'participante activo' : 'participantes activos'} sin nivel actual definido.`,
    sinCantera: (niveles: number[]) => `Sin cantera en ${niveles.map((n) => `nivel ${n}`).join(', ')}.`,
  },

  inscribir: {
    title: 'Inscribir colaborador',
    colaborador: 'Colaborador',
    buscar: 'Buscar colaborador',
    seleccionar: 'Selecciona un colaborador',
    colaboradorRequerido: 'Elige el colaborador que quieres inscribir.',
    sinResultados: 'Ningún colaborador con vínculo vigente coincide con la búsqueda.',
    nivelActual: 'Nivel actual (opcional)',
    nivelObjetivo: 'Nivel objetivo (opcional)',
    sinNivel: 'Sin definir',
    ayuda: 'Solo se pueden inscribir colaboradores con vínculo laboral vigente. El nivel objetivo debe ser posterior al actual.',
    boton: 'Inscribir en la ruta',
    noVigente: 'Solo las rutas Vigentes admiten nuevas inscripciones.',
    sinNiveles: 'Agrega al menos un nivel antes de inscribir participantes.',
    exito: (nombre: string) => `${nombre} fue inscrito en la ruta.`,
    errorGuardar: 'No se pudo inscribir al colaborador.',
  },

  niveles: {
    titulo: 'Cambiar niveles',
    descripcion: (nombre: string) => `Ajusta el nivel actual y el nivel objetivo de ${nombre}. El objetivo debe ser posterior al actual.`,
    actual: 'Nivel actual',
    objetivo: 'Nivel objetivo',
    sinNivel: 'Sin definir',
    guardar: 'Guardar niveles',
    objetivoPosterior: 'El nivel objetivo debe ser posterior al nivel actual.',
    exito: (nombre: string) => `Niveles de ${nombre} actualizados.`,
    errorGuardar: 'No se pudieron cambiar los niveles.',
  },

  alcanzado: {
    title: 'Registrar nivel alcanzado',
    description: (nombre: string, nivel: string) => `${nombre} pasará al nivel «${nivel}». Es un registro informativo: el cambio real de puesto lo ejecuta Gestión de Personal.`,
    confirmar: 'Registrar nivel alcanzado',
    exito: (nombre: string, finalizada: boolean) => (finalizada ? `${nombre} completó la ruta: la inscripción quedó Finalizada.` : `Nivel alcanzado registrado para ${nombre}.`),
    nota: 'Informativo: el cambio real de puesto lo ejecuta Gestión de Personal.',
    sinObjetivo: 'Define primero un nivel objetivo.',
    errorGuardar: 'No se pudo registrar el nivel alcanzado.',
  },

  retirar: {
    title: 'Retirar de la ruta',
    description: (nombre: string) => `${nombre} dejará de participar en la ruta. Indica el motivo; quedará en el historial.`,
    motivo: 'Motivo del retiro',
    motivoRequerido: 'El motivo del retiro es obligatorio.',
    motivoLargo: 'El motivo no puede superar los 500 caracteres.',
    confirmar: 'Retirar',
    exito: (nombre: string) => `${nombre} salió de la ruta.`,
    errorGuardar: 'No se pudo retirar la inscripción.',
  },

  historial: {
    title: 'Historial de la ruta',
    descripcion: 'Cada cambio de la ruta, de sus niveles y de las inscripciones, con quién lo hizo.',
    columnas: { fecha: 'Fecha', usuario: 'Usuario', accion: 'Acción', anterior: 'Valor anterior', nuevo: 'Valor nuevo', motivo: 'Motivo' },
    vacio: 'Todavía no hay cambios registrados.',
    tabla: 'Historial de cambios de la ruta',
    acciones: {
      creada: 'Ruta creada',
      editada: 'Ruta editada',
      estado_cambiado: 'Cambio de estado',
      nivel_agregado: 'Nivel agregado',
      nivel_editado: 'Nivel editado',
      nivel_reordenado: 'Nivel reordenado',
      nivel_quitado: 'Nivel quitado',
      inscrita: 'Colaborador inscrito',
      niveles_cambiados: 'Niveles cambiados',
      nivel_alcanzado: 'Nivel alcanzado',
      retirada: 'Inscripción retirada',
    } as Record<string, string>,
    sinValor: '—',
    campos: {
      nombre: 'Nombre',
      tipo: 'Tipo',
      descripcion: 'Descripción',
      departamento_id: 'Departamento',
      estado: 'Estado',
      orden: 'Orden',
      puesto_id: 'Puesto',
      nivel: 'Nivel',
      experiencia_minima_anios: 'Experiencia mínima (años)',
      colaborador_id: 'Colaborador',
      nivel_actual: 'Nivel actual',
      nivel_objetivo: 'Nivel objetivo',
    } as Record<string, string>,
  },

  miRuta: {
    eyebrow: 'Desarrollo y Formación',
    title: 'Mi ruta de carrera',
    subtitle: 'Tu camino de crecimiento: dónde estás, a dónde aspiras y lo que se exige en cada nivel.',
    vacio: { title: 'Aún no estás inscrito en ninguna ruta', description: 'Cuando el equipo de Talento Humano te inscriba en una ruta, la verás aquí. Mientras tanto, puedes consultar las rutas disponibles.' },
    verAdecuacion: 'Ver mi adecuación',
    escalera: 'Escalera de niveles',
    actual: 'Tu nivel actual',
    objetivo: 'Tu nivel objetivo',
    siguiente: 'Siguiente nivel',
    tagActual: 'Estás aquí',
    tagObjetivo: 'Objetivo',
    tagSiguiente: 'Siguiente',
    detalleSiguiente: (puesto: string) => `Tu siguiente nivel: ${puesto}`,
    detalleObjetivo: (puesto: string) => `Tu nivel objetivo: ${puesto}`,
    detalleAyuda: (n: number, codigo: string | null, vs: string) => `Nivel ${n}${codigo ? ` · ${codigo}` : ''}. En gris, lo que cambia frente a ${vs}.`,
    vsActual: (puesto: string) => `tu nivel actual (${puesto})`,
    vsAnterior: 'el nivel anterior',
    nivelMasAlto: 'Estás en el nivel más alto de esta ruta',
    sinSiguiente: 'No hay un nivel superior definido en esta ruta.',
    sinObjetivo: 'Todavía no tienes un nivel objetivo definido.',
    elegirNivel: 'Elige un nivel de la escalera para ver lo que se exige.',
    disponibles: {
      title: 'Rutas disponibles',
      descripcion: 'Rutas vigentes que puedes consultar. La inscripción la gestiona Talento Humano.',
      vacio: 'No hay rutas vigentes por ahora.',
    },
  },
} as const

export type RutasTexto = typeof rutasText
