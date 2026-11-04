/** Todos los textos visibles de los parámetros del módulo (español), tal como aparecen en el tablero de diseño. */
export const parametrosText = {
  navLabel: 'Parámetros',
  title: 'Parámetros del módulo',
  subtitle: 'Ajusta las reglas del módulo de reconocimientos. Los valores son únicos para toda la organización.',
  tabsLabel: 'Secciones de reconocimientos',
  tabs: { categorias: 'Categorías', parametros: 'Parámetros' },
  retry: 'Reintentar',
  discard: 'Descartar',
  save: 'Guardar',
  saving: 'Guardando…',
  reload: 'Recargar',
  states: {
    loading: 'Cargando parámetros…',
    errorTitle: 'No se pudieron cargar los parámetros',
    errorDescription: 'Ocurrió un problema al consultar los parámetros. Inténtalo de nuevo.',
    saved: 'Los parámetros se guardaron correctamente.',
    saveError: 'No se pudo guardar. Revisa los campos marcados o inténtalo de nuevo.',
    conflict: 'Otra persona cambió un parámetro mientras editabas. Recarga para ver los valores actuales.',
    unchanged: 'No hay cambios por guardar.',
    noPermission: 'Esta sección es solo para el Gestor de Talento.',
  },
  fields: {
    units: { puntos: 'Puntos', dias: 'Días', porcentaje: 'Porcentaje' },
    current: (valor: string) => `Actual: ${valor}`,
    range: (minimo: number, maximo: number) => `Número entero de ${minimo} a ${maximo}.`,
    scheduled: (valor: number, desde: string) => `Programado: ${valor} desde el ${desde}.`,
  },
  parametros: {
    puntos_por_colaborador: {
      title: 'Puntos por colaborador al mes',
      description:
        'Cada colaborador activo aporta estos puntos al presupuesto mensual de su jefatura. También es el puntaje máximo que puede tener una categoría.',
      unit: 'puntos' as const,
      applies: 'El cambio se aplica desde el presupuesto del mes siguiente. Los reconocimientos y el presupuesto de este mes no cambian.',
    },
    plazo_anulacion_dias: {
      title: 'Plazo para anular un reconocimiento',
      description:
        'Días que tiene el líder, desde que otorga un reconocimiento, para anularlo. Pasado ese plazo ya no se puede anular.',
      unit: 'dias' as const,
      applies: 'Este cambio se aplica de inmediato a todas las anulaciones desde ese momento.',
    },
    umbral_concentracion: {
      title: 'Alerta de concentración',
      description:
        'Si una sola persona recibe más de este porcentaje de los puntos que otorgó su unidad en el mes, el líder y el Gestor reciben un aviso. Es solo informativo y no bloquea nada.',
      unit: 'porcentaje' as const,
      applies: 'Este cambio se aplica de inmediato a los avisos desde ese momento.',
    },
    plazo_max_desafio_dias: {
      title: 'Plazo máximo de un desafío',
      description:
        'Días máximos que puede durar un desafío desde que se asigna. El líder elige la fecha límite de cada desafío dentro de este máximo.',
      unit: 'dias' as const,
      applies: 'Este cambio se aplica de inmediato a los desafíos que se asignen desde ese momento.',
    },
    umbral_clima_unidad: {
      title: 'Alerta de clima por unidad',
      description:
        'Si más de este porcentaje de las personas de una unidad está en nivel «riesgo», Gerencia y el Gestor reciben un aviso. Solo se evalúan unidades de 5 personas o más.',
      unit: 'porcentaje' as const,
      applies: 'Este cambio se aplica de inmediato a los avisos desde ese momento.',
    },
  },
  preview: {
    title: 'Así quedaría',
    change: (actual: number, nuevo: number) => `${actual} → ${nuevo} puntos por colaborador al mes`,
    since: (fecha: string) => `Rige desde el ${fecha} (día 1 del mes siguiente).`,
    example: (puntos: number) => `Un equipo de 10 personas tendría ${puntos} puntos al mes.`,
  },
  sensibilidad: {
    title: 'Sensibilidad del radar de clima',
    description:
      'Define qué tan pronto el radar marca a una persona en «atención» o «riesgo». Eliges una opción; los umbrales ya están probados y no se escriben a mano.',
    legend: 'Sensibilidad del radar de clima',
    recommended: ' (recomendada)',
    options: {
      1: { label: 'Baja', description: 'Solo alerta en casos claros. Menos avisos.' },
      2: { label: 'Normal', description: 'Equilibrio entre detectar a tiempo y no saturar al líder.' },
      3: { label: 'Alta', description: 'Detecta antes las señales leves. Más avisos.' },
    },
    note: 'Aplica desde el siguiente cálculo semanal. Los líderes verán que la regla cambió en esa medición.',
  },
  lastChange: {
    initial: 'Valor inicial',
    by: (nombre: string | null, fecha: string, anterior: string, nuevo: string) =>
      `Último cambio: ${nombre ?? 'Usuario'}, ${fecha} (${anterior} ${nuevo})`,
    fromDefault: 'de valor por defecto',
    from: (valor: string) => `de ${valor}`,
    to: (valor: string) => `a ${valor}`,
  },
  validation: {
    required: 'Escribe un valor.',
    integer: 'Debe ser un número entero.',
    range: (minimo: number, maximo: number) => `Debe estar entre ${minimo} y ${maximo}.`,
    belowCategory: (nombre: string, puntos: string) => `No puede ser menor que la puntuación de la categoría «${nombre}» (${puntos} puntos).`,
  },
} as const
