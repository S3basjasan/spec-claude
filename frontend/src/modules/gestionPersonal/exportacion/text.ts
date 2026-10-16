/** Todos los textos visibles de la exportación individual de la ficha (español). */
export const exportacionText = {
  navLabel: 'Exportar mi ficha',
  button: 'Exportar ficha',
  buttonBusy: 'Generando PDF…',
  progress: 'Generando el PDF de la ficha. Esto puede tardar unos segundos.',
  success: (nombre: string) => `Se descargó ${nombre}. El archivo no se agregó a Documentos.`,
  errors: {
    generic: 'No se pudo generar la ficha. Inténtalo de nuevo.',
    unavailable: 'No se pudo generar la ficha porque una parte de la información no está disponible. Inténtalo de nuevo en unos minutos.',
    forbidden: 'Ya no tienes permiso para exportar esta ficha.',
    notFound: 'La ficha ya no está disponible.',
    notPdf: 'El servidor no devolvió un PDF válido. No se descargó ningún archivo.',
  },
  retry: 'Reintentar',
  mine: {
    title: 'Exportar mi ficha',
    subtitle: 'Descarga un PDF con la información guardada de tu ficha: datos personales, situación laboral, formación, experiencia, movilidad y trayectoria.',
    note: 'El PDF refleja lo guardado en el momento de generarlo. No incluye tus documentos ni se agrega a ellos.',
    noRecordTitle: 'Tu cuenta no tiene una ficha asociada',
    noRecordDescription: 'Cuando tu organización asocie tu cuenta a una ficha podrás exportarla aquí.',
    noPermissionTitle: 'No tienes permiso para exportar tu ficha',
    noPermissionDescription: 'Tu cuenta no tiene habilitada la exportación. Consulta con Talento Humano.',
  },
} as const
