export const uiText = {
  pagination: {
    label: 'Paginación',
    noResults: 'Sin resultados',
    showing: (from: number, to: number, total: number) => `Mostrando ${from}–${to} de ${total}`,
    previous: 'Anterior',
    next: 'Siguiente',
    pageOf: (page: number, last: number) => `Página ${page} de ${last}`,
  },
} as const
