import type { EstadoDePosicion, ModalidadDePosicion } from './api'

export const organigramaText = {
  navLabel: 'Organigrama',
  title: 'Organigrama',
  subtitle: 'Estructura de reporte generada a partir de las posiciones vigentes.',
  listLink: 'Ver listado de posiciones',
  loadingLabel: 'Cargando el organigrama…',
  emptyTitle: 'Aún no hay posiciones vigentes para mostrar el organigrama.',
  emptyDescription: 'Cuando se creen posiciones vigentes aparecerán aquí.',
  errorTitle: 'No se pudo cargar el organigrama.',
  errorDescription: 'Revisa tu conexión e inténtalo de nuevo.',
  retry: 'Reintentar',
  toolbar: {
    searchLabel: 'Buscar',
    searchPlaceholder: 'Código, puesto, unidad u ocupante',
    unitLabel: 'Unidad organizacional',
    allUnits: 'Todas las unidades',
    expandAll: 'Expandir todo',
    collapseAll: 'Contraer todo',
    clearFilters: 'Limpiar filtros',
  },
  summary: {
    plain: (vigentes: number, ocupadas: number, vacantes: number) =>
      `${vigentes} ${vigentes === 1 ? 'posición vigente' : 'posiciones vigentes'} · ${ocupadas} ${
        ocupadas === 1 ? 'ocupada' : 'ocupadas'
      } · ${vacantes} ${vacantes === 1 ? 'vacante' : 'vacantes'}`,
    filtered: (coinciden: number, vigentes: number) =>
      `${coinciden} ${coinciden === 1 ? 'posición coincide' : 'posiciones coinciden'} de ${vigentes} ${
        vigentes === 1 ? 'vigente' : 'vigentes'
      }`,
  },
  legend: { occupied: 'Ocupada', vacant: 'Vacante', suppressed: 'Las posiciones suprimidas no se muestran.' },
  statuses: { Ocupada: 'Ocupada', Vacante: 'Vacante' } satisfies Record<EstadoDePosicion, string>,
  modalities: {
    Nombramiento: 'Nombramiento',
    Contrato: 'Contrato',
    Encargo: 'Encargo',
    Subrogacion: 'Subrogación',
  } satisfies Record<ModalidadDePosicion, string>,
  noOccupant: 'Sin ocupante',
  topAuthority: 'Máxima autoridad',
  tree: {
    areaLabel: 'Árbol del organigrama',
    help: 'Arrastra para desplazarte. Haz clic en una tarjeta para ver su detalle.',
    collapse: '− Contraer',
    expandBranch: (cantidad: number) => `+ ${cantidad} ${cantidad === 1 ? 'posición' : 'posiciones'}`,
    collapseLabel: (codigo: string) => `Contraer ${codigo}`,
    expandLabel: (codigo: string) => `Expandir ${codigo}`,
    cardLabel: (codigo: string, puesto: string, estado: string) => `${codigo}, ${puesto}, ${estado}`,
  },
  zoom: {
    zoomOut: 'Alejar',
    zoomIn: 'Acercar',
    reset: 'Restablecer zoom',
    percent: (valor: number) => `${Math.round(valor * 100)} %`,
  },
  detail: {
    regionLabel: 'Detalle de la posición',
    close: 'Cerrar detalle',
    occupant: 'Ocupante',
    supervisor: 'Supervisor',
    supervises: (cantidad: number) => `Supervisa a (${cantidad})`,
    noSubordinates: 'Ninguna posición le reporta.',
    position: 'Puesto',
    unit: 'Unidad organizacional',
    unitInfo: (tipo: string, estado: string) => `${tipo} · ${estado}`,
    goTo: (codigo: string) => `Ir a ${codigo}`,
  },
} as const
