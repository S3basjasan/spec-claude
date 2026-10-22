import type { Asistencia, EstadoActividad, MotivoBloqueo, TipoAccion } from './api'

const MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic']

function partes(iso: string): [number, number, number] {
  const [anio, mes, dia] = iso.split('-').map(Number)
  return [anio, mes, dia]
}

/** «5 mar 2026» a partir de «2026-03-05». */
function fecha(iso: string | null): string {
  if (!iso) return '—'
  const [anio, mes, dia] = partes(iso)
  return `${dia} ${MESES_CORTOS[mes - 1]} ${anio}`
}

/** «10–12 mar 2026», «28 feb – 2 mar 2026» o «30 dic 2025 – 3 ene 2026»; una sola fecha si coinciden. */
function rango(inicio: string | null, fin: string | null): string {
  if (!inicio && !fin) return '—'
  if (!inicio || !fin || inicio === fin) return fecha(inicio ?? fin)
  const [a1, m1, d1] = partes(inicio)
  const [a2, m2, d2] = partes(fin)
  if (a1 !== a2) return `${fecha(inicio)} – ${fecha(fin)}`
  if (m1 !== m2) return `${d1} ${MESES_CORTOS[m1 - 1]} – ${d2} ${MESES_CORTOS[m2 - 1]} ${a2}`
  return `${d1}–${d2} ${MESES_CORTOS[m2 - 1]} ${a2}`
}

/** Promedio con un decimal y coma decimal; «—» cuando no hay calificaciones. */
function promedio(valor: number | null): string {
  return valor === null ? '—' : valor.toFixed(1).replace('.', ',')
}

/** Textos de las Actividades formativas (todo texto visible va aquí, en español). */
export const actividadesText = {
  navLabel: 'Actividades',
  noDisponible: 'No disponible',
  sinDato: '—',
  fecha,
  rango,
  promedio,
  noPermission: {
    title: 'No tienes permisos para ver esta pantalla',
    description: 'Si crees que es un error, contacta a tu administrador.',
    back: 'Volver al inicio',
  },
  loading: 'Cargando actividades',
  error: {
    title: 'No se pudieron cargar los datos',
    description: 'Revisa tu conexión e inténtalo de nuevo.',
    retry: 'Reintentar',
  },
  estado: {
    Programada: 'Programada',
    EnCurso: 'En curso',
    Finalizada: 'Finalizada',
    Cancelada: 'Cancelada',
  } satisfies Record<EstadoActividad, string>,
  tipo: {
    Presencial: 'Presencial',
    Virtual: 'Virtual',
    ELearning: 'E-learning',
    OJT: 'OJT',
    Codesarrollo: 'Codesarrollo',
    Mentoring: 'Mentoring',
  } satisfies Record<TipoAccion, string>,
  asistencia: {
    Pendiente: 'Pendiente',
    Presente: 'Presente',
    Ausente: 'Ausente',
    Justificado: 'Justificado',
  } satisfies Record<Asistencia, string>,
  asistenciaPendiente: 'Asistencia pendiente',
  bloqueo: {
    actividad_programada: 'La actividad aún no ha comenzado: el registro de asistencia se habilita desde su fecha de inicio.',
    actividad_cancelada: 'La actividad está cancelada: no se puede registrar nada nuevo, pero se conservan los registros existentes.',
    plan_cerrado: 'El plan está cerrado: la actividad y sus registros son de solo lectura.',
  } satisfies Record<MotivoBloqueo, string>,

  admin: {
    title: 'Actividades formativas',
    subtitle: 'Ejecución de las actividades del plan: creación, asistencia y calificaciones.',
    nueva: '+ Nueva Actividad',
    vistas: 'Vistas de actividades',
    pestanas: { actividades: 'Actividades', calificaciones: 'Calificaciones' },
    tableLabel: 'Actividades formativas',
    filtros: {
      buscar: 'Buscar',
      buscarPlaceholder: 'Buscar por nombre',
      tipo: 'Tipo',
      estado: 'Estado',
      departamento: 'Departamento',
      competencia: 'Competencia',
      plan: 'Plan',
      desde: 'Desde',
      hasta: 'Hasta',
      todos: 'Todos',
      todas: 'Todas',
      limpiar: 'Limpiar filtros',
      resumen: (mostradas: number, total: number) => `Mostrando ${mostradas} de ${total} actividades`,
    },
    columnas: {
      codigo: 'Código',
      nombre: 'Nombre',
      tipo: 'Tipo',
      fechas: 'Fechas',
      lugar: 'Lugar / Enlace',
      formador: 'Formador',
      departamento: 'Departamento',
      capacidad: 'Capacidad',
      inscritos: 'Inscritos',
      estado: 'Estado',
      acciones: 'Acciones',
    },
    acciones: {
      asistencia: 'Asistencia',
      editar: 'Editar',
      asistenciaDe: (nombre: string) => `Registrar asistencia de ${nombre}`,
      editarDe: (nombre: string) => `Editar ${nombre}`,
    },
    sinCoincidencias: 'Ninguna actividad coincide con los filtros.',
    vacio: {
      title: 'Aún no hay actividades',
      description: 'Crea la primera actividad a partir de una acción de un plan en ejecución.',
    },
    creada: (codigo: string) => `Se creó la actividad ${codigo}.`,
    actualizada: (codigo: string) => `Se actualizó la actividad ${codigo}.`,
    sinDepartamento: 'Sin departamento',
  },

  formulario: {
    tituloCrear: 'Nueva actividad formativa',
    tituloEditar: 'Editar actividad formativa',
    descripcion: 'La actividad es la ejecución de una acción del plan. Los datos de la acción se gestionan en el plan.',
    accion: 'Acción del plan',
    accionPlaceholder: 'Busca una acción por nombre',
    accionVacia: 'No hay acciones disponibles. Solo se ofrecen acciones no canceladas de planes en ejecución que aún no tienen actividad.',
    filtroPlan: 'Filtrar por plan',
    todosLosPlanes: 'Todos los planes',
    cargandoAcciones: 'Cargando acciones disponibles',
    heredados: 'Datos de la acción (solo lectura)',
    campos: { nombre: 'Nombre', tipo: 'Tipo', competencias: 'Competencias objetivo', fechas: 'Fechas', formador: 'Formador', capacidad: 'Capacidad máxima', inscritos: 'Inscritos', plan: 'Plan' },
    lugar: 'Lugar',
    enlace: 'Enlace',
    lugarHint: 'Texto libre, por ejemplo la sala o el edificio.',
    enlaceHint: 'Debe ser una URL válida que empiece por http:// o https://.',
    departamento: 'Departamento',
    departamentoOpcional: 'Opcional',
    sinDepartamento: 'Todos / sin departamento',
    crear: 'Crear actividad',
    guardar: 'Guardar cambios',
    cancelar: 'Cancelar',
    cerrar: 'Cerrar',
    soloLectura: 'El plan está cerrado: la actividad es de solo lectura.',
    errorGuardar: 'No se pudo guardar la actividad. Revisa los datos e inténtalo de nuevo.',
    errores: {
      accionRequerida: 'Elige la acción del plan.',
      lugarRequerido: 'El lugar o enlace es obligatorio.',
      lugarLargo: 'El lugar o enlace no puede superar los 500 caracteres.',
      urlInvalida: 'Ingresa una URL válida que empiece por http:// o https://.',
    },
  },

  registro: {
    title: (nombre: string) => `Asistencia — ${nombre}`,
    subtitle: 'Registra la asistencia, la calificación y las observaciones de los inscritos.',
    volver: 'Volver a actividades',
    tableLabel: 'Registro de asistencia',
    resumenLabel: 'Resumen de asistencia',
    resumen: { presentes: 'Presentes', ausentes: 'Ausentes', justificados: 'Justificados', sinRegistrar: 'Sin registrar' },
    columnas: { numero: '#', participante: 'Participante', asistencia: 'Asistencia', calificacion: 'Calificación (1–5)', observaciones: 'Observaciones' },
    nota: 'La calificación solo se registra si el participante estuvo presente. Escala entera de 1 a 5. Pulsa de nuevo la opción seleccionada para volver a Pendiente.',
    notaObservaciones: 'Las observaciones son de uso interno: solo las ve el administrador.',
    control: (nombre: string) => `Asistencia de ${nombre}`,
    calificacionDe: (nombre: string) => `Calificación de ${nombre}`,
    observacionesDe: (nombre: string) => `Observaciones de ${nombre}`,
    errorCalificacion: 'La calificación debe ser un entero de 1 a 5.',
    errorObservaciones: 'Las observaciones no pueden superar los 1000 caracteres.',
    marcarTodos: 'Marcar todos presentes',
    guardar: 'Guardar',
    guardando: 'Guardando…',
    cambiosSinGuardar: 'Tienes cambios sin guardar.',
    sinCambios: 'No hay cambios por guardar.',
    guardado: 'Registro guardado.',
    todosMarcados: 'Los participantes pendientes se marcaron como presentes. Pulsa Guardar para confirmar.',
    sinInscritos: { title: 'No hay inscritos', description: 'La acción del plan no tiene participantes inscritos.' },
    conflicto: 'Otro administrador modificó el registro. Recarga para ver los cambios.',
    recargar: 'Recargar',
    errorGuardar: 'No se pudo guardar el registro. Inténtalo de nuevo.',
    confirmar: {
      title: 'Confirma los cambios en registros existentes',
      description: 'Vas a modificar la asistencia de participantes que ya tenían un registro:',
      confirmar: 'Confirmar y guardar',
      cancelar: 'Volver',
      cambio: (nombre: string, antes: string, despues: string) => `${nombre}: ${antes} → ${despues}`,
    },
    salir: 'Tienes cambios sin guardar. Si sales, se perderán.',
    historial: 'Historial de registros',
    verHistorial: 'Ver historial',
  },

  historial: {
    title: 'Historial de registros',
    tableLabel: 'Historial de cambios de asistencia y calificación',
    columnas: { fecha: 'Fecha', usuario: 'Usuario', participante: 'Participante', asistencia: 'Asistencia', calificacion: 'Calificación' },
    vacio: 'Aún no hay cambios registrados.',
    cambio: (antes: string, despues: string) => `${antes} → ${despues}`,
    pendiente: 'Pendiente',
    sinCalificacion: 'Sin calificación',
    cerrar: 'Cerrar',
    anterior: 'Anterior',
    siguiente: 'Siguiente',
    pagina: (pagina: number, total: number) => `Página ${pagina} de ${total}`,
  },

  calificaciones: {
    porActividad: 'Por actividad',
    porParticipante: 'Por participante',
    label: 'Calificaciones',
    tabs: 'Agrupación de calificaciones',
    tablaActividad: 'Calificaciones por actividad',
    tablaParticipante: 'Calificaciones por participante',
    columnasActividad: { nombre: 'Actividad', tipo: 'Tipo', fechas: 'Fechas', calificados: 'Calificados', promedio: 'Promedio (1–5)' },
    columnasParticipante: { nombre: 'Participante', calificadas: 'Actividades calificadas', promedio: 'Promedio (1–5)', detalle: 'Detalle' },
    verDetalle: (nombre: string) => `Ver detalle de ${nombre}`,
    detalleTitulo: (nombre: string) => `Detalle de ${nombre}`,
    detalleColumnas: { actividad: 'Actividad', fecha: 'Fecha', asistencia: 'Asistencia', calificacion: 'Calificación' },
    vacio: 'Aún no hay calificaciones registradas para este filtro.',
    sinCalificacion: 'Sin calificar',
    cerrarDetalle: 'Cerrar detalle',
    nota: 'El promedio considera solo las calificaciones asignadas dentro del filtro.',
  },

  colaborador: {
    title: 'Mis actividades formativas',
    subtitle: 'Las actividades en las que estás inscrito, con tu asistencia y calificación.',
    resumenLabel: 'Resumen de mis actividades',
    resumen: {
      inscritas: 'Actividades inscritas',
      porRealizar: (n: number) => `${n} por realizar`,
      finalizadas: 'Finalizadas',
      enHistorial: 'En tu historial',
      asistencia: 'Asistencia',
      asistenciaNota: (presentes: number, registradas: number) => `${presentes} de ${registradas} registradas`,
      calificacion: 'Calificación promedio',
      calificacionValor: (valor: string) => `${valor} / 5`,
      calificadas: (n: number) => `${n} ${n === 1 ? 'calificada' : 'calificadas'}`,
    },
    proximas: 'Próximas actividades',
    historial: 'Historial',
    sinProximas: 'No tienes actividades próximas.',
    sinHistorial: 'Aún no tienes actividades finalizadas.',
    vacio: {
      title: 'Aún no tienes actividades',
      description: 'Cuando tu administrador cree la actividad de una acción en la que estás inscrito, la verás aquí.',
    },
    tarjeta: { lugar: 'Lugar', enlace: 'Enlace', formador: 'Formador', fecha: 'Fecha', asistencia: 'Asistencia', calificacion: 'Calificación', sinCalificacion: 'Sin calificación', abrirEnlace: 'Abrir enlace' },
    cuando: {
      hoy: 'Es hoy',
      manana: 'Empieza mañana',
      enDias: (n: number) => `Empieza en ${n} días`,
      enCurso: 'Está en curso',
    },
    lista: (titulo: string) => titulo,
  },
}
