import type { Alcance, EstadoBrecha, Nivel, Prioridad } from './api'

/** Textos de la DNF (todo texto visible va aquí, en español). */
export const dnfText = {
  navLabel: 'DNF',
  noDisponible: 'No disponible',
  noPermission: {
    title: 'No tienes permisos para ver esta pantalla',
    description: 'Si crees que es un error, contacta a tu administrador.',
    back: 'Volver al inicio',
  },
  prioridad: { Critica: 'Crítica', Alta: 'Alta', Media: 'Media', Baja: 'Baja' } satisfies Record<Prioridad, string>,
  sinPrioridad: '—',
  sinPrioridadAria: 'Sin prioridad',
  estado: {
    ConBrecha: 'Con brecha',
    Cumple: 'Cumple',
    Supera: 'Supera',
    SinEvaluacion: 'Sin evaluación',
  } satisfies Record<EstadoBrecha, string>,
  tipoCompetencia: { Cardinal: 'Cardinal', Especifica: 'Específica' },
  alcance: { Individual: 'Individual', Departamento: 'Departamento' } satisfies Record<Alcance, string>,
  nivel: (nivel: Nivel | null) => (nivel === null ? 'Sin nivel' : `Nivel ${nivel}`),
  motivoExclusion: {
    SinPuesto: 'No tiene puesto',
    SinPerfil: 'Su puesto no tiene perfil de competencias',
    SinVinculoVigente: 'No tiene vínculo laboral vigente',
  } as Record<string, string>,
  fechaLabel: 'Fecha del diagnóstico',

  admin: {
    title: 'Detección de necesidades formativas',
    subtitle: 'Compara el perfil requerido del puesto con las competencias actuales y calcula la brecha de cada una.',
    verHistorial: 'Ver historial',
    pasos: { etiqueta: 'Pasos de la detección', seleccion: 'Selección', diagnostico: 'Diagnóstico' },
    paso1: {
      titulo: 'Paso 1 · ¿A quién quieres evaluar?',
      descripcion: 'Elige un colaborador o un departamento completo (DNF masivo).',
      alcanceLegend: 'Alcance',
      individual: { titulo: 'Un colaborador', descripcion: 'Diagnóstico individual contra su puesto actual.' },
      departamento: { titulo: 'Por departamento (DNF masivo)', descripcion: 'Evalúa a todos los colaboradores del departamento a la vez.' },
      colaboradorLabel: 'Colaborador',
      colaboradorPlaceholder: 'Busca un colaborador',
      departamentoLabel: 'Departamento',
      departamentoPlaceholder: 'Elige un departamento',
      previewColaborador: 'Colaborador seleccionado',
      puesto: 'Puesto',
      puestoDepartamento: 'Departamento',
      competenciasPerfil: 'Competencias del perfil',
      colaboradoresEvaluables: (n: number) => (n === 1 ? '1 colaborador evaluable' : `${n} colaboradores evaluables`),
      detectar: 'Detectar necesidades',
      detectando: 'Detectando…',
      errorColaborador: 'Elige un colaborador.',
      errorDepartamento: 'Elige un departamento.',
      errorAlcance: 'Elige el alcance de la detección.',
      sinOpciones: {
        title: 'No hay colaboradores evaluables',
        description: 'Aún no hay colaboradores con vínculo vigente, puesto y perfil de competencias.',
      },
    },
    confirmar: {
      titulo: 'Generar DNF masiva',
      descripcion: (n: number, departamento: string) =>
        `Se evaluarán ${n} ${n === 1 ? 'colaborador' : 'colaboradores'} del departamento ${departamento}. El diagnóstico se guarda y no se puede editar.`,
      confirmar: 'Generar DNF',
      cancelar: 'Cancelar',
    },
    paso2: {
      titulo: (alcance: string) => `Paso 2 · Diagnóstico: ${alcance}`,
      cambiarSeleccion: 'Cambiar selección',
      guardadaComo: (codigo: string) => `Diagnóstico guardado como ${codigo}.`,
      volverResumen: 'Volver al resumen',
    },
    kpi: {
      evaluadas: 'Competencias evaluadas',
      conBrecha: 'Con brecha',
      urgentes: 'Prioridad crítica o alta',
      urgentesNota: (n: number) => (n === 1 ? 'requiere atención primero' : 'requieren atención primero'),
      prioridadMasAlta: 'Prioridad más alta',
      colaboradores: 'Colaboradores evaluados',
    },
    avisos: {
      omitidas: (n: number) =>
        n === 1 ? 'Se omitió 1 competencia inactiva del perfil.' : `Se omitieron ${n} competencias inactivas del perfil.`,
      sinEvaluacion: (n: number) =>
        n === 1 ? 'Hay 1 competencia sin evaluación: el colaborador no tiene nivel actual registrado.' : `Hay ${n} competencias sin evaluación: falta el nivel actual registrado.`,
      excluidos: (n: number) => (n === 1 ? 'Se excluyó 1 colaborador de la evaluación:' : `Se excluyeron ${n} colaboradores de la evaluación:`),
    },
    resumen: {
      titulo: 'Resumen por colaborador',
      columnas: { colaborador: 'Colaborador', puesto: 'Puesto', brechas: 'Brechas', prioridad: 'Prioridad más alta', accion: 'Acciones' },
      ver: (nombre: string) => `Ver diagnóstico de ${nombre}`,
      verBoton: 'Ver diagnóstico',
    },
    tabla: {
      label: 'Diagnóstico por competencia',
      columnas: {
        competencia: 'Competencia',
        requerido: 'Nivel requerido',
        actual: 'Nivel actual',
        brechaGrados: 'Brecha (grados)',
        brechaPorcentaje: 'Brecha (%)',
        prioridad: 'Prioridad',
        estado: 'Estado',
      },
      sinNivel: '—',
      inactiva: 'Inactiva',
      vacio: 'Ninguna competencia coincide con los filtros.',
      calculo:
        'Cálculo: diferencia = nivel actual − nivel requerido (D = 1, C = 2, B = 3, A = 4); brecha % = (requerido − actual) / requerido × 100. Prioridad: Crítica = 3 grados, Alta = 2, Media = 1 grado en una competencia Cardinal, Baja = 1 grado en una Específica.',
    },
    filtros: {
      prioridad: 'Filtrar por prioridad',
      prioridadTodas: 'Todas las prioridades',
      estado: 'Filtrar por estado',
      estadoTodos: 'Todos los estados',
      soloConBrecha: 'Mostrar solo competencias con brecha',
    },
    grafico: {
      titulo: 'Perfil ideal vs. perfil actual',
      descripcion: (nombre: string) => `Colaborador: ${nombre}`,
      actual: 'Perfil actual (barras)',
      ideal: 'Perfil ideal (línea)',
      brecha: 'Brecha',
      sinNivel: 'sin nivel actual',
      resumen: (nombre: string, n: number) => `Gráfico del perfil requerido contra el actual de ${nombre} en ${n} competencias.`,
      fila: (nombre: string, requerido: Nivel, actual: Nivel | null) =>
        `${nombre}: requerido ${requerido}, actual ${actual ?? 'sin nivel'}`,
      selector: 'Colaborador del gráfico',
    },
    plan: {
      boton: 'Generar Plan de Formación',
      generando: 'Generando…',
      motivoSinBrechas: 'La DNF no tiene brechas: no hay nada que planificar.',
      motivoYaGenerado: 'Esta DNF ya generó un plan de formación.',
      verPlan: 'Ver plan',
      generado: 'Se generó el borrador del plan de formación.',
      generadoPor: (nombre: string, fecha: string) => `Plan generado por ${nombre} el ${fecha}.`,
      noDisponible: 'Plan de Formación no disponible. Inténtalo más tarde.',
    },
    error: {
      title: 'No se pudo cargar la información',
      description: 'Ocurrió un problema al consultar el servidor.',
      retry: 'Reintentar',
      generar: 'No se pudo generar la DNF. Revisa los datos e inténtalo de nuevo.',
    },
  },

  historial: {
    title: 'Historial de DNF',
    subtitle: 'Diagnósticos generados. Son de solo lectura: no se editan ni se eliminan.',
    breadcrumbInicio: 'Detección de necesidades formativas',
    filtros: {
      desde: 'Desde',
      hasta: 'Hasta',
      colaborador: 'Colaborador',
      colaboradorPlaceholder: 'Todos los colaboradores',
      departamento: 'Departamento',
      departamentoTodos: 'Todos los departamentos',
      prioridad: 'Prioridad más alta',
      prioridadTodas: 'Todas las prioridades',
      limpiar: 'Limpiar filtros',
      errorRango: 'La fecha inicial no puede ser posterior a la final.',
    },
    columnas: {
      codigo: 'Código',
      fecha: 'Fecha',
      alcance: 'Alcance',
      objetivo: 'Colaborador o departamento',
      brechas: 'Brechas',
      prioridad: 'Prioridad más alta',
      plan: 'Plan',
      acciones: 'Acciones',
    },
    tienePlan: 'Con plan',
    sinPlan: 'Sin plan',
    verDetalle: 'Ver detalle',
    verDetalleDe: (codigo: string) => `Ver detalle de ${codigo}`,
    tableLabel: 'Historial de diagnósticos',
    vacio: { title: 'Aún no hay diagnósticos', description: 'Cuando generes una DNF aparecerá aquí.' },
    sinCoincidencias: 'Ningún diagnóstico coincide con los filtros.',
    paginacion: (desde: number, hasta: number, total: number) => `Mostrando ${desde}–${hasta} de ${total}`,
  },

  detalle: {
    solo_lectura: 'Detalle de solo lectura',
    generadoPor: (nombre: string, fecha: string) => `Generada por ${nombre} el ${fecha}`,
    noExiste: { title: 'La DNF no existe', description: 'Es posible que haya sido consultada con un enlace incorrecto.', back: 'Volver al historial' },
  },

  colaborador: {
    title: 'Mis brechas formativas',
    subtitle: (puesto: string) => `Qué te falta para alcanzar el perfil de tu puesto · ${puesto}`,
    subtituloSinPuesto: 'Qué te falta para alcanzar el perfil de tu puesto',
    loadingLabel: 'Cargando tu diagnóstico',
    kpi: { conBrecha: 'Con brecha', cumples: 'Ya cumples', sinEvaluacion: 'Sin evaluación', ultimaDnf: 'Último diagnóstico' },
    leyenda: {
      titulo: 'Cómo leer las barras',
      requerido: 'Nivel requerido por tu puesto',
      actual: 'Tu nivel actual',
      nota: 'A es el nivel más alto · Diferencia negativa = te falta',
    },
    prioritarias: {
      titulo: 'Mis brechas prioritarias',
      descripcion: 'Las 3 competencias que requieren más atención, de mayor a menor prioridad.',
    },
    otras: 'Otras brechas detectadas',
    cumples: (n: number) => `Competencias que ya cumples (${n})`,
    tarjeta: {
      requerido: 'Requerido',
      actual: 'Actual',
      detalle: (actual: Nivel | null, requerido: Nivel, diferencia: number | null, porcentaje: number | null) =>
        `Nivel actual: ${actual ?? 'sin nivel'} · Requerido: ${requerido} · Diferencia: ${diferencia === null ? '—' : diferencia}` +
        (porcentaje === null ? '' : ` (${porcentaje.toLocaleString('es-EC', { maximumFractionDigits: 2 })} % de brecha)`),
      aria: (nombre: string, prioridad: string, actual: Nivel | null, requerido: Nivel, diferencia: number | null) =>
        `${nombre}, prioridad ${prioridad}. Nivel actual ${actual ?? 'sin nivel'}, requerido ${requerido}, diferencia ${diferencia === null ? 'sin calcular' : diferencia}`,
      verCursos: 'Ver cursos sugeridos',
      verCursosDe: (nombre: string) => `Ver cursos sugeridos para ${nombre}`,
      sinCursos: 'Aún no hay cursos asociados en el catálogo.',
    },
    vacio: {
      sinDnf: { title: 'Aún no tienes un diagnóstico', description: 'Cuando el equipo de talento humano genere tu detección de necesidades formativas, tus brechas aparecerán aquí.' },
      sinBrechas: { title: 'No tienes brechas detectadas', description: 'Tu último diagnóstico no muestra competencias por debajo del nivel que exige tu puesto.' },
    },
    error: { title: 'No se pudo cargar tu diagnóstico', description: 'Ocurrió un problema al consultar el servidor.', retry: 'Reintentar' },
  },

  /** Fecha y hora legible en español (Ecuador). */
  fecha: (iso: string) =>
    new Date(iso).toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric' }),
} as const
